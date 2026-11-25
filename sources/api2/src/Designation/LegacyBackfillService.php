<?php

namespace App\Designation;

use App\ClubRanking\OrganiserClubResolver;
use Doctrine\DBAL\Connection;

/**
 * Reprise des officiels et des clubs organisateurs saisis avant les Désignations : les colonnes legacy de kp_journee
 * (`Delegue`, `Responsable_R1`, `ChefArbitre`, `Responsable_insc`, `Arb_nj1…5`, `Rep_athletes`) deviennent des
 * Désignations, afin que la fenêtre « Officiels » et le suivi les voient (ADR-0002 : les Désignations sont la source,
 * les colonnes legacy en sont la copie). Ne touche jamais à un rôle déjà désigné.
 */
final class LegacyBackfillService
{
    private const CREATED_BY = 'backfill-legacy';
    private const MANAGER_COLUMN = 'Responsable_insc';

    public function __construct(
        private readonly Connection $connection,
        private readonly DesignationRepository $repository,
        private readonly LegacyColumnsSync $legacyColumns,
        private readonly GeneratedMandates $mandates,
    ) {
    }

    /**
     * @return array{designations: list<array<string, mixed>>, unresolved: list<array<string, mixed>>, organiserCodes: list<array{gameday: int, code: string}>}
     */
    public function run(string $season, bool $apply): array
    {
        $gamedays = $this->connection->fetchAllAssociative(
            'SELECT Id, Code_competition, Responsable_insc, Delegue, Responsable_R1, ChefArbitre, Rep_athletes, Arb_nj1, Arb_nj2, Arb_nj3, Arb_nj4, Arb_nj5
             FROM kp_journee WHERE Code_saison = ? ORDER BY Id',
            [$season],
        );
        $existing = $this->repository->ofSeason($season);
        $planned = [];
        $unresolved = [];

        foreach ($this->managersToCreate($gamedays, $existing, $unresolved) as $competition => $matric) {
            $planned[] = ['role' => 'RC', 'scope' => ScopeType::Competition, 'scopeId' => $competition, 'matric' => $matric, 'order' => 1];
        }
        foreach ($gamedays as $row) {
            $scope = $this->repository->gameday((int) $row['Id']);
            if ($scope === null) {
                continue;
            }
            $plan = LegacyBackfillPlanner::forGameday($scope, $row, EffectiveDesignations::of($scope, $existing));
            foreach ($plan->create as $item) {
                $planned[] = $item + ['scope' => ScopeType::Journee, 'scopeId' => (string) $row['Id']];
            }
            foreach ($plan->unresolved as $item) {
                $unresolved[] = $item + ['gameday' => (int) $row['Id']];
            }
        }

        $designations = [];
        foreach ($planned as $item) {
            $licence = $this->repository->licence($item['matric']);
            if ($licence === null) {
                $unresolved[] = ['role' => $item['role'], 'gameday' => $item['scopeId'], 'text' => sprintf('licence %d inconnue', $item['matric'])];
                continue;
            }
            if ($apply) {
                $this->create($season, $item, $licence['club']);
            }
            $designations[] = ['role' => $item['role'], 'scope' => $item['scope']->value, 'scopeId' => $item['scopeId'], 'matric' => $item['matric']];
        }

        return ['designations' => $designations, 'unresolved' => $unresolved, 'organiserCodes' => $this->organiserCodes($season, $apply)];
    }

    /**
     * @param list<array<string, mixed>> $gamedays
     * @param list<Designation>          $existing
     * @param list<array<string, mixed>> $unresolved rempli des compétitions dont les journées ne s'accordent pas
     *
     * @return array<string, int> code de compétition => licence du RC à désigner
     */
    private function managersToCreate(array $gamedays, array $existing, array &$unresolved): array
    {
        $texts = [];
        foreach ($gamedays as $row) {
            $texts[$row['Code_competition']][] = $row[self::MANAGER_COLUMN];
        }
        $designated = [];
        foreach ($existing as $designation) {
            if ($designation->role === 'RC' && $designation->scopeType === ScopeType::Competition) {
                $designated[$designation->scopeId] = true;
            }
        }
        $managers = [];
        foreach ($texts as $competition => $competitionTexts) {
            if (isset($designated[$competition])) {
                continue;
            }
            $manager = LegacyBackfillPlanner::competitionManager($competitionTexts);
            if ($manager['matric'] !== null) {
                $managers[(string) $competition] = $manager['matric'];
            } elseif ($manager['reason'] !== null) {
                $unresolved[] = ['role' => 'RC', 'competition' => (string) $competition, 'text' => $manager['reason']];
            }
        }

        return $managers;
    }

    /** @param array{role: string, scope: ScopeType, scopeId: string, matric: int, order: int} $item */
    private function create(string $season, array $item, ?string $club): void
    {
        if ($this->repository->exists($season, $item['role'], $item['scope'], $item['scopeId'], $item['matric'])) {
            return;
        }
        $designation = new Designation(0, $season, $item['role'], $item['scope'], $item['scopeId'], $item['matric'], $club, RoleCatalog::get($item['role'])->defaultAuthority(), $item['order']);
        $this->repository->transactional(function () use ($designation): void {
            $created = $this->repository->find($this->repository->insert($designation, self::CREATED_BY));
            if ($created === null) {
                return;
            }
            $this->legacyColumns->afterDesignationChange($created);
            $this->mandates->refresh($created->matric, $created->season, $created->role);
        });
    }

    /**
     * Journées dont le club organisateur n'est saisi que par son libellé : le code est renseigné quand le libellé
     * identifie un seul club (même règle que le classement national des clubs).
     *
     * @return list<array{gameday: int, code: string}>
     */
    private function organiserCodes(string $season, bool $apply): array
    {
        $resolver = new OrganiserClubResolver(array_column($this->connection->fetchAllAssociative('SELECT Code, Libelle FROM kp_club'), 'Libelle', 'Code'));
        $filled = [];
        foreach ($this->connection->fetchAllAssociative(
            "SELECT Id, Organisateur FROM kp_journee WHERE Code_saison = ? AND (Code_organisateur IS NULL OR Code_organisateur = '') AND Organisateur IS NOT NULL AND Organisateur <> ''",
            [$season],
        ) as $row) {
            $code = $resolver->code(null, $row['Organisateur']);
            if ($code === '') {
                continue;
            }
            if ($apply) {
                $this->connection->update('kp_journee', ['Code_organisateur' => $code], ['Id' => $row['Id']]);
            }
            $filled[] = ['gameday' => (int) $row['Id'], 'code' => $code];
        }

        return $filled;
    }
}
