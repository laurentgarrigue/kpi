<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mime\Email;

/**
 * Synchronisation des suspensions (FEATURE_CARD_SUSPENSIONS.md SUS-11 à SUS-17). Fixtures « cumuls de cartons »,
 * saison 2996 : le joueur 9961 prend un jaune au match 9961 et un jaune puis un noir au match 9962, même journée
 * (événement 9961, délégué CNA 9964) ; ses cartons du match 9964 (non validé) et du match 9965 (section Tournoi) ne
 * comptent pas. Destinataires : RC 9962 (rc-sus@) et bureau CNA 9963 (bureau-sus@). Le joueur est capitaine (C) de l'équipe 9961,
 * avec 9965.
 */
final class CardSuspensionsSyncTest extends ApiTestCase
{
    private const SEASON = '2996';
    private const RECIPIENTS = ['bureau-sus@example.test', 'rc-sus@example.test'];

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testSus11And12OnlyValidatedGamesOfACoveredSectionCountAndEachSuspensionIsNotifiedOnce(): void
    {
        self::assertStringContainsString('2 detected, 2 notified, 0 cancelled', $this->sync());

        $rows = $this->suspensions();
        self::assertSame(['sus-9962-01', 'sus-9962-02'], array_column($rows, 'carton_id'));
        self::assertSame(['YELLOW_RED_GAMEDAY', 'BLACK_GAME'], array_column($rows, 'regle'));
        self::assertSame([1, 2], array_map('intval', array_column($rows, 'matchs')));
        self::assertNotNull($rows[0]['notifiee_le']);
        self::assertEqualsCanonicalizing([9962, 9963], json_decode((string) $rows[0]['destinataires'], true));

        $messages = $this->mails();
        self::assertCount(4, $messages);
        self::assertEqualsCanonicalizing([...self::RECIPIENTS, ...self::RECIPIENTS], array_map(static fn (Email $m): string => $m->getTo()[0]->getAddress(), $messages));
        self::assertSame('KPI — Suspension : SUSPENDU Joueur (9961)', $messages[0]->getSubject());
        self::assertStringContainsString('1 match (le match suivant)', (string) $messages[0]->getTextBody());

        self::assertStringContainsString('0 detected, 0 notified, 0 cancelled', $this->sync());
        self::assertCount(4, $this->mails());
    }

    public function testSus13ASuspensionIsCancelledWhenTheGameIsNoLongerValidated(): void
    {
        $this->sync();
        $this->connection->update('kp_match', ['Validation' => ''], ['Id' => 9962]);

        self::assertStringContainsString('0 detected, 0 notified, 2 cancelled', $this->sync());

        $rows = $this->suspensions();
        self::assertSame([null, null], array_column($rows, 'actif'));
        self::assertNotNull($rows[0]['annulee_le']);
        $cancellations = array_values(array_filter($this->mails(), static fn (Email $m): bool => str_starts_with((string) $m->getSubject(), 'KPI — Suspension annulée')));
        self::assertCount(4, $cancellations);
    }

    public function testSus14ABlackCardOpensAnAppealJuryDraftOnTheEventForTheDelegate(): void
    {
        $this->sync();

        $black = $this->suspensions()[1];
        self::assertSame(1, (int) $black['jury_appel']);
        $report = $this->connection->fetchAssociative('SELECT * FROM kp_report WHERE id = ?', [$black['rapport_id']]);
        self::assertIsArray($report);
        self::assertSame(['JURY_APPEL', 'EVENEMENT', '9961', 'BROUILLON', 9964, 'KPI'], [$report['type'], $report['perimetre_type'], $report['perimetre_id'], $report['etat'], (int) $report['auteur_matric'], $report['saisi_par']]);
        $answers = json_decode((string) $report['reponses'], true);
        self::assertStringContainsString('SUSPENDU Joueur', $answers['reason']);
        self::assertSame('2', $answers['matches'][0]['number']);
        self::assertSame(9964, $answers['jury'][0]['matric']);

        $mail = array_values(array_filter($this->mails(), static fn (Email $m): bool => str_contains((string) $m->getTextBody(), "jury d'appel a été ouvert en brouillon")))[0];
        self::assertStringContainsString('/reports/' . $black['rapport_id'], (string) $mail->getTextBody());
        // le délégué reprend le brouillon
        $this->requestAs('9964', 'GET', '/admin/reports/' . $black['rapport_id']);
    }

    public function testSus15ASilentSyncRecordsWithoutNotifyingAndNothingIsSentLater(): void
    {
        self::assertStringContainsString('2 detected, 0 notified', $this->sync(silent: true));

        self::assertSame([null, null], array_column($this->suspensions(), 'notifiee_le'));
        self::assertSame([null, null], array_column($this->suspensions(), 'rapport_id'));
        self::assertStringContainsString('0 detected, 0 notified', $this->sync());
        self::assertCount(0, $this->mails());
    }

    public function testSus16WithoutAnyDesignatedRecipientTheAdminAddressIsNotified(): void
    {
        $this->connection->executeStatement("DELETE FROM kp_designation WHERE saison = ? AND role IN ('RC', 'BUREAU_CNA')", [self::SEASON]);

        $this->sync();

        $messages = $this->mails();
        self::assertCount(2, $messages);
        foreach ($messages as $message) {
            self::assertNotContains($message->getTo()[0]->getAddress(), self::RECIPIENTS);
        }
    }

    public function testSus17OnlyProfilesOneAndTwoListTheSuspensions(): void
    {
        $this->sync();

        $list = $this->requestAs('TADMIN', 'GET', '/admin/card-suspensions?season=' . self::SEASON);
        self::assertSame(1, $list['catalog']['version']);
        self::assertCount(6, $list['catalog']['rules']);
        self::assertSame(['BLACK_GAME', 'YELLOW_RED_GAMEDAY'], array_column($list['suspensions'], 'rule'));
        self::assertSame('SUSPENDU Joueur', $list['suspensions'][0]['name']);
        self::assertSame('ACTIVE', $list['suspensions'][0]['state']);
        self::assertSame([2, null], array_column($list['suspensions'], 'gamedayTotal'));
        $this->requestAs('TDIV', 'GET', '/admin/card-suspensions?season=' . self::SEASON, expectedStatus: 403);
        $this->requestAs('TADMIN', 'GET', '/admin/card-suspensions?season=abc', expectedStatus: 400);
    }

    public function testSus19TheSuspendedStatusIsSetOnTheTeamOfTheCardAndKeepsTheStatusBefore(): void
    {
        $this->sync();

        self::assertSame('S', $this->statusOf(9961));
        self::assertSame('-', $this->statusOf(9965));
        $rows = $this->suspensions();
        self::assertSame([9961, 9961], array_map('intval', array_column($rows, 'equipe_id')));
        self::assertSame(['C', 'C'], array_column($rows, 'statut_avant'));
    }

    public function testSus19ASilentSyncLeavesTheStatusAlone(): void
    {
        $this->sync(silent: true);

        self::assertSame('C', $this->statusOf(9961));
    }

    public function testSus22And23LiftingTheLastSuspensionGivesBackTheStatusBefore(): void
    {
        $this->sync();
        [$yellow, $black] = array_map(static fn (array $row): int => (int) $row['id'], $this->suspensions());

        $this->requestAs('TDIV', 'POST', "/admin/card-suspensions/$yellow/lift", expectedStatus: 403);
        $lifted = $this->requestAs('TADMIN', 'POST', "/admin/card-suspensions/$yellow/lift");
        self::assertSame(['LIFTED', 'TADMIN'], [$lifted['state'], $lifted['liftedBy']]);
        self::assertSame('S', $this->statusOf(9961)); // retenu par la suspension du carton noir

        $this->requestAs('TADMIN', 'POST', "/admin/card-suspensions/$black/lift");
        self::assertSame('C', $this->statusOf(9961));
        $this->requestAs('TADMIN', 'POST', "/admin/card-suspensions/$black/lift", expectedStatus: 409);
        self::assertStringContainsString('0 detected, 0 notified, 0 cancelled', $this->sync());
        self::assertSame('C', $this->statusOf(9961));
    }

    public function testSus24ACancelledSuspensionGivesBackTheStatusBefore(): void
    {
        $this->sync();
        $this->connection->update('kp_match', ['Validation' => ''], ['Id' => 9962]);

        $this->sync();

        self::assertSame('C', $this->statusOf(9961));
        $this->requestAs('TADMIN', 'POST', '/admin/card-suspensions/' . $this->suspensions()[0]['id'] . '/lift', expectedStatus: 409);
    }

    public function testSus20ASuspendedPlayerIsNeitherInitialisedNorOfferedOnAMatchSheet(): void
    {
        $this->sync();

        $available = $this->requestAs('TADMIN', 'GET', '/admin/matches/9964/available-players?teamCode=B');
        self::assertSame([9965], array_column($available['players'], 'matric'));
        $this->requestAs('TADMIN', 'POST', '/admin/matches/9964/players/initialize', ['teamCode' => 'B']);
        self::assertSame([9965], array_map('intval', $this->connection->fetchFirstColumn("SELECT Matric FROM kp_match_joueur WHERE Id_match = 9964 AND Equipe = 'B'")));
    }

    public function testSus21OnlyProfilesOneAndTwoGiveOrRemoveTheSuspendedStatus(): void
    {
        $this->requestAs('9962', 'PATCH', '/admin/teams/9961/players/9965', ['capitaine' => 'S'], 403);
        $this->requestAs('TADMIN', 'PATCH', '/admin/teams/9961/players/9965', ['capitaine' => 'S']);
        self::assertSame('S', $this->statusOf(9965));
        $this->requestAs('9962', 'PATCH', '/admin/teams/9961/players/9965', ['capitaine' => '-'], 403);
        $this->requestAs('9962', 'PATCH', '/admin/teams/9961/players/9965', ['numero' => 8]);
        $this->requestAs('TADMIN', 'PATCH', '/admin/teams/9961/players/9965', ['capitaine' => '-', 'force' => true]); // joueur de test non en règle : forcé
        self::assertSame('-', $this->statusOf(9965));
    }

    private function statusOf(int $matric): string
    {
        return (string) $this->connection->fetchOne('SELECT Capitaine FROM kp_competition_equipe_joueur WHERE Id_equipe = 9961 AND Matric = ?', [$matric]);
    }

    private function sync(bool $silent = false): string
    {
        $tester = new CommandTester((new Application(static::$kernel))->find('app:card-suspensions:sync'));
        $tester->execute(['--season' => self::SEASON] + ($silent ? ['--silent' => true] : []));
        $tester->assertCommandIsSuccessful();

        return $tester->getDisplay();
    }

    /** @return list<array<string, mixed>> */
    private function suspensions(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_card_suspension WHERE saison = ? ORDER BY id', [self::SEASON]);
    }

    /** @return list<Email> */
    private function mails(): array
    {
        return array_values(array_filter(self::getMailerMessages(), static fn ($m): bool => $m instanceof Email));
    }
}
