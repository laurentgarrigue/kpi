<?php

namespace App\Tests\Unit\Journal;

use App\Journal\JournalCategories;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Catégories du journal (PAGE_GESTION_JOURNAL.md § 5.2, JRN-CAT-01 à JRN-CAT-04). */
final class JournalCategoriesTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function actions(): iterable
    {
        // Libellés réellement écrits par le legacy (utyJournal) et par api2 (AdminLoggableTrait, PdfJournal…).
        yield 'connexion legacy' => ['Connexion', JournalCategories::CONNEXION];
        yield 'connexion app4' => ['Connexion Admin', JournalCategories::CONNEXION];
        yield 'révocation appareil' => ['Révocation appareil', JournalCategories::CONNEXION];
        yield 'lien Google' => ['Lien Google ajouté', JournalCategories::CONNEXION];
        yield 'reset mot de passe' => ['Reset mot de passe', JournalCategories::CONNEXION];

        yield 'utilisateur' => ['Création utilisateur', JournalCategories::ADMINISTRATION];
        yield 'utilisateurs en masse' => ['Suppression en masse utilisateurs', JournalCategories::ADMINISTRATION];
        yield 'mandat' => ['Modification mandat', JournalCategories::ADMINISTRATION];
        yield 'saison active' => ['Change Saison Active', JournalCategories::ADMINISTRATION];
        yield 'club' => ['CLUB_UPDATE', JournalCategories::ADMINISTRATION];
        yield 'comité départemental' => ['CD_CREATE', JournalCategories::ADMINISTRATION];
        yield 'licencié' => ['ATHLETE_UPDATE', JournalCategories::ADMINISTRATION];
        yield 'RC' => ['Modif Rc', JournalCategories::ADMINISTRATION];
        yield 'image' => ['Upload Image', JournalCategories::ADMINISTRATION];
        yield 'fusion' => ['Fusion Joueurs', JournalCategories::ADMINISTRATION];
        yield 'changement de code' => ['Change code', JournalCategories::ADMINISTRATION];
        yield 'import PCE' => ['Import PCE', JournalCategories::ADMINISTRATION];

        yield 'match' => ['Modification match', JournalCategories::OPERATION];
        yield 'journée' => ['Ajout journee', JournalCategories::OPERATION];
        yield 'classement' => ['Calcul Classement', JournalCategories::OPERATION];
        yield 'compétition' => ['Verrou Compet', JournalCategories::OPERATION];
        yield 'équipes de la compétition' => ['Modification kp_competition_equipe', JournalCategories::OPERATION];
        yield 'action inconnue' => ['IGNORE', JournalCategories::OPERATION];

        yield 'titulaire' => ['Ajout titulaire', JournalCategories::PRESENCE];
        yield 'MAJ titulaires' => ['MAJ titulaires journée', JournalCategories::PRESENCE];
        yield 'copie composition' => ['Copie Compo sur Journée', JournalCategories::PRESENCE];
        yield 'feuille de présence compétition' => ['Modification kp_competition_equipe_joueur', JournalCategories::PRESENCE];
        yield 'feuille de présence match' => ['Modification kp_match_joueur', JournalCategories::PRESENCE];
        yield 'joueur de match' => ['Ajout joueur match', JournalCategories::PRESENCE];
        yield 'contrôle équipement' => ['Scrut équipement', JournalCategories::PRESENCE];

        yield 'scoring' => ['Scoring score', JournalCategories::SCORING];
        yield 'scoring rejeté' => ['Scoring rejeté (source)', JournalCategories::SCORING];
        yield 'incrustation' => ['Incrustation jeton créé', JournalCategories::SCORING];

        yield 'pdf' => ['PDF presence', JournalCategories::PDF];

        yield 'article' => ['Création article', JournalCategories::EDITORIAL];
        yield 'média' => ['Suppression média', JournalCategories::EDITORIAL];
        yield 'capacité Rédacteur' => ['Ajout capacité Rédacteur', JournalCategories::EDITORIAL];
        yield 'redirection' => ['Ajout redirection', JournalCategories::EDITORIAL];
    }

    #[DataProvider('actions')]
    public function testJrnCat01EachActionBelongsToItsCategory(string $action, string $expected): void
    {
        self::assertSame($expected, JournalCategories::categoryOf($action));
    }

    public function testJrnCat02CategoryMatchingIgnoresCaseLikeTheColumnCollation(): void
    {
        self::assertSame(JournalCategories::SCORING, JournalCategories::categoryOf('SCORING score'));
        self::assertSame(JournalCategories::CONNEXION, JournalCategories::categoryOf('connexion admin'));
    }

    public function testJrnCat03ScoringIsTheOnlyCategoryHiddenByDefault(): void
    {
        self::assertSame([JournalCategories::SCORING], JournalCategories::HIDDEN_BY_DEFAULT);
        self::assertContains(JournalCategories::FALLBACK, JournalCategories::ALL);
    }

    public function testJrnCat04FilterKeepsKnownCategoriesAndTreatsAllOrNoneAsNoFilter(): void
    {
        self::assertSame(
            [JournalCategories::CONNEXION, JournalCategories::PDF],
            JournalCategories::parseFilter('pdf, connexion,unknown'),
        );
        self::assertSame([], JournalCategories::parseFilter(''));
        self::assertSame([], JournalCategories::parseFilter('unknown'));
        self::assertSame([], JournalCategories::parseFilter(implode(',', JournalCategories::ALL)));
    }

    public function testSqlCaseQuotesEveryPatternAndFallsBackToOperation(): void
    {
        $sql = JournalCategories::sqlCase('j.Actions', fn (string $value) => "'" . $value . "'");

        self::assertStringStartsWith("(CASE WHEN j.Actions LIKE 'Scoring %'", $sql);
        self::assertStringEndsWith("ELSE 'operation' END)", $sql);
    }
}
