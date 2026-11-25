<?php

namespace App\Scrutineering;

/**
 * Equipment checks of kp_scrutineering (PAGE_SCRUTINEERING.md § 3.2): each status cycles 0 (not checked) → 1 (valid)
 * → 2/3/4 (cosmetic / safety / technical defect); the paddle count goes from 0 to 6.
 */
final class EquipmentRules
{
    /** Column => [maximum value, label written in kp_journal] */
    private const PARAMETERS = [
        'kayak_status' => [4, 'kayak'],
        'vest_status' => [4, 'gilet'],
        'helmet_status' => [4, 'casque'],
        'paddle_count' => [6, 'pagaies'],
    ];

    /** Meaning of each value of a status column (the paddle count is a number, not a status). */
    public const STATUSES = [0 => 'non contrôlé', 1 => 'valide', 2 => 'défaut esthétique', 3 => 'défaut sécurité', 4 => 'défaut technique'];

    /** Route requirement listing the accepted columns. */
    public const PARAMETER_REQUIREMENT = 'kayak_status|vest_status|helmet_status|paddle_count';

    public static function isValid(string $parameter, int $value): bool
    {
        return isset(self::PARAMETERS[$parameter]) && $value >= 0 && $value <= self::PARAMETERS[$parameter][0];
    }

    /** @return array<string, int> highest accepted value of each column */
    public static function maximums(): array
    {
        return array_map(static fn (array $parameter): int => $parameter[0], self::PARAMETERS);
    }

    public static function journalLabel(string $parameter): string
    {
        return self::PARAMETERS[$parameter][1] ?? $parameter;
    }
}
