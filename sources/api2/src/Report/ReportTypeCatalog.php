<?php

namespace App\Report;

/**
 * ============================================================================
 *  CATALOGUE DES TYPES DE RAPPORT OFFICIEL — SEUL POINT DE CONFIGURATION
 * ============================================================================
 *
 * Chaque type de rapport (bilan de journée, rapport d'incident, rapport de jury d'appel, fiche d'évaluation
 * d'arbitrage) est défini ICI, dans toutes ses versions : changer un formulaire, c'est ajouter une version, jamais
 * modifier une version publiée (RAP-02). app4 reçoit le catalogue par l'API et rend les formulaires depuis lui
 * (RAP-01, ADR-0004).
 *
 * Spec : DOC/specs/FEATURE_DESIGNATIONS.md (D12, D13, D14, D18, D28 ; § 5, § 7).
 */
final class ReportTypeCatalog
{
    public const INCIDENT = 'INCIDENT';

    /** @var array<string, array<int, ReportTypeDefinition>>|null */
    private static ?array $types = null;

    /** @return array<string, array<int, ReportTypeDefinition>> type => version => définition */
    public static function all(): array
    {
        return self::$types ??= self::build();
    }

    public static function has(string $code): bool
    {
        return isset(self::all()[$code]);
    }

    /** Dernière version, celle des nouveaux rapports. */
    public static function current(string $code): ReportTypeDefinition
    {
        $versions = self::all()[$code] ?? throw new \InvalidArgumentException(sprintf('Type de rapport inconnu : %s', $code));

        return $versions[max(array_keys($versions))];
    }

    public static function version(string $code, int $version): ReportTypeDefinition
    {
        return self::all()[$code][$version] ?? throw new \InvalidArgumentException(sprintf('Version inconnue : %s v%d', $code, $version));
    }

    /** @return array<string, array<int, ReportTypeDefinition>> */
    private static function build(): array
    {
        $definitions = [self::incidentV1()];
        $types = [];
        foreach ($definitions as $definition) {
            $types[$definition->code][$definition->version] = $definition;
        }

        return $types;
    }

    /** Rapport d'incident (D14, D28) : tout officiel, rattaché à une compétition, un événement, une journée ou un match. */
    private static function incidentV1(): ReportTypeDefinition
    {
        return new ReportTypeDefinition(
            self::INCIDENT,
            1,
            "Rapport d'incident",
            'Incident report',
            [ReportScope::Competition, ReportScope::Evenement, ReportScope::Journee, ReportScope::Match],
            ['ROLE:DELEGUE_CNA', 'ROLE:JUGE_ARBITRE', 'ROLE:RC', ReportTypeDefinition::RECIPIENT_AUTHOR],
            [
                new ReportSection('context', 'Circonstances', 'Circumstances', [
                    new ReportField('date', ReportField::DATE, 'Date', 'Date', true),
                    new ReportField('time', ReportField::TIME, 'Heure', 'Time'),
                    new ReportField('place', ReportField::TEXT, 'Lieu précis', 'Exact place'),
                ]),
                new ReportSection('facts', 'Faits', 'Facts', [
                    new ReportField('description', ReportField::TEXTAREA, 'Description des faits', 'Description of the facts', true),
                    new ReportField('people', ReportField::TEXTAREA, 'Personnes impliquées (nom, équipe, numéro)', 'People involved (name, team, number)'),
                    new ReportField('sanction', ReportField::TEXT, 'Sanction prononcée sur le moment', 'Sanction given at the time'),
                ]),
                new ReportSection('follow_up', 'Suites', 'Follow-up', [
                    new ReportField('jury_requested', ReportField::CHECKBOX, "Réunion du jury d'appel demandée", 'Appeal jury requested'),
                    new ReportField('remarks', ReportField::TEXTAREA, 'Observations', 'Remarks'),
                ]),
            ],
        );
    }
}
