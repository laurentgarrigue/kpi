<?php

namespace App\Pdf;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TimeoutExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HTML → PDF through Gotenberg's Chromium route (PDF_GOTENBERG.md § 3 and § 4). Gotenberg runs in its own container on
 * the internal network of the stack; every resource of the page is sent with it, Chromium has no network (§ 9).
 */
class GotenbergClient
{
    private const ROUTE = '/forms/chromium/convert/html';

    /** A4, in inches: the unit every Gotenberg 8 version accepts. */
    private const A4_SHORT_SIDE = '8.27';
    private const A4_LONG_SIDE = '11.7';
    private const MARGIN_SIDE = '0.39';
    private const MARGIN_TOP = '0.39';
    private const MARGIN_BOTTOM = '0.6';
    /** Room for the sponsor of the competition above the footer line (§ 8.4.1). */
    private const MARGIN_BOTTOM_WITH_SPONSOR = '1.05';

    /** Below Gotenberg's own --api-timeout, so that api2 answers 504 itself instead of a dangling worker (§ 9). */
    private const TIMEOUT_SECONDS = 55;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(GOTENBERG_URL)%')]
        private readonly string $baseUrl,
        #[Autowire('%env(default::GOTENBERG_USER)%')]
        private readonly ?string $user,
        #[Autowire('%env(default::GOTENBERG_PASSWORD)%')]
        private readonly ?string $password,
    ) {
    }

    /**
     * @param array<string, string> $files       other files of the page (fonts, images, stylesheets), by name
     * @param bool                  $withSponsor the footer carries the sponsor of the competition
     *
     * @throws PdfRenderingFailed
     */
    public function render(string $html, string $footer, array $files, bool $landscape, bool $withSponsor = false): string
    {
        $fields = [
            ['files' => new DataPart($html, 'index.html', 'text/html')],
            ['files' => new DataPart($footer, 'footer.html', 'text/html')],
        ];
        foreach ($files as $name => $content) {
            $fields[] = ['files' => new DataPart($content, $name)];
        }
        foreach ([
            'paperWidth' => $landscape ? self::A4_LONG_SIDE : self::A4_SHORT_SIDE,
            'paperHeight' => $landscape ? self::A4_SHORT_SIDE : self::A4_LONG_SIDE,
            'marginTop' => self::MARGIN_TOP,
            'marginBottom' => $withSponsor ? self::MARGIN_BOTTOM_WITH_SPONSOR : self::MARGIN_BOTTOM,
            'marginLeft' => self::MARGIN_SIDE,
            'marginRight' => self::MARGIN_SIDE,
            'printBackground' => 'true',
        ] as $name => $value) {
            $fields[] = [$name => $value];
        }
        $form = new FormDataPart($fields);

        $options = [
            'headers' => $form->getPreparedHeaders()->toArray(),
            'body' => $form->bodyToIterable(),
            'timeout' => self::TIMEOUT_SECONDS,
            'max_duration' => self::TIMEOUT_SECONDS,
        ];
        if ($this->user !== null && $this->user !== '') {
            $options['auth_basic'] = [$this->user, (string) $this->password];
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->baseUrl, '/') . self::ROUTE, $options);
            if ($response->getStatusCode() !== 200) {
                throw PdfRenderingFailed::unavailable();
            }

            return $response->getContent();
        } catch (TimeoutExceptionInterface $e) {
            throw PdfRenderingFailed::timeout($e);
        } catch (ExceptionInterface $e) {
            throw PdfRenderingFailed::unavailable($e);
        }
    }
}
