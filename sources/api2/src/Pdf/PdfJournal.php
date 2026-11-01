<?php

namespace App\Pdf;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

/**
 * Journal of the admin PDF documents (PDF_GOTENBERG.md § 6.4): who printed which document, for which season,
 * competition, event, gameday or games, in kp_journal like the other admin actions. A failed write never prevents the
 * document from being served.
 */
class PdfJournal
{
    /** Prefix of the action, followed by the document: « PDF presence ». */
    public const ACTION = 'PDF';

    /** kp_journal.Competitions holds one code. */
    private const COMPETITION_MAX_LENGTH = 8;

    /** Parameters already in their own column, left out of the details. */
    private const CONTEXT_PARAMETERS = ['season', 'competition', 'event', 'gameday', 'games', 'tz'];

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(string $user, string $document, PdfRequest $request): void
    {
        $competition = $request->competition !== null && strlen($request->competition) <= self::COMPETITION_MAX_LENGTH ? $request->competition : null;
        $details = array_diff_key($request->toQuery(), array_flip(self::CONTEXT_PARAMETERS));
        if ($request->competitions !== [] && $competition === null) {
            $details['competition'] = implode(',', $request->competitions);
        }

        try {
            $this->connection->insert('kp_journal', [
                'Dates' => date('Y-m-d H:i:s'),
                'Users' => $user,
                'Actions' => self::ACTION . ' ' . $document,
                'Saisons' => $request->season,
                'Competitions' => $competition,
                'Evenements' => $request->event,
                'Journees' => $request->gameday,
                'Matchs' => $request->games === [] ? null : implode(',', $request->games),
                'Journal' => http_build_query($details, '', ' ', PHP_QUERY_RFC3986),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('PDF journal not written: {message}', ['message' => $e->getMessage()]);
        }
    }
}
