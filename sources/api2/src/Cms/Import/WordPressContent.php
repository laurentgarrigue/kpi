<?php

namespace App\Cms\Import;

use App\Cms\CmsRules;

/**
 * Conversion du contenu WordPress (FEATURE_CMS.md § 8) : textes WP Multilang `[:fr]…[:en]…[:]`, paragraphes
 * automatiques de l'éditeur classique (wpautop), shortcodes, permaliens. Fonctions pures, testées seules.
 */
final class WordPressContent
{
    private const BLOCK_TAGS = 'table|thead|tfoot|caption|col|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|form|map|area|blockquote|address|math|style|p|h[1-6]|hr|fieldset|legend|section|article|aside|hgroup|header|footer|nav|figure|figcaption|details|menu|summary|iframe';

    /**
     * Texte WP Multilang éclaté par langue ; un texte sans balise de langue est français.
     *
     * @return array{fr: string, en: string}
     */
    public static function splitLanguages(string $text): array
    {
        if (!str_contains($text, '[:')) {
            return ['fr' => trim($text), 'en' => ''];
        }
        $parts = ['fr' => '', 'en' => ''];
        if (preg_match_all('/\[:([a-z]{2})\](.*?)(?=\[:[a-z]{0,2}\]|$)/s', $text, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                if (isset($parts[$match[1]])) {
                    $parts[$match[1]] .= $match[2];
                }
            }
        }

        return ['fr' => trim($parts['fr']), 'en' => trim($parts['en'])];
    }

    /** Paragraphes automatiques de l'éditeur classique (version simplifiée de wpautop). */
    public static function autop(string $content): string
    {
        $content = str_replace(["\r\n", "\r"], "\n", trim($content));
        if ($content === '') {
            return '';
        }
        // Block tags start a new paragraph.
        $content = (string) preg_replace('!(<(?:' . self::BLOCK_TAGS . ')[\s/>])!', "\n\n$1", $content);
        $content = (string) preg_replace('!(</(?:' . self::BLOCK_TAGS . ')>)!', "$1\n\n", $content);
        $paragraphs = preg_split('/\n\s*\n/', $content) ?: [];

        $html = '';
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }
            if (preg_match('!^</?(?:' . self::BLOCK_TAGS . ')[\s/>]!', $paragraph) === 1) {
                $html .= $paragraph . "\n";
                continue;
            }
            $html .= '<p>' . str_replace("\n", "<br>\n", $paragraph) . "</p>\n";
        }

        return trim($html);
    }

    /** @return list<string> shortcodes restants (`gallery`, `ninja_form`, `table`…), sans doublon */
    public static function shortcodes(string $content): array
    {
        preg_match_all('/\[([a-z_][a-z0-9_-]*)(?:\s[^\]]*)?\]/i', $content, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[1])));
    }

    /** @return list<int> identifiants des images d'un shortcode `[gallery ids="1,2,3"]` */
    public static function galleryIds(string $shortcode): array
    {
        if (preg_match('/ids=["\']?([\d,\s]+)/', $shortcode, $match) !== 1) {
            return [];
        }

        return array_values(array_filter(array_map('intval', explode(',', $match[1]))));
    }

    /**
     * Chemin d'un article selon la structure de permaliens WordPress (`/%year%/%monthnum%/%postname%/`…).
     *
     * @param array{ID: int|string, post_name: string, post_date: string} $post
     */
    public static function permalink(string $structure, array $post): string
    {
        if (trim($structure) === '') {
            return '/?p=' . $post['ID'];
        }
        $date = new \DateTimeImmutable($post['post_date']);

        return strtr($structure, [
            '%year%' => $date->format('Y'),
            '%monthnum%' => $date->format('m'),
            '%day%' => $date->format('d'),
            '%hour%' => $date->format('H'),
            '%minute%' => $date->format('i'),
            '%second%' => $date->format('s'),
            '%postname%' => $post['post_name'],
            '%post_id%' => (string) $post['ID'],
            '%category%' => 'non-classe',
            '%author%' => 'admin',
        ]);
    }

    /** Type de champ KPI d'un champ Ninja Forms ; null pour un champ sans saisie (bouton, HTML, séparateur). */
    public static function ninjaFieldType(string $type): ?string
    {
        return match ($type) {
            'textbox', 'firstname', 'lastname', 'address', 'city', 'zip', 'liststate', 'listcountry' => 'text',
            'textarea' => 'textarea',
            'email' => 'email',
            'phone' => 'phone',
            'number', 'quantity' => 'number',
            'date' => 'date',
            'listselect' => 'select',
            'listradio' => 'radio',
            'listcheckbox', 'listmultiselect' => 'checkbox',
            'checkbox' => 'checkbox',
            default => null,
        };
    }

    /**
     * Expression des fichiers de `wp-content/uploads` du site, quel que soit le répertoire d'installation de WordPress
     * (`/wordpress/wp-content/…` sur kayak-polo.info) : URL absolue du site (avec ou sans `www`, schéma relatif) ou
     * chemin relatif dans un attribut. Groupe 1 : chemin dans uploads.
     */
    public static function uploadsPattern(string $siteUrl): string
    {
        $host = preg_quote(preg_replace('~^www\.~', '', (string) parse_url($siteUrl, PHP_URL_HOST)) ?? '', '~');

        return '~(?:(?:https?:)?//(?:www\.)?' . $host . '|(?<=["\'(=\s]))(?:/[\w.-]+)*?/wp-content/uploads/([^"\'\s?#<>]+)~i';
    }

    /**
     * Catégorie du site d'une catégorie WordPress : correspondance explicite (`--category-map`, par slug WordPress),
     * sinon même nom ou même slug (accents et casse ignorés), sinon null.
     *
     * @param array{name: string, slug: string}                 $wordpress
     * @param list<array{slug: string, label_fr: string}>       $categories catégories du site
     * @param array<string, string>                             $map        slug WordPress → slug du site
     */
    public static function categoryFor(array $wordpress, array $categories, array $map): ?string
    {
        $slugs = array_column($categories, 'slug');
        if (isset($map[$wordpress['slug']]) && in_array($map[$wordpress['slug']], $slugs, true)) {
            return $map[$wordpress['slug']];
        }
        $candidates = [CmsRules::slugify(self::splitLanguages($wordpress['name'])['fr']), $wordpress['slug']];
        foreach ($categories as $category) {
            if (array_intersect($candidates, [$category['slug'], CmsRules::slugify($category['label_fr'])]) !== []) {
                return $category['slug'];
            }
        }

        return null;
    }

    /**
     * Catégorie par défaut de WordPress (« Non classé ») : ignorée sans avertissement.
     *
     * @param array{name: string, slug: string} $wordpress
     */
    public static function isDefaultCategory(array $wordpress): bool
    {
        return in_array($wordpress['slug'], ['uncategorized', 'non-classe'], true);
    }

    /**
     * Retire du HTML (déjà assaini) la première image, ou celle d'adresse `$src`, avec le lien, la légende et le
     * paragraphe qui ne contenaient qu'elle (CMS-15).
     *
     * @return array{0: ?string, 1: string} adresse de l'image retirée (null si aucune) et HTML restant
     */
    public static function extractImage(string $html, ?string $src = null): array
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body><div id="kpi-root">' . $html . '</div></body>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $document->getElementById('kpi-root');
        $image = null;
        foreach ($root?->getElementsByTagName('img') ?? [] as $candidate) {
            if ($src === null || $candidate->getAttribute('src') === $src) {
                $image = $candidate;
                break;
            }
        }
        if ($root === null || $image === null) {
            return [null, $html];
        }

        $removed = $image;
        while ($removed->parentNode instanceof \DOMElement && $removed->parentNode !== $root && self::onlyHolds($removed->parentNode, $removed)) {
            $removed = $removed->parentNode;
        }
        // « texte <img> texte » : a single space is kept between the remaining texts.
        if ($removed->previousSibling instanceof \DOMText && $removed->nextSibling instanceof \DOMText && preg_match('/\s$/', $removed->previousSibling->data) === 1) {
            $removed->nextSibling->data = ltrim($removed->nextSibling->data);
        }
        $removed->parentNode?->removeChild($removed);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return [$image->getAttribute('src'), trim($output)];
    }

    /** L'élément ne contient rien d'autre que `$child`, des blancs, des sauts de ligne et une légende. */
    private static function onlyHolds(\DOMElement $element, \DOMNode $child): bool
    {
        foreach ($element->childNodes as $node) {
            $empty = $node === $child
                || ($node instanceof \DOMText && trim($node->data) === '')
                || ($node instanceof \DOMElement && in_array($node->tagName, ['br', 'figcaption'], true));
            if (!$empty) {
                return false;
            }
        }

        return true;
    }
}
