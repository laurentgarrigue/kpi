<?php

namespace App\Tests\Unit\Security;

use App\Security\LoginAttemptLogger;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests unitaires du journal de tentatives de login
 * ({@see AUTH_HARDENING.md § 2.2 — AUTH-JL-01, AUTH-JL-02, AUTH-LK-01/03/04}).
 *
 * On vérifie la forme des INSERT et le calcul du verrouillage à partir des échecs
 * récents : la DB est mockée, le but n'est pas de tester MariaDB.
 */
final class LoginAttemptLoggerTest extends TestCase
{
    private Connection $connection;
    private LoginAttemptLogger $logger;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->logger = new LoginAttemptLogger($this->connection);
    }

    public function testLogSuccessWritesSuccessRowWithoutFailureReason(): void
    {
        $this->connection->expects(self::once())
            ->method('insert')
            ->with(
                'kp_user_login_history',
                self::callback(function (array $row): bool {
                    return $row['user_code'] === 'TADMIN'
                        && $row['success'] === 1
                        && $row['method'] === 'password'
                        && $row['failure_reason'] === null
                        && $row['legacy_hash'] === 0
                        && $row['ip'] === '203.0.113.5'
                        && $row['user_agent'] === 'Mozilla/5.0 (Linux)'
                        && isset($row['attempted_at']);
                }),
            );

        $this->logger->logSuccess('TADMIN', 'password', false, $this->request('203.0.113.5', 'Mozilla/5.0 (Linux)'));
    }

    public function testLogSuccessMarksLegacyHashWhenRequested(): void
    {
        $this->connection->expects(self::once())
            ->method('insert')
            ->with('kp_user_login_history', self::callback(fn(array $row): bool => $row['legacy_hash'] === 1));

        $this->logger->logSuccess('TADMIN', 'password', true, $this->request('1.2.3.4', 'UA'));
    }

    public function testLogFailureAcceptsAllFourReasons(): void
    {
        $this->connection->expects(self::exactly(4))
            ->method('insert')
            ->with('kp_user_login_history', self::callback(fn(array $row): bool => $row['success'] === 0));

        $this->logger->logFailure('UNKNOWN', LoginAttemptLogger::REASON_UNKNOWN_USER, $this->request('1.1.1.1', 'UA'));
        $this->logger->logFailure('TADMIN', LoginAttemptLogger::REASON_BAD_PASSWORD, $this->request('1.1.1.1', 'UA'));
        $this->logger->logFailure('TADMIN', LoginAttemptLogger::REASON_RATE_LIMITED, $this->request('1.1.1.1', 'UA'));
        $this->logger->logFailure('TADMIN', LoginAttemptLogger::REASON_ACCOUNT_LOCKED, $this->request('1.1.1.1', 'UA'));
    }

    public function testLogFailureAcceptsNullUserCodeForUnknownUser(): void
    {
        $this->connection->expects(self::once())
            ->method('insert')
            ->with('kp_user_login_history', self::callback(fn(array $row): bool => $row['user_code'] === null));

        $this->logger->logFailure(null, LoginAttemptLogger::REASON_UNKNOWN_USER, $this->request('1.1.1.1', 'UA'));
    }

    public function testLogFailureTruncatesUserAgentTo255Chars(): void
    {
        $longUa = str_repeat('A', 500);
        $this->connection->expects(self::once())
            ->method('insert')
            ->with('kp_user_login_history', self::callback(fn(array $row): bool => strlen($row['user_agent']) === 255));

        $this->logger->logFailure('TADMIN', LoginAttemptLogger::REASON_BAD_PASSWORD, $this->request('1.1.1.1', $longUa));
    }

    public function testLockLiftsFifteenMinutesAfterTheFifthMostRecentFailure(): void
    {
        $this->mockFifthMostRecentFailure('TADMIN', (new \DateTimeImmutable('-10 minutes'))->format('Y-m-d H:i:s'));

        $secondsLeft = $this->logger->lockSecondsLeft('TADMIN');

        self::assertNotNull($secondsLeft);
        self::assertGreaterThan(5 * 60 - 10, $secondsLeft);
        self::assertLessThanOrEqual(5 * 60, $secondsLeft);
    }

    public function testNoLockBelowThreshold(): void
    {
        $this->mockFifthMostRecentFailure('TADMIN', false);

        self::assertNull($this->logger->lockSecondsLeft('TADMIN'));
    }

    public function testOnlyAuthenticationFailuresCountTowardsTheLock(): void
    {
        $this->connection->expects(self::once())
            ->method('fetchOne')
            ->with(
                self::stringContains('failure_reason IN'),
                self::callback(fn (array $params): bool => in_array(LoginAttemptLogger::REASON_BAD_PASSWORD, $params, true)
                    && in_array(LoginAttemptLogger::REASON_BAD_SECOND_FACTOR, $params, true)
                    && !in_array(LoginAttemptLogger::REASON_RATE_LIMITED, $params, true)
                    && !in_array(LoginAttemptLogger::REASON_ACCOUNT_LOCKED, $params, true)),
            )
            ->willReturn(false);

        $this->logger->lockSecondsLeft('TADMIN');
    }

    /** @param string|false $attemptedAt `attempted_at` of the 5th most recent counted failure, false if there are fewer */
    private function mockFifthMostRecentFailure(string $code, string|false $attemptedAt): void
    {
        $this->connection->method('fetchOne')
            ->with(
                self::stringContains('kp_user_login_history'),
                self::callback(fn(array $params): bool => in_array($code, $params, true)),
            )
            ->willReturn($attemptedAt);
    }

    private function request(string $ip, string $userAgent): Request
    {
        $request = new Request();
        $request->server->set('REMOTE_ADDR', $ip);
        $request->headers->set('User-Agent', $userAgent);

        return $request;
    }
}
