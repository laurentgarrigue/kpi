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
    public const BILAN_JOURNEE = 'BILAN_JOURNEE';
    /** Délai indicatif du bilan de journée après la fin de l'événement (D18). */
    private const BILAN_DEADLINE_DAYS = 3;
    /** Critères d'évaluation du site, notés de 1 à 4 (D25). */
    private const SITE_MAX = 4;

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
        $definitions = [self::incidentV1(), self::bilanJourneeV1()];
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

    /**
     * Bilan de journée (D1, D18, D25 ; § 7) : un par événement, ou par journée hors événement, rédigé par le délégué
     * CNA. Sections 1 (en-tête) et 9 (pièces liées) sont les données KPI affichées en tête ; les officiels et les
     * arbitres sont des lignes pré-remplies depuis KPI.
     */
    private static function bilanJourneeV1(): ReportTypeDefinition
    {
        $site = static fn (string $key, string $fr, string $en): ReportField => new ReportField($key, ReportField::SCORE, $fr, $en, max: self::SITE_MAX);
        $text = static fn (string $key, string $fr, string $en): ReportField => new ReportField($key, ReportField::TEXTAREA, $fr, $en);

        return new ReportTypeDefinition(
            self::BILAN_JOURNEE,
            1,
            'Bilan de journée',
            'Gameday report',
            [ReportScope::Evenement, ReportScope::Journee],
            [ReportTypeDefinition::RECIPIENT_BUREAU, 'ROLE:RC', ReportTypeDefinition::RECIPIENT_AUTHOR],
            [
                new ReportSection('officials', 'Officiels', 'Officials', [
                    new ReportField('officials', ReportField::TABLE, 'Officiels désignés', 'Designated officials', columns: [
                        new ReportField('role', ReportField::TEXT, 'Rôle', 'Role', readonly: true),
                        new ReportField('name', ReportField::TEXT, 'Nom', 'Name', readonly: true),
                        new ReportField('matric', ReportField::NUMBER, 'Licence', 'Licence', readonly: true),
                        new ReportField('presence', ReportField::SELECT, 'Présence', 'Attendance', options: ['present', 'absent', 'replaced']),
                        new ReportField('replaced_by', ReportField::TEXT, 'Remplacé par', 'Replaced by'),
                    ], rowsFrom: 'officials'),
                ]),
                new ReportSection('representatives', 'Représentants', 'Representatives', [
                    new ReportField('rep_clubs', ReportField::TEXT, 'Représentant des clubs', 'Clubs representative'),
                    new ReportField('rep_teams', ReportField::TEXT, 'Représentant des équipes', 'Teams representative'),
                    $text('rep_remarks', 'Remarques des représentants', 'Representatives remarks'),
                ]),
                new ReportSection('general', 'Bilan général', 'General report', [
                    $site('site_water', 'Plan d\'eau', 'Water'),
                    $site('site_pitches', 'Terrains et buts', 'Pitches and goals'),
                    $site('site_changing', 'Vestiaires et sanitaires', 'Changing rooms and toilets'),
                    new ReportField('camping_available', ReportField::CHECKBOX, 'Camping disponible pour les équipes', 'Camping available for the teams'),
                    $site('site_camping', 'Camping', 'Camping'),
                    $site('site_scoring', 'Table de marque et sono', 'Scoring table and sound'),
                    $site('site_results', 'Affichage des résultats', 'Results display'),
                    $site('site_hospitality', 'Accueil et restauration', 'Hospitality and catering'),
                    $site('site_safety', 'Sécurité et secours', 'Safety and first aid'),
                    $site('site_officials', 'Hébergement et repas des officiels', 'Officials accommodation and meals'),
                    new ReportField('site_total', ReportField::SUM, 'Total du site', 'Site total', max: 9 * self::SITE_MAX, sumOf: ['site_water', 'site_pitches', 'site_changing', 'site_camping', 'site_scoring', 'site_results', 'site_hospitality', 'site_safety', 'site_officials']),
                    new ReportField('podium', ReportField::CHECKBOX, 'Organisation d\'un podium (N2, NEM)', 'Podium ceremony (N2, NEM)'),
                    $text('general_comment', 'Commentaire général', 'General comment'),
                ]),
                new ReportSection('sport', 'Bilan sportif', 'Sporting report', [
                    new ReportField('matches_planned', ReportField::NUMBER, 'Matchs prévus', 'Games planned', prefill: 'matches.planned'),
                    new ReportField('matches_played', ReportField::NUMBER, 'Matchs joués', 'Games played', prefill: 'matches.played'),
                    $text('forfeits', 'Forfaits', 'Forfeits'),
                    $text('delays', 'Retards', 'Delays'),
                    $text('teams_absent', 'Équipes absentes', 'Absent teams'),
                    $text('sport_comment', 'Commentaire sportif', 'Sporting comment'),
                ]),
                new ReportSection('refereeing', 'Bilan arbitrage', 'Refereeing report', [
                    new ReportField('referees', ReportField::TABLE, 'Arbitres désignés', 'Designated referees', columns: [
                        new ReportField('name', ReportField::TEXT, 'Arbitre', 'Referee', readonly: true),
                        new ReportField('matric', ReportField::NUMBER, 'Licence', 'Licence', readonly: true),
                        new ReportField('games', ReportField::NUMBER, 'Matchs arbitrés', 'Games refereed', readonly: true),
                        new ReportField('outfit_warning', ReportField::CHECKBOX, 'Avertissement tenue non conforme', 'Non-compliant outfit warning'),
                        new ReportField('observation', ReportField::TEXT, 'Observation', 'Observation'),
                    ], rowsFrom: 'referees'),
                    $text('refereeing_comment', 'Commentaire sur l\'arbitrage', 'Refereeing comment'),
                ]),
                new ReportSection('logistics', 'Bilan logistique', 'Logistics report', [
                    new ReportField('schedule_kept', ReportField::SELECT, 'Horaires tenus', 'Schedule kept', options: ['yes', 'partly', 'no']),
                    $text('scoring', 'Scoring et feuilles de match', 'Scoring and score sheets'),
                    $text('equipment', 'Matériel', 'Equipment'),
                    $text('logistics_comment', 'Commentaire logistique', 'Logistics comment'),
                ]),
                new ReportSection('retributions', 'Rétributions et infractions', 'Retributions and offences', [
                    new ReportField('pitches_used', ReportField::NUMBER, 'Terrains utilisés', 'Pitches used'),
                    new ReportField('final', ReportField::CHECKBOX, 'Finale (championnat, coupe, play-off, play-down)', 'Final (championship, cup, play-off, play-down)'),
                    new ReportField('infractions', ReportField::TABLE, 'Infractions constatées', 'Offences noted', columns: [
                        new ReportField('offender', ReportField::TEXT, 'Club ou licencié', 'Club or licensee'),
                        new ReportField('category', ReportField::SELECT, 'Catégorie (RP KAP 59)', 'Category (RP KAP 59)', options: ['1', '2', '3', '4', '5', '6']),
                        new ReportField('description', ReportField::TEXT, 'Description', 'Description'),
                        new ReportField('report', ReportField::TEXT, 'Rapport lié (n°)', 'Linked report (no.)'),
                    ]),
                    $text('late_additions', 'Joueurs ajoutés hors délai', 'Players added late'),
                ]),
            ],
            deadlineDays: self::BILAN_DEADLINE_DAYS,
            writers: ReportTypeDefinition::WRITERS_DELEGATE,
            uniquePerScope: true,
            gamedayOnlyOutsideEvent: true,
        );
    }
}
