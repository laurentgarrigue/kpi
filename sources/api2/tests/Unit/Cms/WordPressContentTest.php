<?php

namespace App\Tests\Unit\Cms;

use App\Cms\Import\WordPressContent;
use PHPUnit\Framework\TestCase;

/** CMS-09 : conversion du contenu WordPress repris. */
final class WordPressContentTest extends TestCase
{
    public function testCms09SplitsWpMultilangTexts(): void
    {
        self::assertSame(['fr' => 'Bonjour', 'en' => 'Hello'], WordPressContent::splitLanguages('[:fr]Bonjour[:en]Hello[:]'));
        self::assertSame(['fr' => 'Seulement', 'en' => ''], WordPressContent::splitLanguages('Seulement'));
        self::assertSame(['fr' => '<p>a</p>', 'en' => ''], WordPressContent::splitLanguages('[:fr]<p>a</p>[:]'));
    }

    public function testCms09AddsParagraphsLikeTheClassicEditor(): void
    {
        self::assertSame("<p>Premier<br>\nligne 2</p>\n<p>Second</p>", WordPressContent::autop("Premier\nligne 2\n\nSecond"));
        self::assertSame("<p>Texte</p>\n<ul>\n<li>a</li>\n</ul>\n<p>Fin</p>", WordPressContent::autop("Texte\n<ul><li>a</li></ul>\nFin"), 'les blocs ne sont pas enveloppés');
        self::assertSame('<h2>Titre</h2>', WordPressContent::autop('<h2>Titre</h2>'));
    }

    public function testCms09ReportsShortcodesAndReadsGalleries(): void
    {
        self::assertSame(['gallery', 'ninja_form'], WordPressContent::shortcodes('a [gallery ids="1,2"] b [ninja_form id=19] [gallery]'));
        self::assertSame([12, 13, 14], WordPressContent::galleryIds('[gallery columns="3" ids="12, 13,14"]'));
    }

    public function testCms09BuildsWordPressPermalinks(): void
    {
        $post = ['ID' => 123, 'post_name' => 'finale', 'post_date' => '2024-06-02 10:00:00'];

        self::assertSame('/2024/06/finale/', WordPressContent::permalink('/%year%/%monthnum%/%postname%/', $post));
        self::assertSame('/finale/', WordPressContent::permalink('/%postname%/', $post));
        self::assertSame('/?p=123', WordPressContent::permalink('', $post));
    }

    public function testQP48MapsNinjaFormsFieldTypes(): void
    {
        self::assertSame('text', WordPressContent::ninjaFieldType('textbox'));
        self::assertSame('select', WordPressContent::ninjaFieldType('listselect'));
        self::assertSame('checkbox', WordPressContent::ninjaFieldType('listcheckbox'));
        self::assertNull(WordPressContent::ninjaFieldType('submit'));
        self::assertNull(WordPressContent::ninjaFieldType('html'));
    }
}
