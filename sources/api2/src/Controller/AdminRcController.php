<?php

namespace App\Controller;

use App\Designation\Designation;
use App\Designation\DesignationRejected;
use App\Designation\DesignationRepository;
use App\Designation\DesignationService;
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
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page RC d'app4 (PAGE_RC.md). Depuis l'absorption de kp_rc (FEATURE_DESIGNATIONS.md D4, ADR-0003), un RC est une
 * Désignation de rôle `RC` : sur la compétition, ou sur le niveau national pour un RC « national sans compétition »
 * (`competitionCode` null). Le contrat de l'API est inchangé (RC-04) ; les écritures passent par DesignationService
 * (club figé, recopie dans Responsable_insc).
 */
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: '27. App4 - RC')]
class AdminRcController extends AbstractController
{
    use AdminLoggableTrait;

    private const ROLE = 'RC';
    private const MIN_ORDER = 1;
    private const MAX_ORDER = 99;

    public function __construct(
        private readonly Connection $connection,
        private readonly DesignationService $designations,
        private readonly DesignationRepository $repository,
    ) {
    }

    #[Route('/admin/rc', name: 'admin_rc_list', methods: ['GET'])]
    #[OA\Get(path: '/admin/rc', summary: 'List RC for a season', tags: ['27. App4 - RC'])]
    public function list(Request $request): JsonResponse
    {
        $season = (string) $request->query->get('season', '');
        if ($season === '') {
            return $this->json(['message' => 'Season is required'], Response::HTTP_BAD_REQUEST);
        }

        $sql = "SELECT d.id, d.perimetre_type, d.perimetre_id, d.saison, d.ordre, d.matric, d.club,
                       l.Nom, l.Prenom, comp.Libelle AS competition_libelle
                FROM kp_designation d
                LEFT JOIN kp_licence l ON l.Matric = d.matric
                LEFT JOIN kp_competition comp ON d.perimetre_type = 'COMPETITION' AND comp.Code = d.perimetre_id AND comp.Code_saison = d.saison
                WHERE d.role = 'RC' AND d.saison = ?";
        $params = [$season];
        $codes = array_values(array_filter(array_map('trim', explode(',', (string) $request->query->get('competitions', '')))));
        if ($codes !== []) {
            $sql .= sprintf(" AND (d.perimetre_type = 'NIVEAU' OR d.perimetre_id IN (%s))", implode(',', array_fill(0, count($codes), '?')));
            $params = array_merge($params, $codes);
        }
        $sql .= " ORDER BY d.perimetre_type = 'NIVEAU' DESC, d.perimetre_id, d.ordre";

        $items = array_map(static function (array $row): array {
            $competitionCode = $row['perimetre_type'] === ScopeType::Niveau->value ? null : $row['perimetre_id'];

            return [
                'id' => (int) $row['id'],
                'competitionCode' => $competitionCode,
                'competitionLabel' => $competitionCode === null ? 'National (sans compétition)' : ($row['competition_libelle'] ?? $competitionCode),
                'season' => $row['saison'],
                'ordre' => (int) $row['ordre'],
                'matric' => (int) $row['matric'],
                'nom' => $row['Nom'] ?? '',
                'prenom' => $row['Prenom'] ?? '',
                'club' => $row['club'] ?? '',
            ];
        }, $this->connection->fetchAllAssociative($sql, $params));

        return $this->json(['items' => $items, 'total' => count($items)]);
    }

    #[Route('/admin/rc', name: 'admin_rc_create', methods: ['POST'])]
    #[OA\Post(path: '/admin/rc', summary: 'Create a new RC', tags: ['27. App4 - RC'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data) || empty($data['season']) || empty($data['matric']) || !isset($data['ordre'])) {
            return $this->json(['message' => 'Season, matric, and ordre are required'], Response::HTTP_BAD_REQUEST);
        }
        $ordre = (int) $data['ordre'];
        if ($ordre < self::MIN_ORDER || $ordre > self::MAX_ORDER) {
            return $this->json(['message' => 'Ordre must be between 1 and 99'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $rc = $this->designate((string) $data['season'], $data['competitionCode'] ?? null, (int) $data['matric'], $ordre);
        } catch (DesignationRejected $rejected) {
            return $this->rejection($rejected);
        }
        $this->logActionForCompetition('Ajout Rc', $rc->season, self::competitionOf($rc) ?? 'CNA', "Matric: {$rc->matric}, Ordre: {$rc->order}");

        return $this->json(['id' => $rc->id, 'message' => 'RC ajouté avec succès'], Response::HTTP_CREATED);
    }

    /** Changer de compétition ou de licencié remplace la Désignation (nouvel id) ; changer l'ordre la modifie. */
    #[Route('/admin/rc/{id}', name: 'admin_rc_update', methods: ['PUT'])]
    #[OA\Put(path: '/admin/rc/{id}', summary: 'Update an existing RC', tags: ['27. App4 - RC'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $current = $this->findRc($id);
        if ($current === null) {
            return $this->json(['message' => 'RC not found'], Response::HTTP_NOT_FOUND);
        }
        $data = json_decode($request->getContent(), true);
        $data = is_array($data) ? $data : [];
        $competitionCode = array_key_exists('competitionCode', $data) ? $data['competitionCode'] : self::competitionOf($current);
        $matric = (int) ($data['matric'] ?? 0);
        $ordre = (int) ($data['ordre'] ?? 0);
        if ($matric === 0 || $ordre === 0) {
            return $this->json(['message' => 'Matric and ordre are required'], Response::HTTP_BAD_REQUEST);
        }
        if ($ordre < self::MIN_ORDER || $ordre > self::MAX_ORDER) {
            return $this->json(['message' => 'Ordre must be between 1 and 99'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->currentUser();
        try {
            $rc = $this->repository->transactional(function () use ($user, $current, $competitionCode, $matric, $ordre): Designation {
                if ($competitionCode === self::competitionOf($current) && $matric === $current->matric) {
                    return $this->designations->update($user, $current->id, ['order' => $ordre]);
                }
                $this->designations->delete($user, $current->id);

                return $this->designate($current->season, $competitionCode, $matric, $ordre);
            });
        } catch (DesignationRejected $rejected) {
            return $this->rejection($rejected);
        }
        $this->logActionForCompetition('Modif Rc', $rc->season, self::competitionOf($rc) ?? 'CNA', "Id: {$rc->id}, Matric: {$rc->matric}, Ordre: {$rc->order}");

        return $this->json(['id' => $rc->id, 'message' => 'RC modifié avec succès']);
    }

    #[Route('/admin/rc', name: 'admin_rc_delete', methods: ['DELETE'])]
    #[OA\Delete(path: '/admin/rc', summary: 'Delete RC in bulk (legacy - use POST /bulk-delete instead)', tags: ['27. App4 - RC'])]
    public function delete(Request $request): JsonResponse
    {
        return $this->bulkDelete($request);
    }

    #[Route('/admin/rc/bulk-delete', name: 'admin_rc_bulk_delete', methods: ['POST'])]
    #[OA\Post(path: '/admin/rc/bulk-delete', summary: 'Delete RC in bulk', tags: ['27. App4 - RC'])]
    public function bulkDelete(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $ids = is_array($data) ? ($data['ids'] ?? []) : [];
        if (empty($ids) || !is_array($ids)) {
            return $this->json(['message' => 'IDs array is required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->currentUser();
        try {
            $deleted = $this->repository->transactional(function () use ($user, $ids): int {
                $deleted = 0;
                foreach (array_map('intval', $ids) as $id) {
                    if ($this->findRc($id) !== null) {
                        $this->designations->delete($user, $id);
                        $deleted++;
                    }
                }

                return $deleted;
            });
        } catch (DesignationRejected $rejected) {
            return $this->rejection($rejected);
        }
        $this->logActionForCompetition('Suppression Rc', null, null, 'IDs: ' . implode(', ', $ids));

        return $this->json(['deleted' => $deleted, 'message' => "$deleted RC supprimé(s) avec succès"]);
    }

    /** @throws DesignationRejected */
    private function designate(string $season, mixed $competitionCode, int $matric, int $ordre): Designation
    {
        $national = $competitionCode === null || $competitionCode === '';
        $scopeType = $national ? ScopeType::Niveau : ScopeType::Competition;
        $scopeId = $national ? Designation::NATIONAL_LEVEL : (string) $competitionCode;

        return $this->designations->create($this->currentUser(), $season, self::ROLE, $scopeType, $scopeId, $matric, null, $ordre, null)->designation;
    }

    private function findRc(int $id): ?Designation
    {
        $designation = $this->repository->find($id);

        return $designation?->role === self::ROLE ? $designation : null;
    }

    private static function competitionOf(Designation $rc): ?string
    {
        return $rc->scopeType === ScopeType::Niveau ? null : $rc->scopeId;
    }

    private function rejection(DesignationRejected $rejected): JsonResponse
    {
        return match ($rejected->error) {
            DesignationRejected::LICENCE_UNKNOWN => $this->json(['message' => 'Licence not found'], Response::HTTP_NOT_FOUND),
            DesignationRejected::DUPLICATE => $this->json(['message' => 'Ce RC existe déjà pour cette compétition et cette saison'], Response::HTTP_CONFLICT),
            DesignationRejected::SCOPE_UNKNOWN => $this->json(['message' => 'Competition not found in this season'], Response::HTTP_BAD_REQUEST),
            DesignationRejected::NOT_FOUND => $this->json(['message' => 'RC not found'], Response::HTTP_NOT_FOUND),
            default => $this->json(['message' => $rejected->error], $rejected->status),
        };
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
