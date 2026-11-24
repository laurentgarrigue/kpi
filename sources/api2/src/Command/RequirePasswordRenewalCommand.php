<?php

namespace App\Command;

use App\Security\PasswordRenewalService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Lance la campagne de renouvellement de saison (AUTH_HARDENING.md § 2bis) : tout compte qui a un mot de passe
 * et ne l'a pas renouvelé depuis --renewed-since devra en choisir un nouveau à son prochain login.
 * Idempotente : un compte déjà marqué n'est pas recompté.
 */
#[AsCommand(name: 'app:auth:require-password-renewal', description: 'Marque « mot de passe à renouveler » les comptes non renouvelés depuis une date')]
final class RequirePasswordRenewalCommand extends Command
{
    public function __construct(private readonly PasswordRenewalService $renewals)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('renewed-since', null, InputOption::VALUE_REQUIRED, 'Exempte les comptes renouvelés depuis cette date (AAAA-MM-JJ), ex. début de saison')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste les comptes concernés sans rien écrire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $since = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $input->getOption('renewed-since'));
        if ($since === false) {
            $io->error('--renewed-since=AAAA-MM-JJ est obligatoire (ex. --renewed-since=2027-01-01).');
            return Command::INVALID;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $codes = $this->renewals->requireRenewal($since, $dryRun);
        $io->listing($codes);
        $io->success(sprintf(
            '%d compte(s) %s « mot de passe à renouveler ».',
            count($codes),
            $dryRun ? 'seraient marqués' : 'marqué(s)',
        ));

        return Command::SUCCESS;
    }
}
