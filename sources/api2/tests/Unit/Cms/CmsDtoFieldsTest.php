<?php

namespace App\Tests\Unit\Cms;

use App\Cms\Dto\AlbumView;
use App\Cms\Dto\CategoryView;
use App\Cms\Dto\HomeBlock;
use App\Cms\Dto\MediaView;
use App\Cms\Dto\MenuEntry;
use App\Cms\Dto\NewsArticle;
use App\Cms\Dto\NewsSummary;
use App\Cms\Dto\PageView;
use PHPUnit\Framework\TestCase;

/** CMS-10 : chaque DTO public expose exactement ses champs ; aucun compte utilisateur. */
final class CmsDtoFieldsTest extends TestCase
{
    public function testCms10PublicDtosExposeExactlyTheirFields(): void
    {
        $media = MediaView::fromRow(['path' => '2026/10/a.jpg', 'mime' => 'image/jpeg', 'width' => 800, 'height' => 600, 'alt_fr' => 'A', 'alt_en' => '', 'updated_at' => '2026-10-11 10:00:00'], 'en');
        self::assertSame(['url', 'mime', 'alt', 'width', 'height', 'variants'], array_keys($media->jsonSerialize()));
        self::assertSame('A', $media->alt, 'texte alternatif anglais vide → français');
        self::assertSame([320, 768], array_column($media->variants, 'width'));

        self::assertSame(['slug', 'label'], array_keys((new CategoryView('a', 'A'))->jsonSerialize()));
        self::assertSame(['slug', 'title', 'lead', 'cover', 'published_at', 'categories'], array_keys((new NewsSummary('a', 'A', '', null, '2026-01-01T00:00:00+01:00', []))->jsonSerialize()));
        self::assertSame(
            ['slug', 'title', 'lead', 'body', 'cover', 'published_at', 'updated_at', 'signature', 'categories', 'links', 'seo', 'previous', 'next', 'preview'],
            array_keys((new NewsArticle('a', 'A', '', '', null, null, '', 'CNA', [], [], ['title' => '', 'description' => ''], null, null, false))->jsonSerialize()),
        );
        self::assertSame(['slug', 'path', 'title', 'body', 'parents', 'children', 'seo', 'preview'], array_keys((new PageView('a', '/a', 'A', '', [], [], ['title' => '', 'description' => ''], false))->jsonSerialize()));
        self::assertSame(['label', 'path', 'external', 'children'], array_keys((new MenuEntry('A', '/a', false, []))->jsonSerialize()));
        self::assertSame(['kind', 'level', 'text'], array_keys((new HomeBlock('alert', ['level' => 'info', 'text' => 'x']))->jsonSerialize()));
        self::assertSame(['slug', 'title', 'media'], array_keys((new AlbumView('a', 'A', []))->jsonSerialize()));
    }
}
