<?php

namespace App\Cms;

/** Menu « Le kayak-polo », blocs d'accueil et redirections, réservés aux profils 1–2 (PAGE_CMS.md § 5). */
final class SiteEditor
{
    private const MENU_TARGETS = ['page', 'article', 'url'];
    private const HOME_KINDS = ['alert', 'featured', 'latest_news', 'partners'];
    private const ALERT_LEVELS = ['info', 'important'];
    private const MAX_FEATURED = 3;
    private const MAX_LATEST = 12;
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    public function __construct(
        private readonly SiteRepository $site,
        private readonly PageRepository $pages,
        private readonly ArticleRepository $articles,
        private readonly MediaRepository $media,
    ) {
    }

    /** @return list<array<string, mixed>> arbre du menu à deux niveaux, tel qu'édité */
    public function menu(): array
    {
        $items = $this->site->menuItems();
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] === null) {
                $tree[(int) $item['id']] = self::menuItem($item) + ['children' => []];
            }
        }
        foreach ($items as $item) {
            if ($item['parent_id'] !== null && isset($tree[(int) $item['parent_id']])) {
                $tree[(int) $item['parent_id']]['children'][] = self::menuItem($item);
            }
        }

        return array_values($tree);
    }

    /** @param mixed $input liste d'entrées `{ label_fr, label_en, target_kind, target_ref, children }` */
    public function saveMenu(mixed $input): void
    {
        $errors = new ValidationErrors();
        $items = [];
        foreach (is_array($input) ? $input : [] as $index => $item) {
            $entry = $this->menuEntry($item, "items.$index", $errors);
            $entry['children'] = [];
            foreach (is_array($item['children'] ?? null) ? $item['children'] : [] as $childIndex => $child) {
                $entry['children'][] = $this->menuEntry($child, "items.$index.children.$childIndex", $errors);
            }
            $items[] = $entry;
        }
        if (!is_array($input)) {
            $errors->add('items', 'invalid');
        }
        $errors->throwIfAny();
        $this->site->replaceMenu($items);
    }

    /** @return list<array<string, mixed>> */
    public function homeBlocks(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'kind' => $row['kind'],
            'position' => (int) $row['position'],
            'enabled' => (int) $row['enabled'] === 1,
            'config' => json_decode((string) $row['config'], true) ?: new \stdClass(),
        ], $this->site->homeBlocks());
    }

    /** @param mixed $input liste `{ id, enabled, config }` dans l'ordre voulu */
    public function saveHomeBlocks(mixed $input): void
    {
        $errors = new ValidationErrors();
        $known = array_column($this->site->homeBlocks(), 'kind', 'id');
        $blocks = [];
        foreach (is_array($input) ? $input : [] as $index => $block) {
            $id = (int) ($block['id'] ?? 0);
            if (!isset($known[$id])) {
                $errors->add("blocks.$index", 'invalid');
                continue;
            }
            $blocks[$id] = [
                'position' => $index + 1,
                'enabled' => (bool) ($block['enabled'] ?? false),
                'config' => $this->homeConfig((string) $known[$id], is_array($block['config'] ?? null) ? $block['config'] : [], "blocks.$index", $errors),
            ];
        }
        $errors->throwIfAny();
        foreach ($blocks as $id => $values) {
            $this->site->updateHomeBlock($id, $values);
        }
    }

    /** @return list<array<string, mixed>> */
    public function redirects(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'from_path' => $row['from_path'],
            'to_path' => $row['to_path'],
            'code' => (int) $row['code'],
            'hits' => (int) $row['hits'],
        ], $this->site->redirects());
    }

    /** @param array<string, mixed> $input */
    public function saveRedirect(array $input): int
    {
        $errors = new ValidationErrors();
        $from = trim((string) ($input['from_path'] ?? ''));
        $to = trim((string) ($input['to_path'] ?? ''));
        if (!self::isPath($from) || strlen($from) > 255) {
            $errors->add('from_path', 'invalid');
        }
        if ((!self::isPath($to) && !self::isHttpUrl($to)) || strlen($to) > 255 || $to === $from) {
            $errors->add('to_path', 'invalid');
        }
        $errors->throwIfAny();

        return $this->site->saveRedirect($from, $to);
    }

    public function deleteRedirect(int $id): bool
    {
        return $this->site->deleteRedirect($id);
    }

    // ---------------------------------------------------------------- privé

    /** @param array<string, mixed> $row */
    private static function menuItem(array $row): array
    {
        return [
            'label_fr' => $row['label_fr'],
            'label_en' => $row['label_en'],
            'target_kind' => $row['target_kind'],
            'target_ref' => $row['target_ref'],
        ];
    }

    /** @return array{label_fr: string, label_en: string, target_kind: string, target_ref: string} */
    private function menuEntry(mixed $item, string $field, ValidationErrors $errors): array
    {
        $item = is_array($item) ? $item : [];
        $label = $errors->maxLength("$field.label_fr", (string) ($item['label_fr'] ?? ''), 150);
        $kind = (string) ($item['target_kind'] ?? '');
        $ref = trim((string) ($item['target_ref'] ?? ''));
        if ($label === '') {
            $errors->add("$field.label_fr", 'required');
        }
        $validTarget = match ($kind) {
            'page' => ctype_digit($ref) && $this->pages->find((int) $ref) !== null,
            'article' => ctype_digit($ref) && $this->articles->find((int) $ref) !== null,
            'url' => self::isHttpUrl($ref) || self::isPath($ref),
            default => false,
        };
        if (!in_array($kind, self::MENU_TARGETS, true) || !$validTarget) {
            $errors->add("$field.target_ref", 'invalid');
        }

        return [
            'label_fr' => $label,
            'label_en' => $errors->maxLength("$field.label_en", (string) ($item['label_en'] ?? ''), 150),
            'target_kind' => $kind,
            'target_ref' => $ref,
        ];
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function homeConfig(string $kind, array $config, string $field, ValidationErrors $errors): array
    {
        return match ($kind) {
            'alert' => $this->alertConfig($config, $field, $errors),
            'featured' => $this->featuredConfig($config, $field, $errors),
            'latest_news' => ['count' => max(1, min(self::MAX_LATEST, (int) ($config['count'] ?? 3)))],
            'partners' => $this->partnersConfig($config, $field, $errors),
            default => [],
        };
    }

    /** @param array<string, mixed> $config */
    private function alertConfig(array $config, string $field, ValidationErrors $errors): array
    {
        $level = (string) ($config['level'] ?? 'info');
        if (!in_array($level, self::ALERT_LEVELS, true)) {
            $errors->add("$field.level", 'invalid');
        }
        $dates = [];
        foreach (['from', 'to'] as $bound) {
            $value = $config[$bound] ?? null;
            if ($value !== null && $value !== '' && preg_match(self::DATE_PATTERN, (string) $value) !== 1) {
                $errors->add("$field.$bound", 'invalid');
            }
            $dates[$bound] = $value === '' ? null : $value;
        }

        return [
            'level' => $level,
            'text_fr' => $errors->maxLength("$field.text_fr", (string) ($config['text_fr'] ?? ''), 500),
            'text_en' => $errors->maxLength("$field.text_en", (string) ($config['text_en'] ?? ''), 500),
        ] + $dates;
    }

    /** @param array<string, mixed> $config */
    private function featuredConfig(array $config, string $field, ValidationErrors $errors): array
    {
        $ids = array_values(array_unique(array_map('intval', is_array($config['articles'] ?? null) ? $config['articles'] : [])));
        if (count($ids) > self::MAX_FEATURED) {
            $errors->add("$field.articles", 'too_many');
        }
        foreach ($ids as $id) {
            if ($this->articles->find($id) === null) {
                $errors->add("$field.articles", 'invalid');
            }
        }

        return ['articles' => $ids];
    }

    /** @param array<string, mixed> $config */
    private function partnersConfig(array $config, string $field, ValidationErrors $errors): array
    {
        $partners = [];
        foreach (is_array($config['partners'] ?? null) ? $config['partners'] : [] as $index => $partner) {
            $name = trim((string) ($partner['name'] ?? ''));
            $url = trim((string) ($partner['url'] ?? ''));
            $mediaId = isset($partner['media_id']) && $partner['media_id'] !== null && $partner['media_id'] !== '' ? (int) $partner['media_id'] : null;
            if ($name === '') {
                $errors->add("$field.partners.$index.name", 'required');
            }
            if ($url !== '' && !self::isHttpUrl($url)) {
                $errors->add("$field.partners.$index.url", 'invalid');
            }
            if ($mediaId !== null && $this->media->find($mediaId) === null) {
                $errors->add("$field.partners.$index.media_id", 'invalid');
            }
            $partners[] = ['name' => $name, 'url' => $url, 'media_id' => $mediaId];
        }

        return ['partners' => $partners];
    }

    private static function isPath(string $value): bool
    {
        return preg_match('~^/(?!/)[^\s]*$~', $value) === 1;
    }

    private static function isHttpUrl(string $value): bool
    {
        return preg_match('~^https?://[^\s/$.?#][^\s]*$~i', $value) === 1;
    }
}
