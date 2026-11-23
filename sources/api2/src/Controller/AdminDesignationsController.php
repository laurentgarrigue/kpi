<?php

namespace App\Controller;

use App\Designation\Authority;
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
    private const MAX_QUALITY_LENGTH = 100;

    public function __construct(
        private readonly DesignationService $service,
        private readonly DesignationRepository $repository,
        private readonly Connection $connection,
    ) {
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
    #[OA\Post(path: '/admin/designations', summary: 'Désigner un licencié sur un périmètre (club figé depuis la licence)')]
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
            $designation = $this->service->create($this->currentUser(), ...$input);
        } catch (DesignationRejected $rejected) {
            return $this->error($rejected->error, $rejected->status);
        }
        $this->journal('Ajout Désignation', $designation);

        return $this->json($this->designationToArray($designation), Response::HTTP_CREATED);
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
     * @return array{season: string, role: string, scopeType: ScopeType, scopeId: string, matric: int, authority: Authority|null, order: int, quality: string|null}|null
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
