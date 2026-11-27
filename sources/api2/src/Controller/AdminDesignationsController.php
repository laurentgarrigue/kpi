<?php

namespace App\Controller;

use App\Designation\Authority;
use App\Designation\CoverageService;
use App\Designation\Facts\FactsAggregator;
use App\Designation\Facts\FactsCollector;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Designation\Designation;
use App\Designation\DesignationRejected;
use App\Designation\DesignationRepository;
use App\Designation\DesignationService;
use App\Designation\RoleCatalog;
use App\Designation\RoleDefinition;
use App\Designation\ScopeType;
use App\Entity\User;
use App\Trait\AdminLoggableTrait;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Désignations des officiels et responsables (FEATURE_DESIGNATIONS.md § 8.3, ticket designations/03). Les droits
 * d'écriture sont ceux du catalogue des rôles (profils 1–2, délégué CNA pour le jury d'appel de son périmètre) :
 * ils sont vérifiés par DesignationService, pas par un rôle Symfony.
 */
#[Route('/admin/designations')]
#[OA\Tag(name: 'Admin - Désignations')]
final class AdminDesignationsController extends AbstractController
{
    use AdminLoggableTrait;

    private const SEASON_PATTERN = '/^\d{4}$/';
    private const SCOPE_ID_PATTERN = '/^[A-Za-z0-9_-]{1,20}$/';
    private const MAX_ORDER = 99;
    private const READER_MAX_PROFILE = 5;
    private const STATEMENT_MAX_PROFILE = 2;
    private const MAX_QUALITY_LENGTH = 100;
    /** Taille de kp_user.Mail. */
    private const MAX_EMAIL_LENGTH = 100;

    public function __construct(
        private readonly DesignationService $service,
        private readonly DesignationRepository $repository,
        private readonly Connection $connection,
        private readonly CoverageService $coverage,
        private readonly FactsCollector $facts,
    ) {
    }

    /**
     * Relevé des faits pour les rétributions et pénalités (D19, § 6 ; REL-01 à REL-05) : quantités par club, sources ;
     * `format=csv|xlsx` pour l'export. Profils 1 et 2.
     */
    #[Route('/facts', name: 'admin_designations_facts', methods: ['GET'])]
    #[OA\Get(path: '/admin/designations/facts', summary: 'Relevé des faits par club (quantités, sans montant), JSON, CSV ou Excel')]
    public function facts(Request $request): Response
    {
        $season = (string) $request->query->get('season', '');
        $format = (string) $request->query->get('format', 'json');
        if (!preg_match(self::SEASON_PATTERN, $season) || !in_array($format, ['json', 'csv', 'xlsx'], true)) {
            return $this->error(DesignationRejected::INVALID, Response::HTTP_BAD_REQUEST);
        }
        if (!$this->isStatementReader()) {
            return $this->error(DesignationRejected::FORBIDDEN, Response::HTTP_FORBIDDEN);
        }
        $collected = $this->facts->collect($season);
        $rows = FactsAggregator::rows($collected['facts'], $collected['clubLabels']);
        if ($format === 'json') {
            return $this->json(['season' => $season, 'items' => $rows]);
        }

        $table = [['Club', 'Libellé du club', 'Fait', 'Précision', 'Quantité', 'Sources']];
        foreach ($rows as $row) {
            $table[] = [
                $row['club'] ?? '',
                $row['clubLabel'],
                $row['fact'],
                $row['qualifier'],
                $row['quantity'],
                implode(' ; ', array_map(static fn (array $d): string => sprintf('%s (%s %s)', $d['label'], $d['sourceType'], $d['sourceId']), $row['details'])),
            ];
        }
        return $this->download($table, $format, sprintf('releve-des-faits-%s', $season));
    }

    /** Suivi de couverture (SUI-01 à SUI-05), réservé à ceux qui désignent : profils 1 et 2. */
    #[Route('/coverage', name: 'admin_designations_coverage', methods: ['GET'])]
    #[OA\Get(path: '/admin/designations/coverage', summary: 'Matrice de couverture : compétitions, journées, équipes × rôles, avec état de chaque case')]
    public function coverage(Request $request): JsonResponse
    {
        $season = (string) $request->query->get('season', '');
        $level = (string) $request->query->get('level', CoverageService::LEVEL_ALL);
        if (!preg_match(self::SEASON_PATTERN, $season)
            || !in_array($level, [CoverageService::LEVEL_ALL, CoverageService::LEVEL_NATIONAL, CoverageService::LEVEL_OTHER], true)) {
            return $this->error(DesignationRejected::INVALID, Response::HTTP_BAD_REQUEST);
        }
        if (!$this->isDesignator()) {
            return $this->error(DesignationRejected::FORBIDDEN, Response::HTTP_FORBIDDEN);
        }
        $competitions = array_values(array_filter(array_map('trim', explode(',', (string) $request->query->get('competitions', '')))));

        $user = $this->currentUser();
        // le mandat limité à des saisons ou à des compétitions borne ce que le suivi montre
        $allowedSeasons = $user->getAllowedSeasons();
        if ($allowedSeasons !== null && !in_array($season, $allowedSeasons, true)) {
            return $this->error(DesignationRejected::FORBIDDEN, Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->coverage->matrix($season, $level, $competitions, $request->query->getBoolean('onlyGaps'), $user->getAllowedCompetitions()));
    }

    /**
     * Le licencié a-t-il déjà un compte KPI (code = licence) ? La fenêtre « Désigner » ne demande l'email que sinon (D5).
     */
    #[Route('/account', name: 'admin_designations_account', methods: ['GET'])]
    #[OA\Get(path: '/admin/designations/account', summary: 'Le licencié a-t-il un compte KPI (et un email) ?')]
    public function account(Request $request): JsonResponse
    {
        $matric = $request->query->getInt('matric');
        if ($matric <= 0) {
            return $this->error(DesignationRejected::INVALID, Response::HTTP_BAD_REQUEST);
        }
        if (!$this->canRead()) {
            return $this->error(DesignationRejected::FORBIDDEN, Response::HTTP_FORBIDDEN);
        }
        $mail = $this->connection->fetchOne('SELECT Mail FROM kp_user WHERE Code = ?', [(string) $matric]);

        return $this->json(['exists' => $mail !== false, 'hasEmail' => is_string($mail) && $mail !== '']);
    }

    /** Désignations effectives d'une journée, héritage compris (bloc « Désignations » d'app4, ticket 08). */
    #[Route('/effective', name: 'admin_designations_effective', methods: ['GET'])]
    #[OA\Get(path: '/admin/designations/effective', summary: "Titulaires effectifs de chaque rôle d'une journée, avec leur provenance")]
    public function effective(Request $request): JsonResponse
    {
        $gamedayId = $request->query->getInt('gameday');
        if ($gamedayId <= 0) {
            return $this->error(DesignationRejected::INVALID, Response::HTTP_BAD_REQUEST);
        }
        if (!$this->canRead()) {
            return $this->error(DesignationRejected::FORBIDDEN, Response::HTTP_FORBIDDEN);
        }
        $gameday = $this->coverage->gameday($gamedayId);

        return $gameday === null ? $this->error(DesignationRejected::NOT_FOUND, Response::HTTP_NOT_FOUND) : $this->json($gameday);
    }

    /**
     * Tableau en téléchargement CSV (séparateur « ; », BOM pour Excel) ou Excel.
     *
     * @param list<list<scalar|null>> $table
     */
    private function download(array $table, string $format, string $basename): Response
    {
        $xlsx = $format === 'xlsx';
        $response = new StreamedResponse(static function () use ($table, $xlsx): void {
            if ($xlsx) {
                $spreadsheet = new Spreadsheet();
                $spreadsheet->getActiveSheet()->fromArray($table, null, 'A1', true);
                (new Xlsx($spreadsheet))->save('php://output');

                return;
            }
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF"); // BOM : accents corrects dans Excel
            foreach ($table as $line) {
                fputcsv($output, $line, ';', '"', '');
            }
            fclose($output);
        });
        $response->headers->set('Content-Type', $xlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, sprintf('%s.%s', $basename, $format)));

        return $response;
    }

    /** Récapitulatif par personne et par club (SUI-06), profils 1 et 2. */
    #[Route('/summary', name: 'admin_designations_summary', methods: ['GET'])]
    #[OA\Get(path: '/admin/designations/summary', summary: 'Désignations par rôle et journées couvertes, par personne et par club (période facultative)')]
    public function summary(Request $request): Response
    {
        $season = (string) $request->query->get('season', '');
        $from = $request->query->get('from');
        $to = $request->query->get('to');
        $format = (string) $request->query->get('format', 'json');
        $section = (string) $request->query->get('section', 'people');
        $datePattern = '/^\d{4}-\d{2}-\d{2}$/';
        if (!preg_match(self::SEASON_PATTERN, $season)
            || ($from !== null && !preg_match($datePattern, (string) $from))
            || ($to !== null && !preg_match($datePattern, (string) $to))
            || !in_array($format, ['json', 'csv', 'xlsx'], true) || !in_array($section, ['people', 'clubs'], true)) {
            return $this->error(DesignationRejected::INVALID, Response::HTTP_BAD_REQUEST);
        }
        if (!$this->isStatementReader()) {
            return $this->error(DesignationRejected::FORBIDDEN, Response::HTTP_FORBIDDEN);
        }

        $summary = $this->coverage->summary($season, $from, $to);
        if ($format === 'json') {
            return $this->json($summary);
        }

        return $this->download(self::summaryTable($summary[$section], $section === 'people'), $format, sprintf('recapitulatif-%s-%s', $section, $season));
    }

    /**
     * Une ligne par personne ou par club ; pour chaque rôle, le nombre de Désignations et de journées couvertes.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<list<scalar|null>>
     */
    private static function summaryTable(array $rows, bool $people): array
    {
        $roles = RoleCatalog::all();
        $header = $people ? ['Personne', 'Club', 'Libellé du club'] : ['Club', 'Libellé du club', 'Personnes'];
        foreach ($roles as $role) {
            array_push($header, $role->label . ' (désignations)', $role->label . ' (journées)');
        }
        $table = [$header];
        foreach ($rows as $row) {
            $line = $people ? [$row['name'], $row['club'] ?? '', $row['clubLabel']] : [$row['club'] ?? '', $row['clubLabel'], $row['people']];
            foreach (array_keys($roles) as $code) {
                array_push($line, $row['designations'][$code] ?? 0, $row['gamedays'][$code] ?? 0);
            }
            $table[] = $line;
        }

        return $table;
    }

    #[Route('/roles', name: 'admin_designations_roles', methods: ['GET'])]
    #[OA\Get(path: '/admin/designations/roles', summary: 'Catalogue des rôles de Désignation (app4 ne le réimplémente pas)')]
    public function roles(): JsonResponse
    {
        return $this->json(array_map(self::roleToArray(...), RoleCatalog::all()));
    }

    #[Route('', name: 'admin_designations_list', methods: ['GET'])]
    #[OA\Get(path: '/admin/designations', summary: "Désignations d'une saison, filtrables par rôle, périmètre, licence ou club")]
    public function list(Request $request): JsonResponse
    {
        $season = (string) $request->query->get('season', '');
        if (!preg_match(self::SEASON_PATTERN, $season)) {
            return $this->error(DesignationRejected::INVALID, Response::HTTP_BAD_REQUEST);
        }
        if (!$this->canRead()) {
            return $this->error(DesignationRejected::FORBIDDEN, Response::HTTP_FORBIDDEN);
        }
        $filters = array_filter([
            'role' => $request->query->get('role'),
            'scopeType' => $request->query->get('scopeType'),
            'scopeId' => $request->query->get('scopeId'),
            'matric' => $request->query->has('matric') ? $request->query->getInt('matric') : null,
            'club' => $request->query->get('club'),
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
        $items = array_map(self::rowToArray(...), $this->repository->search($season, $filters));

        return $this->json(['items' => $items, 'total' => count($items)]);
    }

    #[Route('', name: 'admin_designations_create', methods: ['POST'])]
    #[OA\Post(path: '/admin/designations', summary: "Désigner un licencié sur un périmètre (club figé depuis la licence ; `email` crée son compte s'il n'en a pas)")]
    #[OA\Response(response: 201, description: 'Désignation créée')]
    #[OA\Response(response: 403, description: 'Rôle non désignable par cet utilisateur')]
    #[OA\Response(response: 409, description: 'Même personne, même rôle, même périmètre')]
    #[OA\Response(response: 422, description: 'role_unknown, scope_not_allowed, authority_not_allowed, scope_full, scope_unknown, licence_unknown')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $input = is_array($data) ? self::creationInput($data) : null;
        if ($input === null) {
            return $this->error(DesignationRejected::INVALID, Response::HTTP_BAD_REQUEST);
        }

        try {
            $created = $this->service->create($this->currentUser(), ...$input);
        } catch (DesignationRejected $rejected) {
            return $this->error($rejected->error, $rejected->status);
        }
        $this->journal('Ajout Désignation', $created->designation);

        return $this->json(
            $this->designationToArray($created->designation) + ['account' => $created->account],
            Response::HTTP_CREATED,
        );
    }

    #[Route('/{id}', name: 'admin_designations_update', methods: ['PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Patch(path: '/admin/designations/{id}', summary: 'Corriger le club (profils 1–2), l\'autorité, l\'ordre ou la qualité')]
    public function update(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $changes = is_array($data) ? self::changes($data) : null;
        if ($changes === null) {
            return $this->error(DesignationRejected::INVALID, Response::HTTP_BAD_REQUEST);
        }

        try {
            $designation = $this->service->update($this->currentUser(), $id, $changes);
        } catch (DesignationRejected $rejected) {
            return $this->error($rejected->error, $rejected->status);
        }
        $this->journal('Modif Désignation', $designation);

        return $this->json($this->designationToArray($designation));
    }

    #[Route('/{id}', name: 'admin_designations_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    #[OA\Delete(path: '/admin/designations/{id}', summary: 'Supprimer une Désignation')]
    public function delete(int $id): Response
    {
        try {
            $designation = $this->service->delete($this->currentUser(), $id);
        } catch (DesignationRejected $rejected) {
            return $this->error($rejected->error, $rejected->status);
        }
        $this->journal('Suppression Désignation', $designation);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<mixed> $data
     *
     * @return array{season: string, role: string, scopeType: ScopeType, scopeId: string, matric: int, authority: Authority|null, order: int, quality: string|null, email: string|null}|null
     */
    private static function creationInput(array $data): ?array
    {
        $season = $data['season'] ?? null;
        $role = $data['role'] ?? null;
        $scopeType = is_string($data['scopeType'] ?? null) ? ScopeType::tryFrom($data['scopeType']) : null;
        $scopeId = $data['scopeId'] ?? null;
        $matric = $data['matric'] ?? null;
        $authority = $data['authority'] ?? null;
        $order = $data['order'] ?? 1;
        $quality = $data['quality'] ?? null;
        $email = $data['email'] ?? null;
        if ($email !== null && (!is_string($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > self::MAX_EMAIL_LENGTH)) {
            return null;
        }
        if (!is_string($season) || !preg_match(self::SEASON_PATTERN, $season)
            || !is_string($role) || $scopeType === null
            || !is_string($scopeId) || !preg_match(self::SCOPE_ID_PATTERN, $scopeId)
            || !is_int($matric) || $matric <= 0
            || !self::validOrder($order) || !self::validQuality($quality)
            || ($authority !== null && (!is_string($authority) || Authority::tryFrom($authority) === null))) {
            return null;
        }

        return [
            'season' => $season,
            'role' => $role,
            'scopeType' => $scopeType,
            'scopeId' => $scopeId,
            'matric' => $matric,
            'authority' => $authority === null ? null : Authority::from($authority),
            'order' => $order,
            'quality' => $quality,
            'email' => $email,
        ];
    }

    /**
     * @param array<mixed> $data
     *
     * @return array{club?: string|null, authority?: Authority, order?: int, quality?: string|null}|null
     */
    private static function changes(array $data): ?array
    {
        $changes = [];
        if (array_key_exists('club', $data)) {
            $club = $data['club'];
            if ($club !== null && (!is_string($club) || !preg_match('/^[A-Za-z0-9]{1,6}$/', $club))) {
                return null;
            }
            $changes['club'] = $club;
        }
        if (array_key_exists('authority', $data)) {
            $authority = is_string($data['authority']) ? Authority::tryFrom($data['authority']) : null;
            if ($authority === null) {
                return null;
            }
            $changes['authority'] = $authority;
        }
        if (array_key_exists('order', $data)) {
            if (!self::validOrder($data['order'])) {
                return null;
            }
            $changes['order'] = $data['order'];
        }
        if (array_key_exists('quality', $data)) {
            if (!self::validQuality($data['quality'])) {
                return null;
            }
            $changes['quality'] = $data['quality'];
        }

        return $changes;
    }

    private static function validOrder(mixed $order): bool
    {
        return is_int($order) && $order >= 1 && $order <= self::MAX_ORDER;
    }

    private static function validQuality(mixed $quality): bool
    {
        return $quality === null || (is_string($quality) && mb_strlen($quality) <= self::MAX_QUALITY_LENGTH);
    }

    /** @return array<string, mixed> */
    private static function roleToArray(RoleDefinition $role): array
    {
        return [
            'code' => $role->code,
            'label' => $role->label,
            'scopes' => array_map(static fn (ScopeType $scope): string => $scope->value, $role->scopes),
            'mandateProfile' => $role->mandateProfile,
            'requirement' => ['national' => $role->nationalRequirement->value, 'other' => $role->otherRequirement->value],
            'authorities' => array_map(static fn (Authority $authority): string => $authority->value, $role->authorities),
            'maxPerScope' => $role->maxPerScope,
            'designableByDelegate' => $role->designableByDelegate,
            'designableByMaxProfile' => $role->designableByMaxProfile,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function rowToArray(array $row): array
    {
        $designation = DesignationRepository::hydrate($row);

        return [
            'id' => $designation->id,
            'season' => $designation->season,
            'role' => $designation->role,
            'scopeType' => $designation->scopeType->value,
            'scopeId' => $designation->scopeId,
            'matric' => $designation->matric,
            'lastName' => $row['nom'] ?? null,
            'firstName' => $row['prenom'] ?? null,
            'club' => $designation->club,
            'authority' => $designation->authority->value,
            'order' => $designation->order,
            'quality' => $designation->quality,
        ];
    }

    /** @return array<string, mixed> */
    private function designationToArray(Designation $designation): array
    {
        return self::rowToArray($this->repository->findRow($designation->id) ?? []);
    }

    private function journal(string $action, Designation $designation): void
    {
        $this->logActionForSeason($action, $designation->season, sprintf(
            '%s %s %s : licence %d (id %d)',
            $designation->role,
            $designation->scopeType->value,
            $designation->scopeId,
            $designation->matric,
            $designation->id,
        ));
    }

    /** Lecture des Désignations d'un périmètre : jusqu'au délégué fédéral (profil 5), qui constitue le jury. */
    private function canRead(): bool
    {
        return $this->currentUser()->getEffectiveNiveau() <= self::READER_MAX_PROFILE;
    }

    /** Récapitulatif et relevé des faits (rétributions) : profils 1 et 2 seulement, le profil 3 ne voit que le suivi. */
    private function isStatementReader(): bool
    {
        return $this->currentUser()->getEffectiveNiveau() <= self::STATEMENT_MAX_PROFILE;
    }

    private function isDesignator(): bool
    {
        return $this->currentUser()->getEffectiveNiveau() <= RoleCatalog::DESIGNATOR_MAX_PROFILE;
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function error(string $code, int $status): JsonResponse
    {
        return $this->json(['error' => $code], $status);
    }
}
