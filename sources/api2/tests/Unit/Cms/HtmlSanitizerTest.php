<?php

namespace App\Tests\Unit\Cms;

use App\Cms\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** CMS-02 : le HTML des contenus est filtré par liste blanche à l'écriture. */
final class HtmlSanitizerTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function cases(): iterable
    {
        yield 'allowed formatting kept' => ['<h2>Titre</h2><p>Un <strong>gras</strong> et <em>italique</em>.</p>', '<h2>Titre</h2><p>Un <strong>gras</strong> et <em>italique</em>.</p>'];
        yield 'script removed with its content' => ['<p>a</p><script>alert(1)</script>', '<p>a</p>'];
        yield 'style element removed' => ['<style>p{}</style><p>a</p>', '<p>a</p>'];
        yield 'event handlers removed' => ['<p onclick="x()" onmouseover="y()">a</p>', '<p>a</p>'];
        yield 'style and class attributes removed' => ['<p style="color:red" class="big" id="x">a</p>', '<p>a</p>'];
        yield 'javascript link neutralised' => ['<a href="javascript:alert(1)">a</a>', '<a>a</a>'];
        yield 'obfuscated javascript link neutralised' => ["<a href=\"java\tscript:alert(1)\">a</a>", '<a>a</a>'];
        yield 'protocol-relative link refused' => ['<a href="//evil.example">a</a>', '<a>a</a>'];
        yield 'internal and external links kept' => ['<a href="/competitions/2026/N1H">a</a> <a href="https://ffck.org" title="FFCK">b</a>', '<a href="/competitions/2026/N1H">a</a> <a href="https://ffck.org" title="FFCK">b</a>'];
        yield 'new tab link gets noopener' => ['<a href="https://ffck.org" target="_top">a</a>', '<a href="https://ffck.org" target="_blank" rel="noopener noreferrer">a</a>'];
        yield 'mailto kept' => ['<a href="mailto:contact@kayak-polo.info">e</a>', '<a href="mailto:contact@kayak-polo.info">e</a>'];
        yield 'data image removed' => ['<p><img src="data:image/png;base64,AAA" alt="x"></p>', '<p></p>'];
        yield 'image kept with safe attributes' => ['<img src="/media/content/2026/10/a.jpg" alt="A" width="800" onerror="x()">', '<img src="/media/content/2026/10/a.jpg" alt="A" width="800">'];
        yield 'unknown iframe removed' => ['<iframe src="https://evil.example/x"></iframe><p>a</p>', '<p>a</p>'];
        yield 'youtube iframe normalised' => ['<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=1" onload="x()"></iframe>', '<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" allowfullscreen=""></iframe>'];
        yield 'vimeo iframe normalised' => ['<iframe src="https://player.vimeo.com/video/123456789"></iframe>', '<iframe src="https://player.vimeo.com/video/123456789" allowfullscreen=""></iframe>'];
        yield 'unknown wrappers unwrapped' => ['<div><span style="x">texte</span> <font>libre</font></div>', 'texte libre'];
        yield 'headings renamed' => ['<h1>A</h1><h6>B</h6><b>c</b><i>d</i>', '<h2>A</h2><h4>B</h4><strong>c</strong><em>d</em>'];
        yield 'form elements removed' => ['<form action="/x"><input name="a"><button>b</button></form><p>a</p>', '<p>a</p>'];
        yield 'comments removed' => ['<!-- secret --><p>a</p>', '<p>a</p>'];
        yield 'table kept with spans' => ['<table><tr><th scope="col" colspan="2">a</th></tr><tr><td rowspan="x">b</td></tr></table>', '<table><tr><th scope="col" colspan="2">a</th></tr><tr><td>b</td></tr></table>'];
        yield 'accents preserved' => ['<p>Équipe de France — été</p>', '<p>Équipe de France — été</p>'];
        yield 'empty' => ['   ', ''];
    }

    #[DataProvider('cases')]
    public function testCms02SanitizesByWhitelist(string $input, string $expected): void
    {
        self::assertSame($expected, (new HtmlSanitizer())->sanitize($input));
    }

    #[DataProvider('cases')]
    public function testCms02SanitizingIsIdempotent(string $input): void
    {
        $sanitizer = new HtmlSanitizer();
        $once = $sanitizer->sanitize($input);

        self::assertSame($once, $sanitizer->sanitize($once));
    }

    public function testAdmCms03TipTapOutputIsKeptAndStableOnReload(): void
    {
        $tiptap = '<h2>Titre</h2><p>Texte <strong>gras</strong> <em>italique</em> <u>souligné</u> <s>barré</s> '
            . '<a target="_blank" rel="noopener noreferrer nofollow" href="/competitions/2026/N1H">lien</a></p>'
            . '<ul><li><p>a</p></li></ul><blockquote><p>citation</p></blockquote>'
            . '<img src="/media/content/2026/10/photo.jpg" alt="Podium">'
            . '<table style="min-width: 50px"><colgroup><col style="min-width: 25px"><col style="min-width: 25px"></colgroup>'
            . '<tbody><tr><th colspan="1" rowspan="1"><p>A</p></th><th colspan="1" rowspan="1"><p>B</p></th></tr></tbody></table>'
            . '<div data-youtube-video=""><iframe width="640" height="480" allowfullscreen="true" autoplay="false" '
            . 'src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?controls=1" start="0"></iframe></div>';

        $sanitized = (new HtmlSanitizer())->sanitize($tiptap);

        self::assertSame(
            '<h2>Titre</h2><p>Texte <strong>gras</strong> <em>italique</em> <u>souligné</u> <s>barré</s> '
            . '<a target="_blank" href="/competitions/2026/N1H" rel="noopener noreferrer">lien</a></p>'
            . '<ul><li><p>a</p></li></ul><blockquote><p>citation</p></blockquote>'
            . '<img src="/media/content/2026/10/photo.jpg" alt="Podium">'
            . '<table><tbody><tr><th colspan="1" rowspan="1"><p>A</p></th><th colspan="1" rowspan="1"><p>B</p></th></tr></tbody></table>'
            . '<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ" allowfullscreen=""></iframe>',
            $sanitized,
        );
    }

    public function testCms02NormalisesVideoUrls(): void
    {
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', HtmlSanitizer::videoEmbedUrl('https://youtu.be/dQw4w9WgXcQ'));
        self::assertSame('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', HtmlSanitizer::videoEmbedUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        self::assertSame('https://player.vimeo.com/video/76979871', HtmlSanitizer::videoEmbedUrl('https://vimeo.com/76979871'));
        self::assertNull(HtmlSanitizer::videoEmbedUrl('https://dailymotion.com/video/x'));
    }
}
