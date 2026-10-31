<?php

namespace App\Pdf\Document;

use App\Pdf\InvalidPdfRequest;
use App\Pdf\PdfAccessChecker;
use App\Pdf\PdfAccessDenied;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfCompetitions;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfPlace;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\PublicResults\CompetitionRules;
use Doctrine\DBAL\Connection;

/**
 * Officials of a gameday (`instances`, ex-FeuilleInstances, PDF_GOTENBERG.md § 7.2): competition committee and appeal
 * jury, with blank lines for what is decided on site. `gameday` is required and must belong to the competition and
 * season of the request, whose perimeter is checked before; a gameday out of the filter of the mandate → 403.
 */
final class InstancesDocument implements PdfDocumentProvider
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PdfCompetitions $competitions,
        private readonly PdfAccessChecker $access,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::INSTANCES;
    }

    public function supports(PdfTheme $theme): bool
    {
        return $theme === PdfTheme::Admin;
    }

    public function options(): array
    {
        return [];
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        $code = $request->requireCompetition();
        $gamedayId = $request->gameday ?? throw new InvalidPdfRequest('Parameter "gameday" is required.');
        $gameday = $this->connection->fetchAssociative(
            'SELECT Id, Nom, Date_debut, Date_fin, Lieu, Departement, Responsable_insc, Responsable_R1, Delegue, ChefArbitre
             FROM kp_journee WHERE Id = ? AND Code_competition = ? AND Code_saison = ?',
            [$gamedayId, $code, $request->season],
        );
        $header = $this->competitions->header($request->season, $code);
        if ($gameday === false || $header === null) {
            return null;
        }
        $allowed = $this->access->allowedGamedays();
        if ($allowed !== null && !in_array($gamedayId, $allowed, true)) {
            throw new PdfAccessDenied('Gameday out of the perimeter.');
        }

        $labels = $this->labels->forLanguage($request->lang);
        $person = static fn (string $column): string => CompetitionRules::personName($gameday[$column]) ?? '';

        return new PdfContent(
            template: 'sections.html.twig',
            title: $labels['instances.title'],
            filename: sprintf('%s_%s_%s_%d_%s.pdf', $this->document(), $code, $request->season, $gamedayId, $request->lang),
            variables: [
                'competition' => $header['competition'],
                'columns' => [],
                'sections' => [
                    [
                        'title' => (string) $gameday['Nom'],
                        'pairs' => [
                            [$labels['instances.dates'], self::period($gameday['Date_debut'], $gameday['Date_fin'])],
                            [$labels['instances.place'], PdfPlace::label($gameday['Lieu'], $gameday['Departement'])],
                            [$labels['instances.competition_manager'], $person('Responsable_insc')],
                        ],
                    ],
                    [
                        'title' => $labels['instances.competition_officials'],
                        'pairs' => [
                            [$labels['instances.r1'], $person('Responsable_R1')],
                            [$labels['instances.delegate'], $person('Delegue')],
                            [$labels['instances.chief_referee'], $person('ChefArbitre')],
                        ],
                    ],
                    [
                        'title' => $labels['instances.appeal_jury'],
                        'pairs' => [
                            [$labels['instances.jury_president'], $person('Delegue')],
                            [$labels['instances.r1'], $person('Responsable_R1')],
                            [$labels['instances.athletes_representative'], ''],
                        ],
                    ],
                ],
                'internal' => true,
            ],
            images: $header['images'],
        );
    }

    private static function period(?string $start, ?string $end): string
    {
        $format = static function (?string $date): string {
            $parsed = $date === null ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));

            return $parsed === false ? '' : $parsed->format('d/m/Y');
        };

        return trim($format($start) . ' - ' . $format($end), ' -');
    }
}
