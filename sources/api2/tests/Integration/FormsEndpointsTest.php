<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Formulaires d'inscription (FEATURE_FORMS.md, PAGE_CMS.md § 6) sur les fixtures : 9901 ouvert (capacité 2, liste
 * d'attente, 1 confirmée), 9902 confirmation manuelle avec paiement, 9903 clos, 9904 brouillon.
 */
final class FormsEndpointsTest extends ApiTestCase
{
    use ClockSensitiveTrait;

    /**
     * Adresse publique (DNS de Google) : soumise à la limite de débit. Pas de plage de documentation RFC 5737 :
     * depuis Symfony 7.4.21, `IpUtils::isPrivateIp()` les considère comme privées.
     */
    private const PUBLIC_IP = '8.8.4.4';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        self::mockTime('2026-10-11 10:00:00');
        $this->connection = $this->beginWrites();
    }

    public function testFrm01PublishedFormDefinitionWithItsStateAndFields(): void
    {
        $form = $this->getJson('/forms/tournoi-test');

        self::assertSame('open', $form['state']);
        self::assertSame(['capacity' => 2, 'confirmed' => 1, 'full' => false], ['capacity' => $form['capacity'], 'confirmed' => $form['confirmed'], 'full' => $form['full']]);
        self::assertSame(['email', 'phone', 'club', 'team', 'players', 'category', 'competition'], self::column($form['fields'], 'key'));
        self::assertSame([['value' => 'U18', 'label' => 'U18'], ['value' => 'SEN', 'label' => 'Senior']], $form['fields'][5]['options']);
        self::assertSame(['C001', 'C002', 'C003', 'C004'], array_slice(self::column($form['fields'][2]['options'], 'value'), 0, 4), 'clubs ayant une équipe');
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        self::assertSame('closed', $this->getJson('/forms/clos')['state']);
        self::assertSame(['error' => 'not_found'], $this->getJson('/forms/brouillon-form', 404));
        self::assertSame(['error' => 'not_found'], $this->getJson('/forms/inconnu', 404));
    }

    public function testFrm11CompetitionOptionsAreUnfinishedCompetitionsOfActiveAndNextSeason(): void
    {
        $options = self::column($this->getJson('/forms/options/competitions'), 'value');

        self::assertContains('2999/RCH', $options);
        self::assertNotContains('2998/RQL', $options, 'saison passée');
        foreach ($options as $value) {
            self::assertMatchesRegularExpression('~^(2999|3000)/~', $value);
        }
        $finished = $this->connection->fetchFirstColumn("SELECT CONCAT(Code_saison, '/', Code) FROM kp_competition WHERE Statut = 'END' AND Code_saison = '2999'");
        self::assertSame([], array_values(array_intersect($finished, $options)), 'compétitions terminées exclues');
    }

    public function testFrm02ServerValidationReturnsOneCodePerField(): void
    {
        $errors = $this->submit('tournoi-test', ['email' => 'pas-un-email', 'club' => 'NOPE', 'players' => 12, 'category' => 'XXL', 'competition' => '2998/RQL'], 422);

        self::assertSame(['email' => 'invalid', 'club' => 'invalid', 'team' => 'required', 'players' => 'max', 'category' => 'invalid', 'competition' => 'invalid'], $errors['errors']);
    }

    public function testFrm03And04ConfirmedThenWaitingListWithMails(): void
    {
        $first = $this->submit('tournoi-test', $this->validAnswers('Bravo'));
        self::assertSame(['status' => 'confirmed', 'reason' => null, 'payment_url' => null], $first);
        self::assertEmailCount(2, message: 'déclarant + organisateur');
        $mail = self::getMailerMessage(0);
        self::assertEmailHeaderSame($mail, 'To', 'b@example.test');
        self::assertEmailTextBodyContains($mail, 'Votre inscription est confirmée.');
        self::assertEmailTextBodyContains($mail, 'Club : Club Alpha Lacville', 'libellé du club, pas son code');

        $second = $this->submit('tournoi-test', $this->validAnswers('Charlie'));
        self::assertSame(['status' => 'waiting', 'reason' => 'full', 'payment_url' => null], $second, 'capacité atteinte : liste d\'attente');
        self::assertSame(4, (int) $this->connection->fetchOne('SELECT COUNT(*) FROM kp_mail_log WHERE form_id = 9901'));
    }

    public function testFrm03ManualConfirmationWaitsForTheOrganiserAndGivesThePaymentLink(): void
    {
        $result = $this->submit('stage-paiement', ['email' => 'p@example.test', 'team' => 'Delta']);

        self::assertSame(['status' => 'waiting', 'reason' => 'manual', 'payment_url' => 'https://www.helloasso.com/test'], $result);
        self::assertEmailTextBodyContains(self::getMailerMessage(0), 'Paiement : https://www.helloasso.com/test');
    }

    public function testFrm03FullFormWithoutWaitingListRefuses(): void
    {
        $this->connection->executeStatement('UPDATE kp_form SET capacity = 1, waiting_list = 0 WHERE id = 9901');

        self::assertSame(['error' => 'full'], $this->submit('tournoi-test', $this->validAnswers('Echo'), 409));
    }

    public function testFrm01ClosedFormRefusesSubmissions(): void
    {
        $token = $this->getJson('/forms/clos')['token'];
        self::mockTime('+10 seconds');

        $this->client->request('POST', '/forms/clos/submissions', content: (string) json_encode(['_token' => $token, 'email' => 'x@example.test']));
        self::assertSame(409, $this->client->getResponse()->getStatusCode());
    }

    public function testFrm05HoneypotAndTooFastSubmissionsAreRejected(): void
    {
        self::assertSame(['error' => 'rejected'], $this->submit('tournoi-test', $this->validAnswers('Robot') + ['website' => 'http://spam.example'], 400));

        $token = $this->getJson('/forms/tournoi-test')['token'];
        $this->client->request('POST', '/forms/tournoi-test/submissions', content: (string) json_encode(['_token' => $token] + $this->validAnswers('Pressé')));
        self::assertSame(['error' => 'too_fast'], json_decode((string) $this->client->getResponse()->getContent(), true));

        $this->client->request('POST', '/forms/tournoi-test/submissions', content: (string) json_encode(['_token' => '123.forged'] + $this->validAnswers('Faux')));
        self::assertSame(['error' => 'invalid'], json_decode((string) $this->client->getResponse()->getContent(), true));
    }

    public function testFrm05SubmissionsAreRateLimitedPerPublicIp(): void
    {
        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $this->client->request('POST', '/forms/tournoi-test/submissions', server: ['REMOTE_ADDR' => self::PUBLIC_IP], content: '{}');
        }

        self::assertSame(429, $this->client->getResponse()->getStatusCode());
    }

    public function testFrm06PublicListShowsPublishedColumnsOnlyConfirmedThenWaiting(): void
    {
        $this->submit('tournoi-test', $this->validAnswers('Bravo'));
        $this->submit('tournoi-test', $this->validAnswers('Charlie'));

        $list = $this->getJson('/forms/tournoi-test/entries');
        self::assertSame(['Club', 'Équipe'], $list['columns']);
        self::assertSame([['Club Alpha Lacville', 'Alpha'], ['Club Alpha Lacville', 'Bravo']], $list['confirmed']);
        self::assertSame([['Club Alpha Lacville', 'Charlie']], $list['waiting']);
        self::assertStringNotContainsString('@', (string) json_encode($list), 'jamais d\'e-mail');
        self::assertStringNotContainsString('Annulée', (string) json_encode($list), 'annulées exclues');
        self::assertSame(['error' => 'not_found'], $this->getJson('/forms/clos/entries', 404), 'liste non publique');
    }

    public function testFrm10FormsAttachedToAnEvent(): void
    {
        self::assertSame([['slug' => 'tournoi-test', 'title' => 'Tournoi test', 'state' => 'open']], $this->getJson('/forms?event=77'));
        self::assertSame(['error' => 'invalid_parameter'], $this->getJson('/forms', 400));
    }

    public function testAdmCms06ConfirmingAWaitingEntrySendsTheConfirmationMail(): void
    {
        $this->requestAs('TVIEW', 'GET', '/admin/cms/forms', expectedStatus: 403);
        $entries = $this->requestAs('TEDIT', 'PUT', '/admin/cms/forms/9902/entries/9953', ['status' => 'confirmed']);

        self::assertSame('confirmed', $entries['entries'][0]['status']);
        self::assertEmailCount(1);
        self::assertEmailTextBodyContains(self::getMailerMessage(0), 'Votre inscription est confirmée.');
    }

    public function testAdmCms07ExportHasOneLinePerEntryAndOneColumnPerField(): void
    {
        $this->client->request('GET', '/admin/cms/forms/9901/export', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TEDIT')]);
        // BrowserKit captures the streamed body in its internal response.
        $csv = $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));

        $lines = array_values(array_filter(explode("\n", ltrim($csv, "\xEF\xBB\xBF"))));
        self::assertCount(3, $lines, 'en-tête + 2 inscriptions');
        self::assertSame('N°;Date;Statut;E-mail;Téléphone;Club;Équipe;Joueurs;Catégorie;"Compétition visée"', $lines[0]);
    }

    public function testFormDefinitionIsValidated(): void
    {
        $errors = $this->requestAs('TEDIT', 'POST', '/admin/cms/forms', [
            'title_fr' => 'Stage',
            'status' => 'published',
            'fields' => [
                ['key' => 'phone', 'type' => 'phone', 'label_fr' => 'Téléphone'],
                ['key' => 'phone', 'type' => 'select', 'label_fr' => 'Choix'],
                ['key' => 'mail', 'type' => 'email', 'label_fr' => 'E-mail'],
            ],
            'public_columns' => ['mail'],
            'payment_url' => 'http://insecure.example',
        ], 400)['errors'];

        self::assertSame([
            'fields.1.key' => 'invalid',
            'fields.1.options' => 'required',
            'fields' => 'email_required',
            'payment_url' => 'invalid',
            'public_columns' => 'personal',
        ], $errors);

        $created = $this->requestAs('TEDIT', 'POST', '/admin/cms/forms', [
            'title_fr' => 'Stage arbitrage',
            'status' => 'published',
            'fields' => [['key' => 'email', 'type' => 'email', 'label_fr' => 'E-mail', 'required' => true]],
            'closes_at' => '2026-12-01',
        ], 201);
        self::assertSame('stage-arbitrage', $created['slug']);
        self::assertSame('2026-12-01 23:59:59', $created['closes_at'], 'date seule : jusqu\'à la fin de la journée');
    }

    public function testFrm09PurgeRemovesExpiredEntriesAndKeepsTheirCount(): void
    {
        $command = new \Symfony\Component\Console\Tester\CommandTester((new \Symfony\Bundle\FrameworkBundle\Console\Application(static::$kernel))->find('app:forms:purge'));
        $command->execute([]);

        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM kp_form_entry WHERE id = 9954'), 'formulaire clos en 2020, 30 jours de conservation');
        self::assertSame(1, (int) $this->connection->fetchOne('SELECT archived_entries FROM kp_form WHERE id = 9903'));
        self::assertNotFalse($this->connection->fetchOne('SELECT 1 FROM kp_form_entry WHERE id = 9951'), 'formulaire encore ouvert');
    }

    /** @return array<string, mixed> */
    private function validAnswers(string $team): array
    {
        return ['email' => strtolower($team[0]) . '@example.test', 'club' => 'C001', 'team' => $team, 'players' => 7, 'category' => 'SEN', 'competition' => '2999/RCH'];
    }

    /**
     * Affiche le formulaire, attend 10 s (horloge simulée) puis envoie les réponses.
     *
     * @param array<string, mixed> $answers
     *
     * @return array<mixed>
     */
    private function submit(string $slug, array $answers, int $expectedStatus = 201): array
    {
        $token = $this->getJson('/forms/' . $slug)['token'];
        self::mockTime('+10 seconds');
        $this->client->request('POST', "/forms/$slug/submissions", server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode(['_token' => $token] + $answers));
        $response = $this->client->getResponse();
        self::assertSame($expectedStatus, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true);
    }
}
