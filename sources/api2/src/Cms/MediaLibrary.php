<?php

namespace App\Cms;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Médiathèque éditoriale (FEATURE_CMS.md § 5.3, PAGE_CMS.md § 4, CMS-04) : envoi, remplacement à URL constante,
 * variantes WebP, albums. Les images sont réencodées avec GD, ce qui supprime leurs métadonnées (EXIF : position GPS,
 * appareil) ; l'orientation EXIF est appliquée avant. Fichiers sous `{racine}/AAAA/MM/`.
 */
final class MediaLibrary
{
    public const MAX_BYTES = 20 * 1024 * 1024;
    public const PAGE_SIZE = 48;
    public const ALLOWED_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    private const MAX_PIXELS = 40_000_000;
    private const JPEG_QUALITY = 88;
    private const WEBP_QUALITY = 82;
    private const PROCESSING_MEMORY = '768M';

    public function __construct(
        private readonly MediaRepository $media,
        private readonly ClockInterface $clock,
        #[Autowire('%cms_media_root%')]
        private readonly string $root,
    ) {
    }

    /** @return array<string, mixed> fiche d'un média pour app4 */
    public static function summary(array $row): array
    {
        $path = (string) $row['path'];
        $version = substr(md5((string) $row['updated_at']), 0, 8);

        return [
            'id' => (int) $row['id'],
            'url' => MediaUrls::url($path) . '?v=' . $version,
            'thumbnail' => MediaUrls::isImage((string) $row['mime']) && MediaUrls::variantWidths($row['width'] === null ? null : (int) $row['width']) !== []
                ? MediaUrls::url(MediaUrls::variantPath($path, MediaUrls::VARIANT_WIDTHS[0])) . '?v=' . $version
                : MediaUrls::url($path) . '?v=' . $version,
            'name' => basename($path),
            'mime' => $row['mime'],
            'width' => $row['width'] === null ? null : (int) $row['width'],
            'height' => $row['height'] === null ? null : (int) $row['height'],
            'size' => (int) $row['size'],
            'alt_fr' => $row['alt_fr'],
            'alt_en' => $row['alt_en'],
            'credit' => $row['credit'],
            'album_id' => $row['album_id'] === null ? null : (int) $row['album_id'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /** @return array{items: list<array<string, mixed>>, page: int, pages: int, total: int} */
    public function list(?string $type, ?int $albumId, int $page): array
    {
        $total = $this->media->count($type, $albumId);

        return [
            'items' => array_map(self::summary(...), $this->media->list($type, $albumId, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE)),
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'total' => $total,
        ];
    }

    /**
     * @param array<string, mixed> $meta alt_fr, alt_en, credit, album_id
     *
     * @return array<string, mixed> fiche du média créé
     */
    public function upload(UploadedFile $file, array $meta, string $userCode): array
    {
        $mime = $this->checkFile($file);
        $errors = new ValidationErrors();
        $values = $this->metadata($meta, $errors);
        $errors->throwIfAny();

        $path = $this->uniquePath($file->getClientOriginalName(), self::ALLOWED_TYPES[$mime]);
        $dimensions = $this->store($file->getPathname(), $mime, $path);
        $now = $this->now();
        $id = $this->media->insert($values + $dimensions + [
            'path' => $path,
            'mime' => $mime,
            'size' => (int) filesize($this->absolute($path)),
            'uploaded_by' => $userCode,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return self::summary((array) $this->media->find($id));
    }

    /** Remplace le fichier en gardant l'URL (même chemin, même type). */
    public function replace(int $id, UploadedFile $file): ?array
    {
        $row = $this->media->find($id);
        if ($row === null) {
            return null;
        }
        $mime = $this->checkFile($file);
        if ($mime !== $row['mime']) {
            throw (new ValidationErrors())->add('file', 'type_mismatch');
        }
        $this->removeVariants((string) $row['path'], $row['width'] === null ? null : (int) $row['width']);
        $dimensions = $this->store($file->getPathname(), $mime, (string) $row['path']);
        $this->media->update($id, $dimensions + ['size' => (int) filesize($this->absolute((string) $row['path'])), 'updated_at' => $this->now()]);

        return self::summary((array) $this->media->find($id));
    }

    /** @param array<string, mixed> $meta */
    public function updateMetadata(int $id, array $meta): ?array
    {
        if ($this->media->find($id) === null) {
            return null;
        }
        $errors = new ValidationErrors();
        $values = $this->metadata($meta, $errors);
        $errors->throwIfAny();
        $this->media->update($id, $values + ['updated_at' => $this->now()]);

        return self::summary((array) $this->media->find($id));
    }

    /** Supprime un média inutilisé ; false s'il n'existe pas. */
    public function delete(int $id): bool
    {
        $row = $this->media->find($id);
        if ($row === null) {
            return false;
        }
        if ($this->media->isUsed($id, (string) $row['path'])) {
            throw (new ValidationErrors())->add('media', 'in_use');
        }
        $this->removeVariants((string) $row['path'], $row['width'] === null ? null : (int) $row['width']);
        @unlink($this->absolute((string) $row['path']));
        $this->media->delete($id);

        return true;
    }

    /** @return list<array<string, mixed>> */
    public function albums(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'title_fr' => $row['title_fr'],
            'title_en' => $row['title_en'],
            'cover_media_id' => $row['cover_media_id'] === null ? null : (int) $row['cover_media_id'],
            'media_count' => (int) $row['media_count'],
        ], $this->media->albums());
    }

    /** @param array<string, mixed> $input */
    public function saveAlbum(?int $id, array $input): int
    {
        $errors = new ValidationErrors();
        $title = $errors->maxLength('title_fr', (string) ($input['title_fr'] ?? ''), 255);
        if ($title === '') {
            $errors->add('title_fr', 'required');
        }
        $slug = trim((string) ($input['slug'] ?? '')) ?: CmsRules::slugify($title);
        if (!CmsRules::isValidSlug($slug)) {
            $errors->add('slug', 'invalid');
        } elseif ($this->media->albumSlugExists($slug, $id)) {
            $errors->add('slug', 'taken');
        }
        $cover = isset($input['cover_media_id']) && $input['cover_media_id'] !== null ? (int) $input['cover_media_id'] : null;
        if ($cover !== null && $this->media->find($cover) === null) {
            $errors->add('cover_media_id', 'invalid');
        }
        $errors->throwIfAny();

        $values = ['slug' => $slug, 'title_fr' => $title, 'title_en' => trim((string) ($input['title_en'] ?? '')), 'cover_media_id' => $cover];
        if ($id === null) {
            return $this->media->insertAlbum($values + ['created_at' => $this->now()]);
        }
        $this->media->updateAlbum($id, $values);

        return $id;
    }

    public function deleteAlbum(int $id): bool
    {
        if ($this->media->findAlbum($id) === null) {
            return false;
        }
        $this->media->deleteAlbum($id);

        return true;
    }

    // ---------------------------------------------------------------- privé

    /** @return string type MIME réel (contenu, pas l'extension) */
    private function checkFile(UploadedFile $file): string
    {
        $errors = new ValidationErrors();
        if (!$file->isValid() || $file->getSize() === false || $file->getSize() > self::MAX_BYTES) {
            throw $errors->add('file', 'too_large');
        }
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        if (!isset(self::ALLOWED_TYPES[$mime])) {
            throw $errors->add('file', 'type'); // SVG, HTML, executables…
        }

        return $mime;
    }

    /** @param array<string, mixed> $meta */
    private function metadata(array $meta, ValidationErrors $errors): array
    {
        $album = $meta['album_id'] ?? null;
        $albumId = $album === null || $album === '' ? null : (int) $album;
        if ($albumId !== null && $this->media->findAlbum($albumId) === null) {
            $errors->add('album_id', 'invalid');
        }

        return [
            'alt_fr' => $errors->maxLength('alt_fr', (string) ($meta['alt_fr'] ?? ''), 255),
            'alt_en' => $errors->maxLength('alt_en', (string) ($meta['alt_en'] ?? ''), 255),
            'credit' => $errors->maxLength('credit', (string) ($meta['credit'] ?? ''), 255),
            'album_id' => $albumId,
        ];
    }

    private function uniquePath(string $originalName, string $extension): string
    {
        $directory = $this->clock->now()->format('Y/m');
        $base = CmsRules::slugify(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'media';
        $base = substr($base, 0, 80);
        $candidate = sprintf('%s/%s.%s', $directory, $base, $extension);
        for ($suffix = 2; $this->media->pathExists($candidate) || is_file($this->absolute($candidate)); ++$suffix) {
            $candidate = sprintf('%s/%s-%d.%s', $directory, $base, $suffix, $extension);
        }

        return $candidate;
    }

    /** @return array{width: ?int, height: ?int} */
    private function store(string $source, string $mime, string $path): array
    {
        $target = $this->absolute($path);
        if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
            throw new \RuntimeException('Répertoire des médias non accessible en écriture : ' . dirname($target));
        }
        if (!MediaUrls::isImage($mime)) {
            if (!copy($source, $target)) {
                throw new \RuntimeException('Écriture du média impossible : ' . $target);
            }

            return ['width' => null, 'height' => null];
        }

        $size = getimagesize($source);
        if ($size === false || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw (new ValidationErrors())->add('file', $size === false ? 'type' : 'too_large');
        }
        $previousLimit = ini_set('memory_limit', self::PROCESSING_MEMORY);
        try {
            $image = $this->load($source, $mime);
            $this->save($image, $mime, $target);
            $width = imagesx($image);
            foreach (MediaUrls::variantWidths($width) as $variant) {
                $scaled = imagescale($image, $variant);
                if ($scaled !== false) {
                    imagewebp($scaled, $this->absolute(MediaUrls::variantPath($path, $variant)), self::WEBP_QUALITY);
                }
            }

            return ['width' => $width, 'height' => imagesy($image)];
        } finally {
            if ($previousLimit !== false) {
                ini_set('memory_limit', $previousLimit);
            }
        }
    }

    private function load(string $source, string $mime): \GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($source),
            'image/png' => imagecreatefrompng($source),
            default => imagecreatefromwebp($source),
        };
        if ($image === false) {
            throw (new ValidationErrors())->add('file', 'type');
        }
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($source)['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
            if ($angle !== 0) {
                $image = imagerotate($image, $angle, 0) ?: $image;
            }
        }

        return $image;
    }

    /** Réencode l'image : le fichier écrit ne contient plus aucune métadonnée. */
    private function save(\GdImage $image, string $mime, string $target): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $saved = match ($mime) {
            'image/jpeg' => imagejpeg($image, $target, self::JPEG_QUALITY),
            'image/png' => imagepng($image, $target),
            default => imagewebp($image, $target, self::WEBP_QUALITY),
        };
        if (!$saved) {
            throw new \RuntimeException('Écriture du média impossible : ' . $target);
        }
    }

    private function removeVariants(string $path, ?int $width): void
    {
        foreach (MediaUrls::variantWidths($width) as $variant) {
            @unlink($this->absolute(MediaUrls::variantPath($path, $variant)));
        }
    }

    private function absolute(string $path): string
    {
        return rtrim($this->root, '/') . '/' . $path;
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s');
    }
}
