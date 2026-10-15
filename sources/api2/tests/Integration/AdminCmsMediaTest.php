<?php

namespace App\Tests\Integration;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/** CMS-04 : médiathèque (contrôle du type réel, suppression des métadonnées, variantes WebP, remplacement à URL constante). */
final class AdminCmsMediaTest extends ApiTestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kpi-cms-media-' . bin2hex(random_bytes(4));
        $_SERVER['CMS_MEDIA_ROOT'] = $_ENV['CMS_MEDIA_ROOT'] = $this->root;
        parent::setUp();
        $this->beginWrites();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['CMS_MEDIA_ROOT'], $_ENV['CMS_MEDIA_ROOT']);
        if (is_dir($this->root)) {
            exec('rm -rf ' . escapeshellarg($this->root));
        }
        parent::tearDown();
    }

    public function testCms04ImageIsStrippedOfItsMetadataAndDeclinedInWebpVariants(): void
    {
        $media = $this->upload($this->jpegWithExif(1000, 600), 'Photo Été.JPG', ['alt_fr' => 'Une photo']);

        self::assertMatchesRegularExpression('~^/media/content/\d{4}/\d{2}/photo-ete\.jpg\?v=~', $media['url']);
        self::assertSame(['image/jpeg', 1000, 600, 'Une photo'], [$media['mime'], $media['width'], $media['height'], $media['alt_fr']]);
        $stored = $this->root . '/' . substr(parse_url($media['url'], PHP_URL_PATH), strlen('/media/content/'));
        self::assertFileExists($stored);
        self::assertStringNotContainsString('Exif', (string) file_get_contents($stored), 'EXIF supprimé (position GPS…)');
        self::assertStringNotContainsString('SECRET-GPS', (string) file_get_contents($stored));
        foreach ([320, 768] as $width) {
            self::assertFileExists(str_replace('.jpg', "-$width.webp", $stored));
        }
        self::assertFileDoesNotExist(str_replace('.jpg', '-1280.webp', $stored), 'pas de variante plus large que l\'original');
    }

    public function testCms04SvgAndDisguisedFilesAreRefused(): void
    {
        $svg = $this->temporaryFile('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        self::assertSame(['file' => 'type'], $this->upload($svg, 'logo.svg', expectedStatus: 400)['errors']);

        $html = $this->temporaryFile('<html><script>alert(1)</script></html>');
        self::assertSame(['file' => 'type'], $this->upload($html, 'image.jpg', expectedStatus: 400)['errors'], 'le type réel compte, pas l\'extension');
    }

    public function testCms04ReplacingAFileKeepsItsUrl(): void
    {
        $media = $this->upload($this->jpegWithExif(800, 400), 'affiche.jpg');
        $replaced = $this->upload($this->jpegWithExif(400, 200), 'autre.jpg', uri: '/admin/cms/media/' . $media['id'] . '/file', expectedStatus: 200);

        self::assertSame(strtok($media['url'], '?'), strtok($replaced['url'], '?'), 'même URL');
        self::assertSame(400, $replaced['width']);

        $png = $this->temporaryFile((string) $this->png());
        self::assertSame(['file' => 'type_mismatch'], $this->upload($png, 'x.png', uri: '/admin/cms/media/' . $media['id'] . '/file', expectedStatus: 400)['errors']);
    }

    public function testCms04MediaInUseCannotBeDeleted(): void
    {
        $this->requestAs('TADMIN', 'DELETE', '/admin/cms/media/9501', expectedStatus: 409);
        $this->requestAs('TEDIT', 'DELETE', '/admin/cms/media/9502', expectedStatus: 403);
        $this->requestAs('TADMIN', 'DELETE', '/admin/cms/media/9502');
    }

    /**
     * @param array<string, string> $fields
     *
     * @return array<mixed>
     */
    private function upload(string $path, string $name, array $fields = [], string $uri = '/admin/cms/media', int $expectedStatus = 201): array
    {
        $this->client->request('POST', $uri, $fields, ['file' => new UploadedFile($path, $name, null, null, true)], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TEDIT'),
        ]);
        $response = $this->client->getResponse();
        self::assertSame($expectedStatus, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true);
    }

    /** JPEG dont le segment APP1 contient de faux EXIF, comme une photo de téléphone. */
    private function jpegWithExif(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width, $height, (int) imagecolorallocate($image, 30, 120, 200));
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();
        $exif = "Exif\0\0SECRET-GPS-48.85N-2.35E";
        $segment = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;

        return $this->temporaryFile(substr($jpeg, 0, 2) . $segment . substr($jpeg, 2));
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function temporaryFile(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'kpi-upload-');
        file_put_contents($path, $content);

        return $path;
    }
}
