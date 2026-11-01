<?php

namespace App\Command;

use App\AgeCategory\AgeCategoryMigration;
use App\AgeCategory\AgeCategoryMigrationReport;
use App\AgeCategory\AgeCategoryRule;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Migration de la catégorie d'âge (CATEGORIE_AGE.md stories 31-34, procédure : DEPLOYMENT_RUNBOOK.md § 1.4).
 *
 *   bin/console app:age-category:migrate --dry-run   rapport à blanc, rien n'est écrit
 *   bin/console app:age-category:migrate             conversion des dates puis recalcul (relançable)
 */
#[AsCommand(name: 'app:age-category:migrate', description: 'Convertit les dates de naissance conventionnelles en date inconnue et recalcule la catégorie d\'âge de toutes les compositions')]
final class AgeCategoryMigrateCommand extends Command
{
    public function __construct(private readonly AgeCategoryMigration $migration)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Rapport sans rien écrire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dryRun = (bool) $input->getOption('dry-run');
        $report = $dryRun ? $this->migration->plan() : $this->migration->run();

        $output->writeln([$dryRun ? 'Simulation (rien n\'est écrit)' : 'Migration exécutée', '', ...self::lines($report)]);

        return Command::SUCCESS;
    }

    /** @return list<string> */
    private static function lines(AgeCategoryMigrationReport $report): array
    {
        $lines = [
            sprintf('Dates de naissance conventionnelles (%s) → inconnue : %d', implode(', ', AgeCategoryRule::CONVENTIONAL_BIRTH_DATES), $report->convertedBirthDates),
            sprintf('Catégories d\'âge à changer : %d', count($report->categoryChanges)),
        ];
        foreach ($report->categoryChanges->countsBySeason() as $season => $couples) {
            foreach ($couples as $couple => $count) {
                $lines[] = sprintf('  %s  %s : %d', $season, $couple, $count);
            }
        }

        return $lines;
    }
}
