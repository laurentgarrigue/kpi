<?php

namespace App\Cms;

use Psr\Clock\ClockInterface;

/**
 * Albums « Articles » et « Pages » de la médiathèque (FEATURE_CMS.md § 5.3, CMS-14) : les médias utilisés par un
 * article (image de une, corps) ou une page (corps) y sont rangés, qu'ils viennent de la reprise WordPress ou de
 * l'éditeur d'app4. Un média déjà rangé dans un autre album (galerie…) n'est pas déplacé. Les deux albums sont créés
 * au premier besoin.
 */
final class ContentAlbums
{
    public const ARTICLES = 'articles';
    public const PAGES = 'pages';

    private const TITLES = [
        self::ARTICLES => ['Articles', 'News'],
        self::PAGES => ['Pages', 'Pages'],
    ];
    private const MEDIA_URL = '~' . MediaUrls::PUBLIC_PREFIX . '([^"\'\s?#<>]+)~';

    public function __construct(
        private readonly MediaRepository $media,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param self::ARTICLES|self::PAGES $album
     * @param list<string>               $bodies HTML des contenus (toutes langues)
     */
    public function collect(string $album, array $bodies, ?int $coverId = null): void
    {
        $ids = $this->media->idsOfPaths(self::mediaPaths($bodies));
        if ($coverId !== null) {
            $ids[] = $coverId;
        }
        if ($ids !== []) {
            $this->media->assignAlbumWhereNone(array_values(array_unique($ids)), $this->albumId($album));
        }
    }

    /**
     * Chemins (relatifs à content/) des médias de la médiathèque cités dans des contenus HTML.
     *
     * @param list<string> $bodies
     *
     * @return list<string>
     */
    public static function mediaPaths(array $bodies): array
    {
        preg_match_all(self::MEDIA_URL, implode("\n", $bodies), $matches);

        return array_values(array_unique(array_map('rawurldecode', $matches[1])));
    }

    private function albumId(string $slug): int
    {
        $album = $this->media->findAlbumBySlug($slug);
        if ($album !== null) {
            return (int) $album['id'];
        }
        [$titleFr, $titleEn] = self::TITLES[$slug];

        return $this->media->insertAlbum([
            'slug' => $slug,
            'title_fr' => $titleFr,
            'title_en' => $titleEn,
            'created_at' => $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s'),
        ]);
    }
}
