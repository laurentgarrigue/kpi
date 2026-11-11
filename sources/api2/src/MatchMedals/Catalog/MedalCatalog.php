<?php

namespace App\MatchMedals\Catalog;

use App\MatchMedals\MedalSettings;
use App\MatchMedals\MedalThresholds;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The catalogue of medals, labels and statistics (kp_medal_catalog, FEATURE_MATCH_MEDALS.md § 3.1): names,
 * explanations rendered with the thresholds of a season, publication per surface, edition from the admin.
 */
final class MedalCatalog implements ResetInterface
{
    public const ICON_PATH = 'medals/icons/%s.svg';
    /** Generic pictogram of each family, shown until an element has its own (§ 7.1). */
    private const FAMILY_ICONS = [
        'elan' => 'wire_to_wire', 'reaction' => 'bounce_back', 'endurance' => 'extra_time', 'superiorite' => 'powerplay',
        'finition' => 'goal', 'defense' => 'clean_sheet', 'esprit' => 'fair_play', 'arbitrage' => 'whistle',
        'match' => 'thriller', 'analyse' => 'goal_timing',
    ];
    private const EDITABLE = ['name_fr', 'name_en', 'explanation_fr', 'explanation_en', 'status', 'published_app2', 'published_app3', 'sort_order'];
    private const STATUSES = ['active', 'retired'];
    private const JOURNAL_ACTION = 'Catalogue médailles';

    /** @var list<array<string, mixed>>|null */
    private ?array $rows = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly MedalSettings $settings,
        #[Autowire('%kernel.project_dir%/public')] private readonly string $publicDir,
    ) {
    }

    /** A FrankenPHP worker serves many requests: the list is read again for each, so that a change made by another worker or a migration shows. */
    public function reset(): void
    {
        $this->rows = null;
    }

    /** @return list<array<string, mixed>> every element, in display order */
    public function all(): array
    {
        return $this->rows ??= array_map(static fn (array $r): array => [
            ...$r,
            'published_app2' => (bool) $r['published_app2'],
            'published_app3' => (bool) $r['published_app3'],
            'sort_order' => (int) $r['sort_order'],
        ], $this->connection->fetchAllAssociative('SELECT * FROM kp_medal_catalog ORDER BY sort_order, code'));
    }

    public function find(string $code): ?array
    {
        foreach ($this->all() as $row) {
            if ($row['code'] === $code) {
                return $row;
            }
        }

        return null;
    }

    public function isVisible(array $row, MedalSurface $surface): bool
    {
        if ($row['status'] !== 'active') {
            return false;
        }

        return match ($surface) {
            MedalSurface::Admin => true,
            MedalSurface::Public => $row['published_app2'] || $row['published_app3'],
            MedalSurface::App2 => $row['published_app2'],
            MedalSurface::App3 => $row['published_app3'],
        };
    }

    /** @return list<array<string, mixed>> */
    public function visible(MedalSurface $surface, ?string $type = null): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (array $row): bool => $this->isVisible($row, $surface) && ($type === null || $row['type'] === $type),
        ));
    }

    /** @return list<string> */
    public function visibleCodes(MedalSurface $surface, ?string $type = null): array
    {
        return array_column($this->visible($surface, $type), 'code');
    }

    /** Thresholds a season was computed with; the current configuration for a season never computed. */
    public function thresholdsOf(?string $season): MedalThresholds
    {
        $copy = $season === null ? false : $this->connection->fetchOne('SELECT thresholds FROM kp_medal_season WHERE season = ?', [$season]);

        return MedalThresholds::fromArray($copy === false ? $this->settings->thresholds() : [...$this->settings->thresholds(), ...json_decode((string) $copy, true, 512, JSON_THROW_ON_ERROR)]);
    }

    /** Explanation with its {threshold} variables replaced (§ 3.1, MED-41). */
    public static function render(string $text, MedalThresholds $thresholds): string
    {
        return preg_replace_callback('/\{(\w+)\}/', static function (array $m) use ($thresholds): string {
            $value = $thresholds->toArray()[$m[1]] ?? null;

            return $value === null ? $m[0] : (is_array($value) ? implode(', ', $value) : (string) $value);
        }, $text);
    }

    /** @return array<string, mixed> what the public endpoints expose of an element */
    public function present(array $row, MedalThresholds $thresholds): array
    {
        return [
            'code' => $row['code'],
            'type' => $row['type'],
            'scopes' => str_split((string) $row['scopes']) === [''] ? [] : str_split((string) $row['scopes']),
            'family' => $row['family'],
            'data_level' => $row['data_level'],
            'name_fr' => $row['name_fr'],
            'name_en' => $row['name_en'],
            'explanation_fr' => self::render($row['explanation_fr'], $thresholds),
            'explanation_en' => self::render($row['explanation_en'], $thresholds),
            'icon' => sprintf(self::ICON_PATH, $this->iconName($row)),
            'icon_svg' => $this->iconMarkup($this->iconName($row)),
        ];
    }

    /** The element's pictogram if drawn, else the generic one of its family. */
    private function iconName(array $row): string
    {
        return is_file($this->iconFile($row['icon'])) ? $row['icon'] : (self::FAMILY_ICONS[$row['family']] ?? 'goal');
    }

    private function iconFile(string $icon): string
    {
        return $this->publicDir.'/'.sprintf(self::ICON_PATH, basename($icon));
    }

    /** Inner markup of the SVG (viewBox 0 0 24 24, currentColor), inlined by the apps to colour it by family. */
    private function iconMarkup(string $icon): string
    {
        $svg = (string) @file_get_contents($this->iconFile($icon));

        return preg_match('#<svg[^>]*>(.*)</svg>#s', $svg, $m) === 1 ? preg_replace('#<title>.*?</title>#s', '', $m[1]) : '';
    }

    /** @return list<string> seasons with a frozen copy of thresholds, most recent first */
    public function computedSeasons(): array
    {
        return array_map('strval', $this->connection->fetchFirstColumn('SELECT season FROM kp_medal_season ORDER BY season DESC'));
    }

    /**
     * Edition from the admin (§ 9.3): only the editable fields, journalled with their previous values (MED-43).
     *
     * @param array<string, mixed> $changes
     *
     * @return array<string, mixed>|null the updated row, null if unknown
     *
     * @throws \InvalidArgumentException on an invalid value
     */
    public function update(string $code, array $changes, string $user): ?array
    {
        $before = $this->find($code);
        if ($before === null) {
            return null;
        }
        $values = array_intersect_key($changes, array_flip(self::EDITABLE));
        $this->validate($values);
        if ($values === []) {
            return $before;
        }
        foreach (['published_app2', 'published_app3'] as $flag) {
            if (array_key_exists($flag, $values)) {
                $values[$flag] = (int) (bool) $values[$flag];
            }
        }
        $this->connection->update('kp_medal_catalog', [...$values, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user], ['code' => $code]);
        $previous = array_intersect_key($before, $values);
        $this->connection->insert('kp_journal', [
            'Dates' => date('Y-m-d H:i:s'),
            'Users' => $user,
            'Actions' => self::JOURNAL_ACTION,
            'Journal' => mb_substr(sprintf('%s : %s → %s', $code, json_encode($previous, JSON_UNESCAPED_UNICODE), json_encode($values, JSON_UNESCAPED_UNICODE)), 0, 60000),
        ]);
        $this->rows = null;

        return $this->find($code);
    }

    /** @param array<string, mixed> $values */
    private function validate(array $values): void
    {
        foreach (['name_fr', 'name_en', 'explanation_fr', 'explanation_en'] as $text) {
            if (array_key_exists($text, $values) && (!is_string($values[$text]) || trim($values[$text]) === '' || mb_strlen($values[$text]) > ($text[0] === 'n' ? 80 : 500))) {
                throw new \InvalidArgumentException("Invalid $text");
            }
        }
        if (array_key_exists('status', $values) && !in_array($values['status'], self::STATUSES, true)) {
            throw new \InvalidArgumentException('Invalid status');
        }
        if (array_key_exists('sort_order', $values) && !is_int($values['sort_order'])) {
            throw new \InvalidArgumentException('Invalid sort_order');
        }
    }
}
