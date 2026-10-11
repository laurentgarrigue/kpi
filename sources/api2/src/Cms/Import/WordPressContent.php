<?php

namespace App\Cms\Import;

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
}
