<?php

namespace App\PublicSite;

/** Sections des groupes de compétitions (kp_groupe.section), libellées par une clé de traduction legacy. */
final class GroupSections
{
    public const LABELS = [
        1 => 'Competitions_Internationales',
        2 => 'Competitions_Nationales',
        3 => 'Competitions_Regionales',
        4 => 'Tournois_Internationaux',
        5 => 'Continents',
        100 => 'Divers',
    ];

    /** Sections publiées sur les pages publiques (« Divers » exclu). */
    public const PUBLIC_MAX = 100;

    /**
     * Regroupe des groupes triés par section en `[{ section, label, groups }]` (format de /groups/{season}).
     *
     * @param list<array{code: string, libelle: string, libelle_en: ?string, section: int|string}> $rows
     *
     * @return list<array{section: int, label: string, groups: list<array{code: string, libelle: string, libelle_en: ?string}>}>
     */
    public static function organize(array $rows): array
    {
        $sections = [];
        foreach ($rows as $row) {
            $section = (int) $row['section'];
            $sections[$section] ??= ['section' => $section, 'label' => self::LABELS[$section] ?? 'Unknown', 'groups' => []];
            $sections[$section]['groups'][] = ['code' => $row['code'], 'libelle' => $row['libelle'], 'libelle_en' => $row['libelle_en']];
        }

        return array_values($sections);
    }
}
