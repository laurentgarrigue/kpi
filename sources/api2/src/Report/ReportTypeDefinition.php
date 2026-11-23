<?php

namespace App\Report;

/**
 * Un type de rapport dans une version donnée (FEATURE_DESIGNATIONS.md D13, D18, D28) : rattachements, rédacteurs,
 * destinataires, délai, sections. Les réponses d'un rapport sont gardées avec le numéro de version : un rapport
 * ancien s'affiche et s'imprime toujours avec la définition de sa version (RAP-02).
 */
final class ReportTypeDefinition
{
    /** Destinataires : un rôle de Désignation du périmètre (`ROLE:<code>`), le bureau CNA, l'auteur, le jury. */
    public const RECIPIENT_AUTHOR = 'AUTHOR';
    public const RECIPIENT_BUREAU = 'BUREAU_CNA';
    /** Rédacteurs : tout officiel du périmètre (D15), ou seulement son délégué CNA (bilan de journée, BIL-02). */
    public const WRITERS_OFFICIALS = 'OFFICIALS';
    public const WRITERS_DELEGATE = 'DELEGATE';

    /**
     * @param list<ReportScope>   $scopes
     * @param list<string>        $recipients `ROLE:<code>`, `BUREAU_CNA`, `AUTHOR`
     * @param list<ReportSection> $sections
     * @param int|null            $deadlineDays délai indicatif après la fin du périmètre (D18), null = aucun
     * @param bool                $restricted lecture réservée au rédacteur, aux destinataires et aux profils 1–2 (D28)
     * @param bool                $uniquePerScope un seul rapport de ce type par périmètre (bilan de journée, BIL-01)
     * @param bool                $gamedayOnlyOutsideEvent sur une journée seulement si elle n'est dans aucun événement (D1)
     */
    public function __construct(
        public readonly string $code,
        public readonly int $version,
        public readonly string $labelFr,
        public readonly string $labelEn,
        public readonly array $scopes,
        public readonly array $recipients,
        public readonly array $sections,
        public readonly ?int $deadlineDays = null,
        public readonly bool $restricted = false,
        public readonly string $writers = self::WRITERS_OFFICIALS,
        public readonly bool $uniquePerScope = false,
        public readonly bool $gamedayOnlyOutsideEvent = false,
    ) {
    }

    public function allowsScope(ReportScope $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    /** @return array<string, ReportField> */
    public function fields(): array
    {
        $fields = [];
        foreach ($this->sections as $section) {
            foreach ($section->fields as $field) {
                $fields[$field->key] = $field;
            }
        }

        return $fields;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'version' => $this->version,
            'label' => ['fr' => $this->labelFr, 'en' => $this->labelEn],
            'scopes' => array_map(static fn (ReportScope $scope): string => $scope->value, $this->scopes),
            'recipients' => $this->recipients,
            'deadlineDays' => $this->deadlineDays,
            'restricted' => $this->restricted,
            'writers' => $this->writers,
            'uniquePerScope' => $this->uniquePerScope,
            'sections' => array_map(static fn (ReportSection $section): array => $section->toArray(), $this->sections),
        ];
    }
}
