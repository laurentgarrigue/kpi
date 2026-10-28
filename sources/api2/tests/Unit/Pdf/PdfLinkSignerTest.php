<?php

namespace App\Tests\Unit\Pdf;

use App\Pdf\PdfLinkSigner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** PDF-AUTH-07: signed links expire, and any change of the path or of a parameter breaks them (§ 6.3). */
final class PdfLinkSignerTest extends TestCase
{
    private const PATH = '/pdf-signed/teams-groups';

    private MockClock $clock;
    private PdfLinkSigner $signer;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-10-28 12:00:00');
        $this->signer = new PdfLinkSigner('unit-test-secret-of-at-least-32-bytes!', $this->clock);
    }

    public function testASignedLinkIsValidUntilItExpires(): void
    {
        $query = $this->signer->sign(self::PATH, ['season' => '2026', 'competition' => 'N1H', 'team' => 12]);

        self::assertTrue($this->signer->isValid(self::PATH, $query));
        $this->clock->sleep(PdfLinkSigner::TTL_SECONDS);
        self::assertTrue($this->signer->isValid(self::PATH, $query), 'valid up to the expiry second');
        $this->clock->sleep(1);
        self::assertFalse($this->signer->isValid(self::PATH, $query), 'expired');
    }

    public function testPdfAuth07AlteredLinksAreRefused(): void
    {
        $query = $this->signer->sign(self::PATH, ['season' => '2026', 'competition' => 'N1H']);

        self::assertFalse($this->signer->isValid('/pdf-signed/presence', $query), 'other document');
        self::assertFalse($this->signer->isValid(self::PATH, ['competition' => 'N2H'] + $query), 'other competition');
        self::assertFalse($this->signer->isValid(self::PATH, [...$query, 'visa' => '1']), 'added option');
        self::assertFalse($this->signer->isValid(self::PATH, [...$query, PdfLinkSigner::EXPIRES => '9999999999']), 'extended expiry');
        unset($query[PdfLinkSigner::SIGNATURE]);
        self::assertFalse($this->signer->isValid(self::PATH, $query), 'no signature');
    }

    public function testTheParameterOrderDoesNotMatter(): void
    {
        $query = $this->signer->sign(self::PATH, ['season' => '2026', 'competition' => 'N1H']);

        self::assertTrue($this->signer->isValid(self::PATH, array_reverse($query, true)));
    }

    public function testNoSecretNoValidLink(): void
    {
        $unconfigured = new PdfLinkSigner('', $this->clock);

        self::assertFalse($unconfigured->isValid(self::PATH, $unconfigured->sign(self::PATH, ['season' => '2026'])));
    }

    /** sources/commun/MySignedLink.php computes the same signature: one known vector pins the shared scheme. */
    public function testTheSchemeSharedWithTheLegacyPagesIsStable(): void
    {
        $signer = new PdfLinkSigner('shared-secret', new MockClock('@1000'));

        self::assertSame(
            ['S' => '2026', 'Compet' => 'N1H', 'expires' => '1300', 'signature' => '5mTQzZBpnLYj2ToA7G3MC6Re9Z4mseejfhYka0HplDo'],
            $signer->sign('/admin/FeuillePresence.php', ['S' => '2026', 'Compet' => 'N1H']),
        );
    }
}
