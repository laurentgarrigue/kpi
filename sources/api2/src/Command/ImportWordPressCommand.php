<?php

namespace App\Command;

use App\Cms\Import\ImportOptions;
use App\Cms\Import\ImportReport;
use App\Cms\Import\WordPressImporter;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Reprise du contenu WordPress (FEATURE_CMS.md § 8, CMS-09). Lit la base WordPress en lecture seule
 * (`WORDPRESS_DATABASE_URL` ou --dsn), écrit le rapport sur la sortie et dans --report.
 *
 *   bin/console app:import-wordpress --dry-run
 *   bin/console app:import-wordpress --report=var/import-wordpress.txt
 */
#[AsCommand(name: 'app:import-wordpress', description: 'Reprend les articles, pages, médias, redirections et le formulaire d\'exemple de WordPress')]
final class ImportWordPressCommand extends Command
{
    public function __construct(
        private readonly WordPressImporter $importer,
        private readonly Connection $connection,
        #[Autowire('%env(default::WORDPRESS_DATABASE_URL)%')]
        private readonly ?string $wordpressDsn,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dsn', null, InputOption::VALUE_REQUIRED, 'Base WordPress (mysql://user:pass@host:3306/base), sinon WORDPRESS_DATABASE_URL')
            ->addOption('same-database', null, InputOption::VALUE_NONE, 'Tables WordPress dans la base KPI (dump importé, tests)')
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED, 'Préfixe des tables WordPress', 'wp_')
            ->addOption('articles', null, InputOption::VALUE_REQUIRED, 'Nombre d\'articles publiés les plus récents', '50')
            ->addOption('pages', null, InputOption::VALUE_REQUIRED, 'Identifiants des pages (virgules)', implode(',', ImportOptions::PAGES))
            ->addOption('draft-pages', null, InputOption::VALUE_REQUIRED, 'Pages reprises en brouillon (virgules)', implode(',', ImportOptions::DRAFT_PAGES))
            ->addOption('ninja-form', null, InputOption::VALUE_REQUIRED, 'Formulaire Ninja Forms d\'exemple (0 : aucun)', (string) ImportOptions::NINJA_FORM)
            ->addOption('uploads-dir', null, InputOption::VALUE_REQUIRED, 'Répertoire wp-content/uploads local (sinon téléchargement depuis --site-url)')
            ->addOption('site-url', null, InputOption::VALUE_REQUIRED, 'Adresse du site WordPress', 'https://www.kayak-polo.info')
            ->addOption('report', null, InputOption::VALUE_REQUIRED, 'Fichier du rapport')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Analyse et rapport sans rien écrire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dsn = (string) ($input->getOption('dsn') ?? $this->wordpressDsn ?? '');
        if (!$input->getOption('same-database') && $dsn === '') {
            $output->writeln('<error>Base WordPress inconnue : --dsn, WORDPRESS_DATABASE_URL ou --same-database</error>');

            return Command::INVALID;
        }
        $wordpress = $input->getOption('same-database')
            ? $this->connection
            : DriverManager::getConnection((new DsnParser(['mysql' => 'pdo_mysql']))->parse($dsn) + ['charset' => 'utf8mb4']);
        $ids = static fn (string $list): array => array_values(array_filter(array_map('intval', explode(',', $list))));
        $ninjaForm = (int) $input->getOption('ninja-form');
        $options = new ImportOptions(
            prefix: (string) $input->getOption('prefix'),
            articleCount: max(0, (int) $input->getOption('articles')),
            pageIds: $ids((string) $input->getOption('pages')),
            draftPageIds: $ids((string) $input->getOption('draft-pages')),
            ninjaFormId: $ninjaForm > 0 ? $ninjaForm : null,
            uploadsDir: $input->getOption('uploads-dir'),
            siteUrl: (string) $input->getOption('site-url'),
            dryRun: (bool) $input->getOption('dry-run'),
        );

        $report = new ImportReport();
        $this->importer->run($wordpress, $options, $report);

        $lines = [...$report->lines(), '', ($options->dryRun ? 'Simulation (rien n\'est écrit) — ' : '') . $report->summary()];
        $output->writeln($lines);
        if ($input->getOption('report')) {
            file_put_contents((string) $input->getOption('report'), implode("\n", $lines) . "\n");
        }

        return Command::SUCCESS;
    }
}
