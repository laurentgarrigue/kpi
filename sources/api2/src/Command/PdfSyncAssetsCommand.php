<?php

namespace App\Command;

use App\Pdf\ChartTokens;
use App\Pdf\PdfAssets;
use App\Pdf\PdfTheme;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Copies the charters of app4 and app3 into assets/pdf/ (PDF_GOTENBERG.md § 8.1): colour and font tokens, fonts,
 * logos. Run it (`make pdf_tokens_sync`) whenever a charter changes, and commit the result; the PdfAssetsTest
 * suite fails while they differ (PDF-DESIGN-05).
 */
#[AsCommand(name: 'app:pdf:sync-assets', description: 'Copie les jetons de charte, polices et logos d\'app4 et app3 dans assets/pdf/')]
final class PdfSyncAssetsCommand extends Command
{
    public function __construct(
        private readonly PdfAssets $assets,
        #[Autowire('%kernel.project_dir%/..')]
        private readonly string $sourcesFromProject,
        private readonly string $legacyDocumentRoot,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('sources', null, InputOption::VALUE_REQUIRED, 'Racine du dossier sources/ (défaut : parent du projet, sinon LEGACY_DOCUMENT_ROOT)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $sources = $input->getOption('sources') ?? (is_dir($this->sourcesFromProject . '/app4') ? $this->sourcesFromProject : $this->legacyDocumentRoot);
        $target = $this->assets->directory();

        foreach (PdfTheme::cases() as $theme) {
            $css = @file_get_contents($sources . '/' . ChartTokens::SOURCES[$theme->value]);
            if ($css === false) {
                $output->writeln(sprintf('<error>Charte introuvable : %s/%s</error>', $sources, ChartTokens::SOURCES[$theme->value]));

                return Command::FAILURE;
            }
            $this->write($target . '/tokens/' . $theme->value . '.css', ChartTokens::stylesheet($theme, $css), $output);
        }

        foreach (PdfAssets::CHARTER_FILES as $published => $source) {
            $content = @file_get_contents($sources . '/' . $source);
            if ($content === false) {
                $output->writeln(sprintf('<error>Fichier introuvable : %s/%s</error>', $sources, $source));

                return Command::FAILURE;
            }
            $this->write($target . '/' . $published, $content, $output);
        }

        return Command::SUCCESS;
    }

    private function write(string $file, string $content, OutputInterface $output): void
    {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        file_put_contents($file, $content);
        $output->writeln('  ' . $file);
    }
}
