<?php

namespace App\Tests\Integration;

use App\Security\SecondFactorSecretBox;
use App\Security\SecondFactorSettings;
use App\Security\Totp;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

/**
 * Phase 3 : second facteur obligatoire jusqu'au profil 4, mandats compris
 * ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 5}).
 *
 * TADMIN (profil 2), TEDIT (profil 8), TSUPER (profil 1) viennent des fixtures. La date d'entrée en vigueur
 * est posée par test en remplaçant SecondFactorSettings avant la première requête.
 */
final class AuthSecondFactorTest extends ApiTestCase
{
    private const PASSWORD = 'Correct-Horse-99!';
    private const NEW_PASSWORD = 'Battery-Staple-42?';
    private const ENFORCED = '2020-01-01';
    private const RECOVERY_CODES = 10;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        foreach (['TADMIN', 'TEDIT', 'TSUPER'] as $code) {
            $this->connection->executeStatement('UPDATE kp_user SET Pwd = ? WHERE Code = ?', [password_hash(self::PASSWORD, PASSWORD_BCRYPT), $code]);
        }
    }

    // ------------------------------------------------------------------ AUTH-2FA-00 : date d'entrée en vigueur

    public function testWithoutEnforcementDateAConcernedAccountLogsInWithoutDueDate(): void
    {
        $this->enforceFrom(null);

        $body = $this->login('TADMIN', 200);

        self::assertNull($body['user']['secondFactor']['enrollmentDueAt']);
        self::assertTrue($body['user']['secondFactor']['required'], 'concerné, même sans obligation en vigueur');
    }

    public function testEnforcementDateInTheFutureChangesNothing(): void
    {
        $this->enforceFrom('2999-01-01');

        self::assertNull($this->login('TADMIN', 200)['user']['secondFactor']['enrollmentDueAt']);
    }

    // ------------------------------------------------------------------ AUTH-2FA-01/02/03 : grâce de 7 jours

    public function testFirstConcernedLoginOpensTheGraceAndReturnsTheDueDate(): void
    {
        $this->enforceFrom(self::ENFORCED);

        $body = $this->login('TADMIN', 200);

        self::assertNotNull($body['user']['secondFactor']['enrollmentDueAt']);
        self::assertNotFalse($this->connection->fetchOne(
            'SELECT enrollment_required_since FROM kp_user_second_factor WHERE user_code = ? AND enrollment_required_since IS NOT NULL',
            ['TADMIN'],
        ));
    }

    public function testAMandateUpToProfile4MakesAProfile8AccountConcerned(): void
    {
        $this->enforceFrom(self::ENFORCED);
        self::assertNull($this->login('TEDIT', 200)['user']['secondFactor']['enrollmentDueAt'], 'profil 8 sans mandat : non concerné');

        $this->connection->executeStatement("INSERT INTO kp_user_mandat (user_code, libelle, niveau) VALUES ('TEDIT', 'Mandat test', 4)");

        self::assertNotNull($this->login('TEDIT', 200)['user']['secondFactor']['enrollmentDueAt']);
    }

    public function testOnceTheGraceIsOverLoginReturnsAnEnrollmentTokenAndNoSession(): void
    {
        $this->enforceFrom(self::ENFORCED);
        $this->graceStartedDaysAgo('TADMIN', 8);

        $body = $this->login('TADMIN', 403);

        self::assertSame('SECOND_FACTOR_ENROLLMENT_REQUIRED', $body['code']);
        self::assertNotEmpty($body['enrollmentToken']);
        self::assertArrayNotHasKey('token', $body);
        self::assertSame([], $this->client->getResponse()->headers->getCookies(), 'aucun cookie de session');
    }

    // ------------------------------------------------------------------ AUTH-2FA-04/06 : enrôlement TOTP

    public function testTotpEnrollmentWithTheTokenOpensTheSessionAndReturnsTenRecoveryCodes(): void
    {
        $this->enforceFrom(self::ENFORCED);
        $this->graceStartedDaysAgo('TADMIN', 8);
        $token = $this->login('TADMIN', 403)['enrollmentToken'];

        $setup = $this->post('/auth/2fa/enroll/totp/setup', ['enrollmentToken' => $token], 200);
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $setup['secret']);
        self::assertStringStartsWith('otpauth://totp/KPI:TADMIN?', $setup['otpauthUri']);
        self::assertStringContainsString('<svg', $setup['qrSvg']);

        $this->post('/auth/2fa/enroll/totp/activate', ['enrollmentToken' => $token, 'code' => '000000'], 401);
        $body = $this->post('/auth/2fa/enroll/totp/activate', ['enrollmentToken' => $token, 'code' => Totp::codeAt($setup['secret'], time())], 200);

        self::assertNotEmpty($body['token']);
        self::assertCount(self::RECOVERY_CODES, $body['recoveryCodes']);
        self::assertSame('totp', $this->methodOf('TADMIN'));
        self::assertSame(self::RECOVERY_CODES, (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM kp_user_recovery_code WHERE user_code = 'TADMIN' AND used_at IS NULL",
        ));
        self::assertStringStartsWith('$2y$', (string) $this->connection->fetchOne("SELECT code_hash FROM kp_user_recovery_code WHERE user_code = 'TADMIN' LIMIT 1"));
        self::assertSame(1, $this->journalCount('TADMIN', 'Activation 2FA'));
    }

    // ------------------------------------------------------------------ AUTH-2FA-05 : enrôlement par e-mail

    public function testEmailEnrollmentRequiresAValidEmail(): void
    {
        $this->connection->executeStatement("UPDATE kp_user SET Mail = '' WHERE Code = 'TADMIN'");

        $body = $this->requestAs('TADMIN', 'POST', '/auth/2fa/email/setup', [], 400);

        self::assertSame('EMAIL_REQUIRED', $body['code']);
    }

    public function testEmailEnrollmentSendsACodeAndActivatesOnIt(): void
    {
        $this->requestAs('TADMIN', 'POST', '/auth/2fa/email/setup', []);
        $code = $this->lastEmailedCode();

        $this->requestAs('TADMIN', 'POST', '/auth/2fa/email/activate', ['code' => '000000'], 401);
        $body = $this->requestAs('TADMIN', 'POST', '/auth/2fa/email/activate', ['code' => $code]);

        self::assertCount(self::RECOVERY_CODES, $body['recoveryCodes']);
        self::assertSame('email', $this->methodOf('TADMIN'));
    }

    // ------------------------------------------------------------------ AUTH-2FA-07/09/16 : connexion TOTP

    public function testLoginWithAnActiveTotpReturnsAChallengeAndNoSession(): void
    {
        $this->activateTotp('TADMIN');

        $body = $this->login('TADMIN', 403);

        self::assertSame('SECOND_FACTOR_REQUIRED', $body['code']);
        self::assertSame('totp', $body['method']);
        self::assertNotEmpty($body['challengeToken']);
        self::assertSame([], $this->client->getResponse()->headers->getCookies());
    }

    public function testWrongPasswordGetsNoChallenge(): void
    {
        $this->activateTotp('TADMIN');

        $this->post('/auth/login', ['username' => 'TADMIN', 'password' => 'wrong'], 401);
    }

    public function testVerifyWithTheTotpCodeOpensTheSessionAndJournalsTheMethod(): void
    {
        $secret = $this->activateTotp('TADMIN');
        $challenge = $this->login('TADMIN', 403)['challengeToken'];

        $body = $this->post('/auth/2fa/verify', ['challengeToken' => $challenge, 'code' => Totp::codeAt($secret, time())], 200);

        self::assertNotEmpty($body['token']);
        self::assertSame('totp', $this->connection->fetchOne(
            "SELECT method FROM kp_user_login_history WHERE user_code = 'TADMIN' AND success = 1 ORDER BY id DESC LIMIT 1",
        ));
    }

    public function testATotpCodeCannotBeUsedTwice(): void
    {
        $secret = $this->activateTotp('TADMIN');
        $code = Totp::codeAt($secret, time());
        $this->post('/auth/2fa/verify', ['challengeToken' => $this->login('TADMIN', 403)['challengeToken'], 'code' => $code], 200);

        $this->post('/auth/2fa/verify', ['challengeToken' => $this->login('TADMIN', 403)['challengeToken'], 'code' => $code], 401);
    }

    public function testForgedChallengeTokenIsRefused(): void
    {
        $body = $this->post('/auth/2fa/verify', ['challengeToken' => 'forged.token.value.x', 'code' => '123456'], 401);

        self::assertSame('INVALID_TOKEN', $body['code']);
    }

    // ------------------------------------------------------------------ AUTH-2FA-10 : verrouillage

    public function testWrongCodesAreJournaledAndLockTheAccount(): void
    {
        $this->activateTotp('TADMIN');
        $challenge = $this->login('TADMIN', 403)['challengeToken'];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/auth/2fa/verify', ['challengeToken' => $challenge, 'code' => '000000'], 401);
        }

        $this->post('/auth/2fa/verify', ['challengeToken' => $challenge, 'code' => '000000'], 423);
        self::assertSame(5, (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM kp_user_login_history WHERE user_code = 'TADMIN' AND failure_reason = 'bad_second_factor'",
        ));
    }

    // ------------------------------------------------------------------ AUTH-2FA-08 : code par e-mail

    public function testEmailMethodSendsTheCodeAtLoginAndAcceptsItOnce(): void
    {
        $this->activateEmail('TADMIN');

        $challenge = $this->login('TADMIN', 403);
        self::assertSame('email', $challenge['method']);
        self::assertTrue($challenge['codeSent']);
        self::assertSame('a***@example.test', $challenge['maskedEmail']);
        $code = $this->lastEmailedCode();

        $this->post('/auth/2fa/verify', ['challengeToken' => $challenge['challengeToken'], 'code' => $code], 200);
        $this->post('/auth/2fa/verify', ['challengeToken' => $challenge['challengeToken'], 'code' => $code], 401);
    }

    public function testANewEmailCodeInvalidatesThePreviousOne(): void
    {
        $this->activateEmail('TADMIN');
        $challenge = $this->login('TADMIN', 403)['challengeToken'];
        $first = $this->lastEmailedCode();
        $this->connection->executeStatement("UPDATE kp_user_email_otp SET created_at = DATE_SUB(created_at, INTERVAL 2 MINUTE) WHERE user_code = 'TADMIN'");

        $this->post('/auth/2fa/email/send', ['challengeToken' => $challenge], 200);
        $second = $this->lastEmailedCode();

        if ($first !== $second) {
            $this->post('/auth/2fa/verify', ['challengeToken' => $challenge, 'code' => $first], 401);
        }
        $this->post('/auth/2fa/verify', ['challengeToken' => $challenge, 'code' => $second], 200);
    }

    public function testEmailCodeSendingIsThrottled(): void
    {
        $this->activateEmail('TADMIN');
        $challenge = $this->login('TADMIN', 403)['challengeToken'];

        $this->post('/auth/2fa/email/send', ['challengeToken' => $challenge], 429);
    }

    public function testEmailCodeExpiresAfterTenMinutes(): void
    {
        $this->activateEmail('TADMIN');
        $challenge = $this->login('TADMIN', 403)['challengeToken'];
        $code = $this->lastEmailedCode();
        $this->connection->executeStatement("UPDATE kp_user_email_otp SET expires_at = DATE_SUB(expires_at, INTERVAL 11 MINUTE) WHERE user_code = 'TADMIN'");

        $this->post('/auth/2fa/verify', ['challengeToken' => $challenge, 'code' => $code], 401);
    }

    // ------------------------------------------------------------------ AUTH-2FA-06 : codes de récupération

    public function testARecoveryCodeWorksOnce(): void
    {
        $recoveryCodes = $this->enrollTotpWithSession('TADMIN')['recoveryCodes'];

        $this->post('/auth/2fa/verify', ['challengeToken' => $this->login('TADMIN', 403)['challengeToken'], 'code' => $recoveryCodes[0]], 200);
        $this->post('/auth/2fa/verify', ['challengeToken' => $this->login('TADMIN', 403)['challengeToken'], 'code' => $recoveryCodes[0]], 401);
        self::assertSame('recovery_code', $this->connection->fetchOne(
            "SELECT method FROM kp_user_login_history WHERE user_code = 'TADMIN' AND success = 1 ORDER BY id DESC LIMIT 1",
        ));
    }

    public function testRegeneratingRecoveryCodesInvalidatesTheOldOnes(): void
    {
        ['secret' => $secret, 'recoveryCodes' => $old] = $this->enrollTotpWithSession('TADMIN');

        $new = $this->requestAs('TADMIN', 'POST', '/auth/2fa/recovery-codes', ['code' => Totp::codeAt($secret, time() + 30)])['recoveryCodes'];

        self::assertCount(self::RECOVERY_CODES, $new);
        $this->post('/auth/2fa/verify', ['challengeToken' => $this->login('TADMIN', 403)['challengeToken'], 'code' => $old[0]], 401);
    }

    // ------------------------------------------------------------------ AUTH-2FA-11 : appareil de confiance

    public function testATrustedDeviceSkipsTheChallengeUntilThePasswordChanges(): void
    {
        $secret = $this->activateTotp('TADMIN');
        $challenge = $this->login('TADMIN', 403)['challengeToken'];
        $this->post('/auth/2fa/verify', ['challengeToken' => $challenge, 'code' => Totp::codeAt($secret, time()), 'trustDevice' => true], 200);
        $trust = $this->cookieValue('kpi_2fa_trust');
        self::assertNotNull($trust);

        $this->sendCookies(['kpi_2fa_trust' => $trust]);
        $this->login('TADMIN', 200);

        $this->connection->executeStatement("INSERT INTO kp_user_token (user, token, generated_at) VALUES ('TADMIN', 'reset-2fa-test', NOW())");
        $this->post('/auth/reset-password', ['token' => 'reset-2fa-test', 'password' => self::NEW_PASSWORD], 200);
        $this->sendCookies(['kpi_2fa_trust' => $trust]);
        $this->post('/auth/login', ['username' => 'TADMIN', 'password' => self::NEW_PASSWORD], 403);
    }

    public function testATrustedDeviceIsListedAndRevocable(): void
    {
        $secret = $this->activateTotp('TADMIN');
        $challenge = $this->login('TADMIN', 403)['challengeToken'];
        $this->post('/auth/2fa/verify', ['challengeToken' => $challenge, 'code' => Totp::codeAt($secret, time()), 'trustDevice' => true], 200);
        $trust = $this->cookieValue('kpi_2fa_trust');

        $devices = $this->requestAs('TADMIN', 'GET', '/auth/2fa')['trustedDevices'];
        self::assertCount(1, $devices);
        $this->requestAs('TADMIN', 'POST', '/auth/2fa/trusted-devices/' . $devices[0]['id'] . '/revoke', []);

        $this->sendCookies(['kpi_2fa_trust' => (string) $trust]);
        $this->login('TADMIN', 403);
    }

    // ------------------------------------------------------------------ AUTH-2FA-12 : renouvellement de mot de passe

    public function testPasswordRenewalIsStillRequiredAfterTheSecondFactor(): void
    {
        $secret = $this->activateTotp('TADMIN');
        $this->connection->executeStatement("INSERT INTO kp_user_password_renewal (user_code, required_at) VALUES ('TADMIN', NOW())");

        $challenge = $this->login('TADMIN', 403);
        self::assertSame('SECOND_FACTOR_REQUIRED', $challenge['code'], 'le second facteur passe avant le renouvellement');

        $body = $this->post('/auth/2fa/verify', ['challengeToken' => $challenge['challengeToken'], 'code' => Totp::codeAt($secret, time())], 403);
        self::assertSame('PASSWORD_RENEWAL_REQUIRED', $body['code']);
        self::assertNotEmpty($body['renewalToken']);
    }

    // ------------------------------------------------------------------ AUTH-2FA-13 : activation volontaire

    public function testAVoluntaryProfile8ActivationIsThenChallenged(): void
    {
        $this->enforceFrom(self::ENFORCED);
        $this->enrollTotpWithSession('TEDIT');

        self::assertSame('SECOND_FACTOR_REQUIRED', $this->login('TEDIT', 403)['code']);
    }

    // ------------------------------------------------------------------ AUTH-2FA-14 : changement, désactivation

    public function testDisablingIsRefusedUpToProfile4AndAllowedAbove(): void
    {
        $adminSecret = $this->enrollTotpWithSession('TADMIN')['secret'];
        $editSecret = $this->enrollTotpWithSession('TEDIT')['secret'];

        $refused = $this->requestAs('TADMIN', 'POST', '/auth/2fa/disable', ['code' => Totp::codeAt($adminSecret, time() + 30)], 409);
        self::assertSame('SECOND_FACTOR_MANDATORY', $refused['code']);

        $this->requestAs('TEDIT', 'POST', '/auth/2fa/disable', ['code' => '000000'], 401);
        $this->requestAs('TEDIT', 'POST', '/auth/2fa/disable', ['code' => Totp::codeAt($editSecret, time() + 30)]);
        self::assertNull($this->methodOf('TEDIT'));
        $this->login('TEDIT', 200);
    }

    public function testChangingMethodRequiresACodeOfTheCurrentOne(): void
    {
        $secret = $this->enrollTotpWithSession('TADMIN')['secret'];

        $refused = $this->requestAs('TADMIN', 'POST', '/auth/2fa/email/setup', [], 401);
        self::assertSame('INVALID_CODE', $refused['code']);

        $this->requestAs('TADMIN', 'POST', '/auth/2fa/email/setup', ['currentCode' => Totp::codeAt($secret, time() + 30)]);
        self::assertSame('totp', $this->methodOf('TADMIN'), 'méthode inchangée tant que la nouvelle n\'est pas confirmée');
        $this->requestAs('TADMIN', 'POST', '/auth/2fa/email/activate', ['code' => $this->lastEmailedCode()]);
        self::assertSame('email', $this->methodOf('TADMIN'));
    }

    // ------------------------------------------------------------------ AUTH-2FA-15 : réinitialisation super-admin

    public function testSuperAdminResetRemovesTheFactorAndForcesImmediateEnrollment(): void
    {
        $this->enforceFrom(self::ENFORCED);
        $this->enrollTotpWithSession('TSUPER');
        $this->enrollTotpWithSession('TADMIN');

        $this->requestAs('TSUPER', 'POST', '/auth/2fa/reset/TADMIN', []);

        self::assertNull($this->methodOf('TADMIN'));
        self::assertSame(1, $this->journalCount('TADMIN', 'Réinitialisation 2FA'));
        self::assertSame('SECOND_FACTOR_ENROLLMENT_REQUIRED', $this->login('TADMIN', 403)['code'], 'pas de nouvelle grâce');
    }

    public function testResetIsReservedToASuperAdminWithASecondFactor(): void
    {
        $this->enrollTotpWithSession('TADMIN');

        $this->requestAs('TADMIN', 'POST', '/auth/2fa/reset/TADMIN', [], 403);
        $body = $this->requestAs('TSUPER', 'POST', '/auth/2fa/reset/TADMIN', [], 403);
        self::assertSame('SECOND_FACTOR_REQUIRED_FOR_RESET', $body['code']);
    }

    // ------------------------------------------------------------------ Audit (§ 5.2bis)

    public function testAuditListsTheConcernedAccountsWithTheirState(): void
    {
        $this->enforceFrom(self::ENFORCED);
        $this->enrollTotpWithSession('TSUPER');

        $tester = new CommandTester((new Application(static::$kernel))->find('app:auth:second-factor-audit'));
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        $display = $tester->getDisplay();
        self::assertStringContainsString(self::ENFORCED, $display);
        self::assertMatchesRegularExpression('/TSUPER.*totp/', $display);
        self::assertMatchesRegularExpression('/TADMIN.*aucun/', $display);
        self::assertStringNotContainsString('TEDIT', $display);
    }

    // ================================================================== helpers

    private function enforceFrom(?string $date): void
    {
        static::getContainer()->set(SecondFactorSettings::class, new SecondFactorSettings($date ?? ''));
    }

    private function graceStartedDaysAgo(string $userCode, int $days): void
    {
        $this->connection->executeStatement(
            'INSERT INTO kp_user_second_factor (user_code, enrollment_required_since) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE enrollment_required_since = VALUES(enrollment_required_since)',
            [$userCode, (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d H:i:s')],
        );
    }

    /** Active un TOTP en base, sans passer par l'API ; renvoie le secret. */
    private function activateTotp(string $userCode): string
    {
        $secret = Totp::generateSecret();
        $box = new SecondFactorSecretBox((string) ($_ENV['AUTH_2FA_ENCRYPTION_KEY'] ?? $_SERVER['AUTH_2FA_ENCRYPTION_KEY'] ?? ''));
        $this->connection->executeStatement(
            "INSERT INTO kp_user_second_factor (user_code, method, totp_secret_encrypted, activated_at) VALUES (?, 'totp', ?, NOW())",
            [$userCode, $box->encrypt($secret)],
        );

        return $secret;
    }

    private function activateEmail(string $userCode): void
    {
        $this->connection->executeStatement(
            "INSERT INTO kp_user_second_factor (user_code, method, activated_at) VALUES (?, 'email', NOW())",
            [$userCode],
        );
    }

    /** @return array{secret: string, recoveryCodes: list<string>} */
    private function enrollTotpWithSession(string $userCode): array
    {
        $setup = $this->requestAs($userCode, 'POST', '/auth/2fa/totp/setup', []);
        $body = $this->requestAs($userCode, 'POST', '/auth/2fa/totp/activate', ['code' => Totp::codeAt($setup['secret'], time())]);

        return ['secret' => $setup['secret'], 'recoveryCodes' => $body['recoveryCodes']];
    }

    private function login(string $userCode, int $expectedStatus): array
    {
        return $this->post('/auth/login', ['username' => $userCode, 'password' => self::PASSWORD], $expectedStatus);
    }

    private function methodOf(string $userCode): ?string
    {
        $method = $this->connection->fetchOne('SELECT method FROM kp_user_second_factor WHERE user_code = ?', [$userCode]);

        return is_string($method) ? $method : null;
    }

    private function journalCount(string $userCode, string $action): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM kp_journal WHERE Users = ? AND Actions = ?', [$userCode, $action]);
    }

    private function lastEmailedCode(): string
    {
        $messages = self::getMailerMessages();
        self::assertNotEmpty($messages, 'aucun e-mail envoyé');
        $last = end($messages);
        self::assertInstanceOf(Email::class, $last);
        self::assertSame(1, preg_match('/\b(\d{6})\b/', (string) $last->getTextBody(), $match));

        return $match[1];
    }

    private function cookieValue(string $name): ?string
    {
        foreach ($this->client->getResponse()->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie->getValue();
            }
        }

        return null;
    }

    private function post(string $uri, array $body, int $expectedStatus): array
    {
        $this->client->request('POST', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body, JSON_THROW_ON_ERROR));
        $response = $this->client->getResponse();
        self::assertSame($expectedStatus, $response->getStatusCode(), sprintf('POST %s : %s', $uri, substr((string) $response->getContent(), 0, 300)));

        return json_decode((string) $response->getContent(), true) ?? [];
    }
}
