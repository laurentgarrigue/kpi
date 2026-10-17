<?php

namespace App\Cms;

/**
 * Filtre par liste blanche du HTML des contenus éditoriaux (FEATURE_CMS.md § 3.2, CMS-02), appliqué à l'écriture.
 *
 * Écrit sur ext-dom plutôt qu'avec symfony/html-sanitizer pour n'ajouter aucune dépendance. Règles :
 * - éléments autorisés conservés avec leurs seuls attributs autorisés ; h1 → h2, h5/h6 → h4, b → strong, i → em ;
 * - éléments dangereux supprimés avec leur contenu (script, style, formulaires, objets, SVG…) ;
 * - autres éléments (div, span, font…) remplacés par leur contenu ;
 * - liens : http(s), mailto, tel ou chemins relatifs seulement ; `target="_blank"` reçoit `rel="noopener noreferrer"` ;
 * - images : http(s) ou chemin relatif ; vidéos : iframes YouTube / Vimeo normalisées (youtube-nocookie), les autres
 *   iframes sont supprimées.
 */
final class HtmlSanitizer
{
    private const ALLOWED = [
        'p' => [], 'br' => [], 'hr' => [], 'h2' => [], 'h3' => [], 'h4' => [],
        'strong' => [], 'em' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [],
        'ul' => [], 'ol' => ['start'], 'li' => [], 'blockquote' => [],
        'a' => ['href', 'title', 'target'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'thead' => [], 'tbody' => [], 'tr' => [], 'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
        'iframe' => ['src', 'title'],
    ];

    private const RENAMED = ['h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4', 'b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's'];

    private const DROPPED_WITH_CONTENT = [
        'script', 'style', 'noscript', 'template', 'object', 'embed', 'applet', 'form', 'input', 'button', 'select',
        'textarea', 'option', 'svg', 'math', 'link', 'meta', 'title', 'head', 'base', 'frame', 'frameset', 'audio', 'video',
    ];

    private const INTEGER_ATTRIBUTES = ['width', 'height', 'colspan', 'rowspan', 'start'];

    private const YOUTUBE = '~^(?:https?:)?//(?:www\.)?(?:youtube(?:-nocookie)?\.com/(?:embed/|watch\?v=)|youtu\.be/)([A-Za-z0-9_-]{6,20})~';
    private const VIMEO = '~^(?:https?:)?//(?:player\.)?vimeo\.com/(?:video/)?(\d{4,12})~';

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body><div id="kpi-root">' . $html . '</div></body>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('kpi-root');
        if ($root === null) {
            return '';
        }
        $this->cleanChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    /** Convertit une URL de vidéo YouTube / Vimeo en URL d'intégration normalisée, ou null. */
    public static function videoEmbedUrl(string $url): ?string
    {
        if (preg_match(self::YOUTUBE, $url, $match) === 1) {
            return 'https://www.youtube-nocookie.com/embed/' . $match[1];
        }
        if (preg_match(self::VIMEO, $url, $match) === 1) {
            return 'https://player.vimeo.com/video/' . $match[1];
        }

        return null;
    }

    private function cleanChildren(\DOMNode $parent): void
    {
        // The list changes while cleaning: iterate over a copy.
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof \DOMElement) {
                $this->cleanElement($child);
            } elseif (!$child instanceof \DOMText) {
                $parent->removeChild($child); // comments, processing instructions
            }
        }
    }

    private function cleanElement(\DOMElement $element): void
    {
        $tag = strtolower($element->tagName);
        if (in_array($tag, self::DROPPED_WITH_CONTENT, true)) {
            $element->parentNode?->removeChild($element);

            return;
        }
        if (isset(self::RENAMED[$tag])) {
            $element = $this->rename($element, self::RENAMED[$tag]);
            $tag = self::RENAMED[$tag];
        }
        if (!isset(self::ALLOWED[$tag])) {
            $this->cleanChildren($element);
            $this->unwrap($element);

            return;
        }

        $this->filterAttributes($element, self::ALLOWED[$tag]);
        if (!$this->checkUrls($element, $tag)) {
            return;
        }
        $this->cleanChildren($element);
    }

    /** @param list<string> $allowed */
    private function filterAttributes(\DOMElement $element, array $allowed): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);
            if (!in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->name);
            } elseif (in_array($name, self::INTEGER_ATTRIBUTES, true) && preg_match('/^\d{1,4}$/', $value) !== 1) {
                $element->removeAttribute($attribute->name);
            } elseif ($name === 'scope' && !in_array($value, ['col', 'row', 'colgroup', 'rowgroup'], true)) {
                $element->removeAttribute($attribute->name);
            }
        }
    }

    /** Vérifie les URL des liens, images et vidéos ; renvoie false si l'élément a été supprimé. */
    private function checkUrls(\DOMElement $element, string $tag): bool
    {
        if ($tag === 'a') {
            $href = $element->getAttribute('href');
            if ($href !== '' && !self::isSafeUrl($href, ['http', 'https', 'mailto', 'tel'])) {
                $element->removeAttribute('href');
            }
            if ($element->getAttribute('target') !== '') {
                $element->setAttribute('target', '_blank');
                $element->setAttribute('rel', 'noopener noreferrer');
            }
        }
        if ($tag === 'img' && !self::isSafeUrl($element->getAttribute('src'), ['http', 'https'])) {
            $element->parentNode?->removeChild($element);

            return false;
        }
        if ($tag === 'iframe') {
            $embed = self::videoEmbedUrl($element->getAttribute('src'));
            if ($embed === null) {
                $element->parentNode?->removeChild($element);

                return false;
            }
            $element->setAttribute('src', $embed);
            $element->setAttribute('allowfullscreen', '');
            while ($element->firstChild !== null) {
                $element->removeChild($element->firstChild);
            }
        }

        return true;
    }

    /** @param list<string> $schemes */
    private static function isSafeUrl(string $url, array $schemes): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        // Relative paths and anchors (« /competitions/… », « #section »), but not protocol-relative URLs.
        if (preg_match('~^(/(?!/)|#)~', $url) === 1) {
            return true;
        }
        // Control characters and spaces can hide a scheme (« java\tscript: »).
        $normalized = strtolower(preg_replace('/[\x00-\x20]+/', '', $url) ?? '');
        $scheme = parse_url($normalized, PHP_URL_SCHEME);

        return is_string($scheme) && in_array($scheme, $schemes, true);
    }

    private function rename(\DOMElement $element, string $tag): \DOMElement
    {
        $renamed = $element->ownerDocument->createElement($tag);
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $renamed->setAttribute($attribute->name, $attribute->value);
        }
        while ($element->firstChild !== null) {
            $renamed->appendChild($element->firstChild);
        }
        $element->parentNode?->replaceChild($renamed, $element);

        return $renamed;
    }

    private function unwrap(\DOMElement $element): void
    {
        $parent = $element->parentNode;
        if ($parent === null) {
            return;
        }
        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }
        $parent->removeChild($element);
    }
}
