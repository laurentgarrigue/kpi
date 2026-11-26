<?php

namespace App\Command;

use App\Designation\LegacyBackfillService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:designations:backfill-legacy',
    description: 'Reprend en Désignations les officiels saisis en texte sur les journées, et complète le code du club organisateur',
)]
final class DesignationsBackfillLegacyCommand extends Command
{
    public function __construct(private readonly LegacyBackfillService $backfill)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('season', null, InputOption::VALUE_REQUIRED, 'Saison à reprendre (ex. 2026)')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Écrit en base ; sans cette option, simple simulation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $season = (string) $input->getOption('season');
        if ($season === '') {
            $io->error('--season est obligatoire.');

            return Command::INVALID;
        }
        $apply = (bool) $input->getOption('apply');
        $report = $this->backfill->run($season, $apply);

        $io->title(sprintf('Reprise des officiels, saison %s%s', $season, $apply ? '' : ' (simulation, rien n\'est écrit)'));
        $io->table(['Rôle', 'Périmètre', 'Id', 'Licence'], array_map(static fn (array $d): array => [$d['role'], $d['scope'], $d['scopeId'], $d['matric']], $report['designations']));
        $io->writeln(sprintf('%d Désignation(s) %s ; %d code(s) de club organisateur renseigné(s).', count($report['designations']), $apply ? 'créée(s)' : 'à créer', count($report['organiserCodes'])));
        if ($report['unresolved'] !== []) {
            $io->warning(sprintf('%d saisie(s) non reprise(s), à désigner à la main :', count($report['unresolved'])));
            $io->table(['Rôle', 'Journée / compétition', 'Texte'], array_map(static fn (array $u): array => [$u['role'], (string) ($u['gameday'] ?? $u['competition'] ?? ''), $u['text'] . (isset($u['column']) ? ' (' . $u['column'] . ')' : '')], $report['unresolved']));
        }

        return Command::SUCCESS;
    }
}
