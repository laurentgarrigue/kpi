<?php

namespace App\Tests\Unit\Trait;

use App\Trait\DateValidationTrait;
use PHPUnit\Framework\TestCase;

/**
 * DateValidationTrait — validation du format de date des payloads d'API.
 *
 * Ce trait garde TOUS les endpoints qui acceptent une date (journées, matchs,
 * licences…). Une régression ici laisse passer en base des dates fantaisistes
 * ou, à l'inverse, rejette des dates valides. C'est de la logique 100 % pure :
 * aucune DB, aucun kernel → suite `unit`, bloquante en CI.
 *
 * La méthode est `private` (usage interne aux contrôleurs) : on l'exerce via une
 * classe hôte anonyme, comme le font les contrôleurs réels. On ne relâche PAS la
 * visibilité dans le code de prod pour les besoins du test.
 */
final class DateValidationTraitTest extends TestCase
{
    /** Expose la méthode privée du trait sans modifier le code de production. */
    private function validator(): object
    {
        return new class {
            use DateValidationTrait;

            public function check(?string $date): bool
            {
                return $this->isValidDate($date);
            }
        };
    }

    /**
     * Une date absente est VALIDE : le trait ne sert qu'à valider le format,
     * l'obligation de présence est la responsabilité de l'appelant.
     */
    public function testNullAndEmptyAreAccepted(): void
    {
        $v = $this->validator();

        self::assertTrue($v->check(null), 'null = champ non fourni → valide');
        self::assertTrue($v->check(''), 'chaîne vide = champ non fourni → valide');
    }

    public function testWellFormedDatesAreAccepted(): void
    {
        $v = $this->validator();

        self::assertTrue($v->check('2026-07-30'));
        self::assertTrue($v->check('2000-01-01'));
        // Année bissextile : le 29 février existe en 2024.
        self::assertTrue($v->check('2024-02-29'));
    }

    /**
     * Le cœur du trait : `createFromFormat` est laxiste (il « roule » les dates
     * hors bornes, ex. 2026-02-31 → 2026-03-03). La comparaison
     * `$d->format('Y-m-d') === $date` est donc ce qui attrape ces cas — ce test
     * verrouille ce comportement pour qu'un refactor ne le supprime pas.
     */
    public function testOverflowingDatesAreRejected(): void
    {
        $v = $this->validator();

        self::assertFalse($v->check('2026-02-31'), '31 février → roulé par PHP, doit être rejeté');
        self::assertFalse($v->check('2026-13-01'), 'mois 13 inexistant');
        self::assertFalse($v->check('2026-00-10'), 'mois 0 inexistant');
        self::assertFalse($v->check('2026-04-31'), 'avril n\'a que 30 jours');
        self::assertFalse($v->check('2025-02-29'), '2025 n\'est pas bissextile');
    }

    public function testMalformedStringsAreRejected(): void
    {
        $v = $this->validator();

        self::assertFalse($v->check('30/07/2026'), 'format français non accepté');
        self::assertFalse($v->check('2026-7-3'), 'mois/jour non zero-paddés');
        self::assertFalse($v->check('2026-07-30 12:00:00'), 'datetime : suffixe non autorisé');
        self::assertFalse($v->check('pas une date'));
        self::assertFalse($v->check('0000-00-00'), 'date zéro MySQL');
    }
}
