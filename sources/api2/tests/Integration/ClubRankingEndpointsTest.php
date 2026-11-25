<?php

namespace App\Tests\Integration;

use App\Tests\Support\FakeGotenbergClient;
use Symfony\Component\HttpFoundation\Response;

/**
 * National club ranking, end to end on the fixtures (PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 8, § 13): the 2026 scale of
 * the migration copied to the fixture season 2999, its N1H category pointed at the fixture championship RCH.
 */
final class ClubRankingEndpointsTest extends ApiTestCase
{
    private const SEASON = '2999';
    private const BASE = '/admin/club-ranking/' . self::SEASON;

    public function testCnc28bTheClubRankingIsForProfileOneOnly(): void
    {
        $this->requestAs('TADMIN', 'GET', '/admin/club-ranking/seasons', expectedStatus: Response::HTTP_FORBIDDEN);
        $this->requestAs('TSUPER', 'GET', '/admin/club-ranking/seasons');
        $this->client->request('GET', '/admin/pdf/club-ranking?season=2026', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TADMIN')]);
        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
    }

    public function testCnc01ASeasonCopiesTheScaleOfAnotherWithoutChangingIt(): void
    {
        $this->beginWrites();
        $before = $this->requestAs('TSUPER', 'GET', '/admin/club-ranking/2026');

        $this->requestAs('TSUPER', 'POST', '/admin/club-ranking/seasons', ['season' => self::SEASON, 'copy_from' => '2026'], Response::HTTP_CREATED);
        $copy = $this->requestAs('TSUPER', 'GET', self::BASE);

        self::assertCount(28, $copy['rules']);
        self::assertSame(self::column($before['rules'], 'code'), self::column($copy['rules'], 'code'));
        self::assertSame(self::column($before['categories'], 'code'), self::column($copy['categories'], 'code'));
        self::assertSame([], $copy['scale_errors']);
        self::assertSame($before['rules'], $this->requestAs('TSUPER', 'GET', '/admin/club-ranking/2026')['rules']);
        $this->requestAs('TSUPER', 'POST', '/admin/club-ranking/seasons', ['season' => self::SEASON], Response::HTTP_CONFLICT);
        $this->requestAs('TSUPER', 'POST', self::BASE . '/copy-scale', ['from' => '2026'], Response::HTTP_CONFLICT);
    }

    public function testCnc03AnInvalidScaleIsRefusedWithTheCategoryInCause(): void
    {
        $this->beginWrites();
        $this->requestAs('TSUPER', 'POST', '/admin/club-ranking/seasons', ['season' => self::SEASON, 'copy_from' => '2026'], Response::HTTP_CREATED);
        $category = $this->category('N1H');
        $this->requestAs('TSUPER', 'PUT', self::BASE . '/categories/' . $category['id'], ['code' => 'N1H', 'label' => 'N1H', 'entry_codes' => [], 'final_code' => null, 'regular_code' => null]);

        $refused = $this->requestAs('TSUPER', 'POST', self::BASE . '/compute', expectedStatus: Response::HTTP_UNPROCESSABLE_ENTITY);

        self::assertStringContainsString('N1H', implode(' ', $refused['errors']));
    }

    public function testTheLifeCycleOfASeason(): void
    {
        $this->beginWrites();
        $this->requestAs('TSUPER', 'POST', '/admin/club-ranking/seasons', ['season' => self::SEASON, 'copy_from' => '2026'], Response::HTTP_CREATED);
        $this->requestAs('TSUPER', 'PUT', self::BASE . '/settings', ['top_n' => 40, 'national_prefixes' => 'R,TST', 'excluded_competitions' => 'RNP']);
        $category = $this->category('N1H');
        $this->requestAs('TSUPER', 'PUT', self::BASE . '/categories/' . $category['id'], ['code' => 'N1H', 'label' => 'N1H', 'entry_codes' => ['RCH'], 'final_code' => null, 'regular_code' => 'RCH']);
        $this->requestAs('TSUPER', 'POST', self::BASE . '/entries', ['rule' => '7.2.5', 'club' => 'C001', 'detail' => 'Formation de mai'], Response::HTTP_CREATED);
        $this->requestAs('TSUPER', 'POST', self::BASE . '/entries', ['rule' => '7.1.1', 'club' => 'C001'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $computed = $this->requestAs('TSUPER', 'POST', self::BASE . '/compute');
        $overview = $this->requestAs('TSUPER', 'GET', self::BASE);

        self::assertGreaterThan(0, $computed['lines']);
        self::assertSame('computed', $overview['season']['status']);
        $alpha = $this->standing($overview, 'C001');
        self::assertSame(10, $alpha['by_rule']['7.1.3'], 'Equipe Alpha engaged in RCH (N1H category)');
        self::assertSame(15, $alpha['by_rule']['7.2.5'], 'manual entry');
        $lines = $this->requestAs('TSUPER', 'GET', self::BASE . '/lines?club=C001&rule=7.1.3')['items'];
        self::assertCount(1, $lines);
        self::assertStringContainsString('N1H', $lines[0]['reason']);

        // CNC-26: an adjustment needs a reason, and changes the total
        $this->requestAs('TSUPER', 'POST', self::BASE . '/adjustments', ['rule' => '7.1.3', 'club' => 'C001', 'points' => -10, 'reason' => ''], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->requestAs('TSUPER', 'POST', self::BASE . '/adjustments', ['rule' => '7.1.3', 'club' => 'C001', 'points' => -10, 'reason' => 'Équipe forfait'], Response::HTTP_CREATED);
        $adjusted = $this->standing($this->requestAs('TSUPER', 'GET', self::BASE), 'C001');
        self::assertSame($alpha['total'] - 10, $adjusted['total']);
        self::assertSame(array_sum($adjusted['by_rule']), $adjusted['total'], 'CNC-31');

        // Validation, publication, then nothing changes until the season is reopened (CNC-28)
        $this->requestAs('TSUPER', 'POST', self::BASE . '/publish', expectedStatus: Response::HTTP_CONFLICT);
        $this->requestAs('TSUPER', 'POST', self::BASE . '/validate');
        self::assertSame(1, $this->requestAs('TSUPER', 'POST', self::BASE . '/publish')['version']);
        $this->requestAs('TSUPER', 'POST', self::BASE . '/compute', expectedStatus: Response::HTTP_CONFLICT);
        $this->requestAs('TSUPER', 'POST', self::BASE . '/adjustments', ['rule' => '7.1.3', 'club' => 'C002', 'points' => 5, 'reason' => 'x'], Response::HTTP_CONFLICT);
        $publication = $this->requestAs('TSUPER', 'GET', self::BASE . '/publications/1');
        self::assertSame($adjusted['total'], $this->standing($publication, 'C001')['total']);

        // CNC-30: public PDF of the publication, work PDF with the scale
        $this->client->request('GET', '/admin/pdf/club-ranking?season=' . self::SEASON, server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TSUPER')]);
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        $html = (string) static::getContainer()->get(FakeGotenbergClient::class)->html;
        self::assertStringContainsString('Classement national des clubs', $html);
        self::assertStringContainsString('Club Alpha Lacville', $html);
        $this->client->request('GET', '/admin/pdf/club-ranking?season=' . self::SEASON . '&view=work', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TSUPER')]);
        self::assertSame(Response::HTTP_OK, $this->client->getResponse()->getStatusCode());
        self::assertStringContainsString('Arbitre national catégorie A', (string) static::getContainer()->get(FakeGotenbergClient::class)->html);

        $this->requestAs('TSUPER', 'POST', self::BASE . '/reopen');
        self::assertSame('draft', $this->requestAs('TSUPER', 'GET', self::BASE)['season']['status']);
        self::assertCount(1, $this->requestAs('TSUPER', 'GET', self::BASE)['publications']);
    }

    /** @return array<string, mixed> */
    private function category(string $code): array
    {
        foreach ($this->requestAs('TSUPER', 'GET', self::BASE)['categories'] as $category) {
            if ($category['code'] === $code) {
                return $category;
            }
        }
        self::fail('category ' . $code);
    }

    /**
     * @param array<string, mixed> $overview
     *
     * @return array<string, mixed>
     */
    private function standing(array $overview, string $club): array
    {
        foreach ($overview['standings'] as $row) {
            if ($row['club'] === $club) {
                return $row;
            }
        }
        self::fail('club ' . $club . ' not ranked');
    }
}
