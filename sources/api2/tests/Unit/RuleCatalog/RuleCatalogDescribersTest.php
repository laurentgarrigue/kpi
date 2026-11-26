<?php

namespace App\Tests\Unit\RuleCatalog;

use App\AgeCategory\AgeCategoryRuleDescriber;
use App\Discipline\SuspensionCatalog;
use App\Discipline\SuspensionCatalogDescriber;
use App\Eligibility\PlayerEligibilityRules;
use App\Eligibility\PlayerEligibilityRulesDescriber;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfCatalogDescriber;
use App\RuleCatalog\RuleCatalogDescriber;
use App\RuleCatalog\RuleCatalogDescription;
use App\Scoring\ScoringRulesDescriber;
use App\Scrutineering\EquipmentRules;
use App\Scrutineering\EquipmentRulesDescriber;
use App\Tests\Support\FfckAgeCategoryTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Descriptions of the business rule catalogues (FEATURE_RULES_CATALOG_PDF.md § 5). The medals need the database:
 * RCAT-06 is in tests/Integration/RulesCatalogPdfTest.php.
 */
final class RuleCatalogDescribersTest extends TestCase
{
    private const REPOSITORY_ROOT = __DIR__ . '/../../../../..';

    /** @return iterable<string, array{RuleCatalogDescriber, string}> */
    public static function describers(): iterable
    {
        yield 'player-eligibility' => [new PlayerEligibilityRulesDescriber(), 'player-eligibility'];
        yield 'age-categories' => [new AgeCategoryRuleDescriber(new FfckAgeCategoryTable()), 'age-categories'];
        yield 'scoring' => [new ScoringRulesDescriber(), 'scoring'];
        yield 'card-suspensions' => [new SuspensionCatalogDescriber(), 'card-suspensions'];
        yield 'equipment' => [new EquipmentRulesDescriber(), 'equipment'];
        yield 'pdf-documents' => [new PdfCatalogDescriber(), 'pdf-documents'];
    }

    #[DataProvider('describers')]
    public function testRcat01And02EachCatalogueHasItsCodeATitleItsSourcesAndValues(RuleCatalogDescriber $describer, string $code): void
    {
        $description = $describer->describe('2027');

        self::assertSame($code, $describer->code());
        self::assertNotSame('', $description->title);
        self::assertNotEmpty($description->sources);
        $files = array_filter($description->sources, static fn (string $source): bool => str_starts_with($source, 'sources/') || str_starts_with($source, 'DOC/'));
        self::assertNotEmpty($files, 'at least one file of the repository');
        foreach ($files as $file) {
            self::assertFileExists(self::REPOSITORY_ROOT . '/' . explode(' ', $file)[0]);
        }
        self::assertNotEmpty($description->sections);
        foreach ($description->sections as $section) {
            self::assertNotSame('', $section['title']);
            self::assertTrue(($section['rows'] ?? $section['pairs'] ?? $section['lines'] ?? []) !== [], $section['title'] . ' is empty');
        }
    }

    public function testRcat03PlayerEligibilityFollowsTheRulesAndTheSeason(): void
    {
        $describer = new PlayerEligibilityRulesDescriber();
        $criteria2027 = self::rowsByFirstColumn($describer->describe('2027'), 'Critères par niveau de compétition', 'criterion');
        $criteria2026 = self::rowsByFirstColumn($describer->describe('2026'), 'Critères par niveau de compétition', 'criterion');

        self::assertSame('bloquant (joueur refusé)', $criteria2027['Contrôle']['national']);
        self::assertSame('alerte seulement', $criteria2027['Contrôle']['regional']);
        self::assertSame(implode(', ', PlayerEligibilityRules::RULES['national']['licenceTypes']), $criteria2027['Types de licence acceptés']['national']);
        self::assertSame('Verte (PAGV)', $criteria2027['Pagaie minimale']['national']);
        self::assertSame('Jaune (PAGJ)', $criteria2027['Pagaie minimale']['regional']);
        self::assertSame('signalée sans bloquer', $criteria2027['Pagaie insuffisante']['national']);
        self::assertSame('bloquante', $criteria2026['Pagaie insuffisante']['national']);
        self::assertSame('alerte', $criteria2026['Pagaie insuffisante']['regional']);
        self::assertSame(['PAGB', 'PAGJ', 'PAGV', 'PAGBL', 'PAGR', 'PAGN'], array_keys(PlayerEligibilityRulesDescriber::PAGAIE_COLOURS), 'one colour per rank');
        self::assertSame(array_keys(PlayerEligibilityRules::PAGAIE_RANKS), array_keys(PlayerEligibilityRulesDescriber::PAGAIE_COLOURS));
    }

    public function testRcat04BirthYearsOfEachAgeCategoryAreGivenByTheRule(): void
    {
        $categories = self::rowsByFirstColumn((new AgeCategoryRuleDescriber(new FfckAgeCategoryTable()))->describe('2027'), 'Catégories de la saison 2027', 'code');

        self::assertSame('2011–2012', $categories['CAD']['years']);
        self::assertSame('15 à 16 ans', $categories['CAD']['ages']);
        self::assertSame('2009–2010', $categories['JUN']['years']);
        self::assertSame('1907–1952', $categories['V9']['years']);
    }

    public function testRcat05ScoringTablesComeFromTheRule(): void
    {
        $description = (new ScoringRulesDescriber())->describe('2027');
        $sequences = self::rowsByFirstColumn($description, 'Enchaînement des périodes (score à égalité)', 'type');
        $cards = self::rowsByFirstColumn($description, 'Cartons', 'card');
        $progression = self::rowsByFirstColumn($description, 'Progression des cartons d\'un joueur', 'previous');
        $penalties = self::pairs($description, 'Pénalités');

        self::assertSame('M1 → M2', $sequences['Classement (C)']['sequence']);
        self::assertSame('M1 → M2 → P1 → P2 → P3 → …', $sequences['Élimination (E)']['sequence']);
        self::assertSame(['oui', 'non', 'non'], [$cards['Rouge (R)']['clock'], $cards['Rouge (R)']['lift'], $cards['Rouge (R)']['returns']]);
        self::assertSame(['non', 'non', 'non'], [$cards['Noire (exclusion définitive) (D)']['clock'], $cards['Noire (exclusion définitive) (D)']['lift'], $cards['Noire (exclusion définitive) (D)']['returns']]);
        self::assertSame('refusé (pas supérieur)', $progression['Jaune']['J']);
        self::assertSame('autorisé', $progression['Jaune']['R']);
        self::assertSame('autorisé', $progression['aucun']['R'], 'nothing forces starting at green');
        self::assertSame('refusé (joueur exclu)', $progression['Rouge']['D']);
        self::assertSame('2', $penalties['Pénalités simultanées par équipe, au plus']);
        self::assertSame('10 min', self::pairs($description, 'Durées par défaut (la configuration de la compétition peut les remplacer)')['Mi-temps (M1, M2)']);
    }

    public function testRcat13CardSuspensionsDescribeTheVersionOfTheSeason(): void
    {
        $describer = new SuspensionCatalogDescriber();
        $description = $describer->describe('2027');
        $rules = self::rowsByFirstColumn($description, 'Règles (dans l\'ordre de départage)', 'code');
        $catalog = SuspensionCatalog::forSeason('2027');

        self::assertNotNull($catalog);
        self::assertSame(sprintf('v%d (depuis la saison %s)', $catalog->version, $catalog->firstSeason), $description->version);
        self::assertSame(array_map(static fn ($rule): string => $rule->code, $catalog->rules), array_keys($rules), 'order of the tie-break');
        self::assertSame(['vert', 'saison', 8, 1, 'non'], [$rules['GREEN_SEASON']['cards'], $rules['GREEN_SEASON']['scope'], $rules['GREEN_SEASON']['threshold'], $rules['GREEN_SEASON']['matches'], $rules['GREEN_SEASON']['jury']]);
        self::assertSame('journée (matchs différents)', $rules['YELLOW_RED_GAMEDAY']['scope']);
        self::assertSame('Nationale', self::pairs($description, 'Champ d\'application')['Sections couvertes']);

        $before = $describer->describe('2026');
        self::assertNull($before->version, 'no version before the first season');
        self::assertSame(['Aucune version du catalogue ne couvre la saison 2026.'], $before->sections[0]['lines']);
    }

    public function testRcat07EquipmentAndPdfDocumentsReadTheirCatalogue(): void
    {
        $equipment = self::rowsByFirstColumn((new EquipmentRulesDescriber())->describe('2027'), 'Éléments contrôlés', 'column');
        foreach (EquipmentRules::maximums() as $column => $maximum) {
            self::assertSame(sprintf('0 à %d', $maximum), $equipment[$column]['values']);
        }
        self::assertSame('0 à 6', $equipment['paddle_count']['values']);

        $documents = self::rowsByFirstColumn((new PdfCatalogDescriber())->describe('2027'), 'Documents et droits', 'document');
        self::assertSame(PdfCatalog::documents(), array_keys($documents));
        self::assertSame('ROLE_SUPER_ADMIN', $documents[PdfCatalog::RULES_CATALOG]['role']);
        self::assertSame('non', $documents[PdfCatalog::RULES_CATALOG]['public']);
        self::assertSame('—', $documents[PdfCatalog::GAMES_GRID]['role'], 'public version only');
        self::assertStringContainsString('visa : ROLE_ORGANIZER', $documents[PdfCatalog::PRESENCE]['options']);
    }

    /** @return array<string, array<string, mixed>> rows of a table section, by the value of a column */
    private static function rowsByFirstColumn(RuleCatalogDescription $description, string $title, string $key): array
    {
        $rows = self::section($description, $title)['rows'];

        return array_combine(array_map(static fn (array $row): string => (string) $row[$key], $rows), $rows);
    }

    /** @return array<string, string> */
    private static function pairs(RuleCatalogDescription $description, string $title): array
    {
        $pairs = self::section($description, $title)['pairs'];

        return array_combine(array_column($pairs, 0), array_column($pairs, 1));
    }

    /** @return array<string, mixed> */
    private static function section(RuleCatalogDescription $description, string $title): array
    {
        foreach ($description->sections as $section) {
            if ($section['title'] === $title) {
                return $section;
            }
        }
        self::fail(sprintf('No section "%s" in %s', $title, $description->title));
    }
}
