<?php

namespace App\Designation;

/** Une ligne de kp_designation (FEATURE_DESIGNATIONS.md D2, D22, D23 ; § 8.1). */
final class Designation
{
    /** `perimetre_id` d'une Désignation sur le niveau national (D16). */
    public const NATIONAL_LEVEL = 'NATIONAL';

    public function __construct(
        public readonly int $id,
        public readonly string $season,
        public readonly string $role,
        public readonly ScopeType $scopeType,
        public readonly string $scopeId,
        public readonly int $matric,
        public readonly ?string $club,
        public readonly Authority $authority,
        public readonly int $order = 1,
        public readonly ?string $quality = null,
    ) {
    }
}
