<?php

namespace App\Tests\Unit\Trait;

use App\Trait\CompetitionLockTrait;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\TestCase;

/**
 * CompetitionLockTrait — règles « compétition terminée = lecture seule » et
 * « saison passée = lecture seule pour les profils > 2 ».
 *
 * Pourquoi ces tests méritent d'exister : PHPStan a déjà révélé qu'un contrôleur
 * (AdminCompetitionsController) avait OUBLIÉ `use CompetitionLockTrait;`, ce qui
 * cassait la garde « saison passée » (fatal à l'exécution). L'analyse statique
 * attrape l'absence du trait ; seuls des tests attrapent une *inversion de
 * logique* à l'intérieur. Cf. Phase 2 du journal d'exécution.
 *
 * La DB est remplacée par un double de `Connection` : on teste la LOGIQUE
 * (comparaison de saisons, filtrage des ids), pas le SQL. Les requêtes réelles
 * sont couvertes par la suite `integration`.
 */
final class CompetitionLockTraitTest extends TestCase
{
    /**
     * Classe hôte du trait. Les contrôleurs réels exposent `$this->connection` :
     * on reproduit exactement ce contrat, et on ouvre les méthodes privées.
     */
    private function host(Connection $connection): object
    {
        return new class($connection) {
            use CompetitionLockTrait;

            public function __construct(private readonly Connection $connection)
            {
            }

            public function readOnly(string $code, string $season): bool
            {
                return $this->isCompetitionReadOnly($code, $season);
            }

            /** @param int[] $ids @return int[] */
            public function editable(array $ids): array
            {
                return $this->filterEditableGamedayIds($ids);
            }

            public function pastSeason(?string $season): bool
            {
                return $this->isPastSeason($season);
            }

            public function activeSeason(): ?string
            {
                return $this->getActiveSeasonCode();
            }
        };
    }

    /** Connexion dont `fetchOne` (saison active) renvoie une valeur fixée. */
    private function connectionReturningActiveSeason(string|false $season): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchOne')->willReturn($season);

        return $connection;
    }

    /** Connexion dont `prepare()->executeQuery()` renvoie le Result fourni. */
    private function connectionReturningResult(Result $result): Connection
    {
        $statement = $this->createMock(Statement::class);
        $statement->method('executeQuery')->willReturn($result);

        $connection = $this->createMock(Connection::class);
        $connection->method('prepare')->willReturn($statement);

        return $connection;
    }

    // ----------------------------------------------------- isCompetitionReadOnly

    /**
     * Seul le statut END rend la compétition en lecture seule. Le drapeau Verrou
     * est DÉLIBÉRÉMENT ignoré ici (il ne gèle que les feuilles de présence) —
     * c'est une décision documentée dans le trait, donc à verrouiller par un test :
     * une « correction » qui ajouterait Verrou casserait l'édition inline des phases.
     */
    public function testCompetitionIsReadOnlyOnlyWhenStatusIsEnd(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')->willReturn(['Statut' => 'END']);

        $host = $this->host($this->connectionReturningResult($result));
        self::assertTrue($host->readOnly('N1H', '2026'));
    }

    public function testCompetitionIsWritableForOtherStatuses(): void
    {
        foreach (['ENC', 'PRE', 'NEW', ''] as $statut) {
            $result = $this->createMock(Result::class);
            $result->method('fetchAssociative')->willReturn(['Statut' => $statut]);

            $host = $this->host($this->connectionReturningResult($result));
            self::assertFalse(
                $host->readOnly('N1H', '2026'),
                sprintf('statut "%s" ne doit PAS être en lecture seule', $statut)
            );
        }
    }

    /**
     * Compétition introuvable → `false`, pour laisser le contrôleur appelant
     * produire SON propre 404 (documenté dans le trait). Renvoyer `true` ici
     * transformerait un « not found » en « read only », message trompeur.
     */
    public function testUnknownCompetitionIsNotReportedReadOnly(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchAssociative')->willReturn(false);

        $host = $this->host($this->connectionReturningResult($result));
        self::assertFalse($host->readOnly('INCONNU', '2026'));
    }

    // ------------------------------------------------ filterEditableGamedayIds

    /** Liste vide : court-circuit, aucune requête ne doit partir. */
    public function testEmptyGamedayListShortCircuits(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('prepare');

        self::assertSame([], $this->host($connection)->editable([]));
    }

    /**
     * Les journées appartenant à une compétition END sont retirées, les autres
     * conservées — c'est ce qui permet aux opérations en masse de « sauter »
     * silencieusement les journées gelées au lieu d'échouer en bloc.
     */
    public function testGamedaysOfEndedCompetitionsAreFilteredOut(): void
    {
        $result = $this->createMock(Result::class);
        // La requête renvoie les ids VERROUILLÉS (compétition END).
        // Volontairement en string : MySQL renvoie des chaînes, et le trait fait
        // un array_map('intval') — ce test garde ce cast.
        $result->method('fetchFirstColumn')->willReturn(['2', '4']);

        $host = $this->host($this->connectionReturningResult($result));

        self::assertSame([1, 3, 5], $host->editable([1, 2, 3, 4, 5]));
    }

    public function testAllGamedaysKeptWhenNoneIsLocked(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchFirstColumn')->willReturn([]);

        $host = $this->host($this->connectionReturningResult($result));

        self::assertSame([10, 20], $host->editable([10, 20]));
    }

    public function testAllGamedaysRemovedWhenAllAreLocked(): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchFirstColumn')->willReturn(['7', '8']);

        $host = $this->host($this->connectionReturningResult($result));

        self::assertSame([], $host->editable([7, 8]));
    }

    // ------------------------------------------------------------ isPastSeason

    /**
     * Les codes de saison sont des `char(4)` (l'année, ex. « 2026 ») et se
     * comparent comme des chaînes : "2025" < "2026". C'est la garde « profils
     * > 2 ne touchent pas aux saisons antérieures à la saison active ».
     */
    public function testStrictlyOlderSeasonIsPast(): void
    {
        $host = $this->host($this->connectionReturningActiveSeason('2026'));

        self::assertTrue($host->pastSeason('2025'));
        self::assertTrue($host->pastSeason('2019'));
    }

    /** La saison active elle-même n'est PAS passée (comparaison stricte). */
    public function testActiveSeasonIsNotPast(): void
    {
        $host = $this->host($this->connectionReturningActiveSeason('2026'));

        self::assertFalse($host->pastSeason('2026'));
    }

    /** Une saison future n'est pas passée non plus. */
    public function testFutureSeasonIsNotPast(): void
    {
        $host = $this->host($this->connectionReturningActiveSeason('2026'));

        self::assertFalse($host->pastSeason('2027'));
    }

    public function testMissingSeasonIsNotPast(): void
    {
        $host = $this->host($this->connectionReturningActiveSeason('2026'));

        self::assertFalse($host->pastSeason(null));
        self::assertFalse($host->pastSeason(''));
    }

    /**
     * Aucune saison active en base → on n'interdit RIEN. Ouvrir plutôt que
     * bloquer est le choix du trait : sans référence, on ne peut pas affirmer
     * qu'une saison est « passée », et bloquer figerait toute l'admin.
     */
    public function testNothingIsPastWhenNoActiveSeasonExists(): void
    {
        $host = $this->host($this->connectionReturningActiveSeason(false));

        self::assertNull($host->activeSeason());
        self::assertFalse($host->pastSeason('2000'));
    }
}
