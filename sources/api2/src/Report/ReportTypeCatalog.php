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
    public const JURY_APPEL = 'JURY_APPEL';
    public const EVALUATION_ARBITRAGE = 'EVALUATION_ARBITRAGE';
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
        $definitions = [self::incidentV1(), self::bilanJourneeV1(), self::juryAppelV1(), self::evaluationArbitrageV1()];
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

    /**
     * Rapport de jury d'appel (D27, D28) : rédigé par le président du jury (délégué CNA) ; composition reprise des
     * Désignations et modifiable pour l'affaire dans le rapport seulement (RAP-09) ; affichage sur site.
     */
    private static function juryAppelV1(): ReportTypeDefinition
    {
        return new ReportTypeDefinition(
            self::JURY_APPEL,
            1,
            "Rapport de jury d'appel",
            'Appeal jury report',
            [ReportScope::Evenement, ReportScope::Journee],
            [ReportTypeDefinition::RECIPIENT_BUREAU, 'ROLE:RC', ReportTypeDefinition::RECIPIENT_JURY, ReportTypeDefinition::RECIPIENT_TEAMS_IN_CAUSE, ReportTypeDefinition::RECIPIENT_AUTHOR],
            [
                new ReportSection('composition', "Composition du jury d'appel", 'Appeal jury', [
                    new ReportField('jury', ReportField::TABLE, 'Membres', 'Members', columns: [
                        new ReportField('role', ReportField::TEXT, 'Rôle', 'Role', readonly: true),
                        new ReportField('name', ReportField::TEXT, 'Nom', 'Name', readonly: true),
                        new ReportField('matric', ReportField::NUMBER, 'Licence', 'Licence', readonly: true),
                        new ReportField('presence', ReportField::SELECT, 'Pour cette affaire', 'For this case', options: ['present', 'absent', 'recused']),
                        new ReportField('replaced_by', ReportField::TEXT, 'Remplacé par', 'Replaced by'),
                    ], rowsFrom: 'jury'),
                ]),
                new ReportSection('case', 'Affaire', 'Case', [
                    new ReportField('reason', ReportField::TEXTAREA, 'Motif de la réunion du jury', 'Reason for the meeting', true),
                    new ReportField('matches', ReportField::TABLE, 'Match(s) concerné(s)', 'Game(s) concerned', columns: [
                        new ReportField('number', ReportField::TEXT, 'N° de match', 'Game no.'),
                        new ReportField('teams', ReportField::TEXT, 'Équipes', 'Teams'),
                        new ReportField('referees', ReportField::TEXT, 'Arbitres (et clubs)', 'Referees (and clubs)'),
                    ]),
                    new ReportField('facts', ReportField::TEXTAREA, 'Rappel des faits', 'Facts', true),
                ]),
                new ReportSection('decision', 'Décision', 'Decision', [
                    new ReportField('decision', ReportField::TEXTAREA, 'Décisions', 'Decisions', true),
                    new ReportField('follow_up', ReportField::TEXTAREA, 'Suites à donner', 'Follow-up'),
                ]),
                new ReportSection('notice', 'Affichage et notification', 'Posting and notification', [
                    new ReportField('posted_date', ReportField::DATE, 'Affiché sur site le', 'Posted on site on'),
                    new ReportField('posted_time', ReportField::TIME, 'à', 'at'),
                    new ReportField('signed_at', ReportField::TEXT, 'Fait à', 'Done at'),
                ]),
            ],
            writers: ReportTypeDefinition::WRITERS_DELEGATE,
        );
    }

    /**
     * Fiche d'évaluation d'arbitrage (D28) : sur un match ; saisie par l'évaluateur s'il est officiel du périmètre,
     * sinon par le bureau CNA ; lecture réservée (RAP-08) ; note sur 50 = somme des cinq blocs sur 10, calculée.
     */
    private static function evaluationArbitrageV1(): ReportTypeDefinition
    {
        $mark = static fn (string $key, string $fr, string $en, int $max): ReportField => new ReportField($key, ReportField::SCORE, $fr, $en, max: $max);
        $block = static function (string $key, string $fr, string $en, array $marks) use ($mark): ReportSection {
            $fields = array_map(static fn (array $m): ReportField => $mark(...$m), $marks);
            $fields[] = new ReportField($key . '_total', ReportField::SUM, 'Note du bloc', 'Block mark', max: 10, sumOf: array_map(static fn (array $m): string => $m[0], $marks));

            return new ReportSection($key, $fr, $en, $fields);
        };
        $blocks = [
            $block('form', 'Forme', 'Form', [
                ['posture', 'Posture', 'Posture', 3], ['movement', 'Déplacements, anticipation, positionnement', 'Movement, anticipation, positioning', 4],
                ['style', "Style d'arbitrage, autorité", 'Refereeing style, authority', 2], ['verbal', 'Interventions verbales', 'Verbal interventions', 1],
            ]),
            $block('substance', 'Fond', 'Substance', [
                ['attitude', 'Attitude et comportement relationnel', 'Attitude and behaviour', 2], ['management', 'Gestion rencontre et conflits', 'Game and conflict management', 3],
                ['focus', 'Concentration, sérénité', 'Focus, composure', 3], ['before_after', 'Gestion avant et après match', 'Before and after the game', 2],
            ]),
            $block('game', 'Compréhension du jeu', 'Understanding of the game', [
                ['insight', 'Clairvoyance', 'Insight', 2], ['advantage', "Gestion de l'avantage", 'Advantage', 3],
                ['shot_clock', 'Gestion du time-shoot', 'Shot clock', 3], ['protection', 'Protection du jeu et des joueurs', 'Protection of the game and players', 2],
            ]),
            $block('laws', 'Application des lois du jeu', 'Application of the laws', [
                ['knowledge', 'Connaissance des règles, interprétation', 'Knowledge of the rules', 4], ['sanctions', 'Choix et application des sanctions, sens du jeu', 'Sanctions, sense of the game', 2],
                ['signals', 'Gestuelle et utilisation du sifflet', 'Signals and whistle', 1], ['efficiency', "Taux d'efficacité (signale toutes les fautes)", 'Efficiency (calls every foul)', 3],
            ]),
            $block('collaboration', 'Collaboration', 'Collaboration', [
                ['captains', 'Intervention auprès des capitaines, équipes', 'Captains, teams', 2], ['partner', "Collaboration avec l'arbitre secondaire", 'Second referee', 4],
                ['table', 'Interaction avec table de marque, time-shoot', 'Scoring table, shot clock', 2], ['procedures', 'Procédures remplacement, blessés, temps morts', 'Substitutions, injuries, time-outs', 2],
            ]),
        ];
        $allMarks = [];
        foreach ($blocks as $section) {
            foreach ($section->fields as $field) {
                if ($field->type === ReportField::SCORE) {
                    $allMarks[] = $field->key;
                }
            }
        }

        return new ReportTypeDefinition(
            self::EVALUATION_ARBITRAGE,
            1,
            "Fiche d'évaluation d'arbitrage",
            'Referee evaluation sheet',
            [ReportScope::Match],
            ['ROLE:JUGE_ARBITRE', ReportTypeDefinition::RECIPIENT_BUREAU, ReportTypeDefinition::RECIPIENT_AUTHOR],
            [
                new ReportSection('people', 'Arbitre évalué et examinateur', 'Evaluated referee and examiner', [
                    new ReportField('evaluated', ReportField::TEXT, 'Arbitre évalué (principal)', 'Evaluated referee (main)', true, prefill: 'match.referees.0'),
                    new ReportField('evaluated_club', ReportField::TEXT, 'Club', 'Club'),
                    new ReportField('examiner_quality', ReportField::SELECT, "Qualité de l'examinateur", 'Examiner qualification', options: ['A', 'B', 'C']),
                    new ReportField('outfit', ReportField::CHECKBOX, 'Évalué en tenue officielle et positionné en principal', 'In official outfit, as main referee', true),
                ]),
                new ReportSection('match', 'Match', 'Game', [
                    new ReportField('difficulty', ReportField::SELECT, 'Degré de difficulté, enjeu', 'Difficulty, stakes', options: ['easy', 'normal', 'difficult', 'very_difficult']),
                    new ReportField('standings', ReportField::TEXT, 'Classement des équipes (haut ou bas de tableau)', 'Standing of the teams'),
                    new ReportField('second_referee', ReportField::TEXT, 'Arbitre secondaire', 'Second referee', prefill: 'match.referees.1'),
                    new ReportField('second_level', ReportField::TEXT, "Niveau de l'arbitre secondaire", 'Second referee level'),
                    new ReportField('second_presence', ReportField::SELECT, 'Arbitre secondaire', 'Second referee', options: ['absent', 'errors', 'relevant', 'overbearing']),
                    new ReportField('incidents', ReportField::TEXTAREA, "Incidents de jeu ou conditions d'arbitrage particulières", 'Game incidents or particular conditions'),
                ]),
                ...$blocks,
                new ReportSection('assessment', 'Appréciation', 'Assessment', [
                    new ReportField('total', ReportField::SUM, 'Note finale', 'Final mark', max: 50, sumOf: $allMarks),
                    new ReportField('comment', ReportField::TEXTAREA, 'Appréciation générale : compétences maîtrisées et pistes de progrès', 'General assessment: skills mastered and ways to improve', true),
                    new ReportField('recommended_competition', ReportField::TEXT, 'Recommandé pour arbitrer dans la compétition', 'Recommended for the competition'),
                    new ReportField('keep_progressing', ReportField::CHECKBOX, 'Doit continuer à progresser', 'Must keep progressing'),
                    new ReportField('recommended_level', ReportField::SELECT, 'Recommandé pour', 'Recommended for', options: ['national_a', 'national_b', 'national_c', 'regional']),
                ]),
            ],
            restricted: true,
        );
    }
}
