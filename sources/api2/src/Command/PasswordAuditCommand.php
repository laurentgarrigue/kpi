<?php

namespace App\Command;

use App\Security\PasswordAudit;
use App\Security\PasswordRenewalService;
use App\Security\PasswordVerifier;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Inventaire des mots de passe avant le renouvellement de saison et le retrait du MD5 (AUTH_HARDENING.md § 2bis,
 * AUTH-RN-01). Lecture seule.
 */
#[AsCommand(name: 'app:auth:password-audit', description: 'Inventaire des mots de passe : comptes MD5, bcrypt, sans mot de passe, sans e-mail')]
final class PasswordAuditCommand extends Command
{
    private const NEVER = 'jamais';

    public function __construct(
        private readonly PasswordAudit $audit,
        private readonly PasswordRenewalService $renewals,
        private readonly PasswordVerifier $verifier,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('without-email', null, InputOption::VALUE_NONE, 'Lister aussi les comptes sans e-mail valide (pas de « mot de passe oublié » possible)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $accounts = $this->audit->accounts();
        $byKind = array_count_values(array_column($accounts, 'kind'));
        $withoutEmail = array_values(array_filter($accounts, fn (array $a): bool => !$a['hasEmail']));

        $io->title('Inventaire des mots de passe (kp_user)');
        $io->table(['Catégorie', 'Comptes'], [
            ['Hash MD5', $byKind[PasswordAudit::KIND_MD5] ?? 0],
            ['Hash bcrypt', $byKind[PasswordAudit::KIND_BCRYPT] ?? 0],
            ['Sans mot de passe (Google seul ou purgé)', $byKind[PasswordAudit::KIND_EMPTY] ?? 0],
            ['Autre format', $byKind[PasswordAudit::KIND_OTHER] ?? 0],
            ['Sans e-mail valide', count($withoutEmail)],
            ['Renouvellement en attente', $this->renewals->countPending()],
        ]);
        $io->writeln(sprintf('Repli MD5 au login : <info>%s</info> (AUTH_LEGACY_MD5_FALLBACK)', $this->verifier->isLegacyMd5FallbackEnabled() ? 'actif' : 'retiré'));

        $md5 = array_values(array_filter($accounts, fn (array $a): bool => $a['kind'] === PasswordAudit::KIND_MD5));
        $io->section(sprintf('Comptes MD5 (%d)', count($md5)));
        $this->listAccounts($io, $md5);

        if ($input->getOption('without-email')) {
            $io->section(sprintf('Comptes sans e-mail valide (%d)', count($withoutEmail)));
            $this->listAccounts($io, $withoutEmail);
        }

        return Command::SUCCESS;
    }

    /** @param list<array{code: string, identity: string, profile: int, email: string, kind: string, lastLogin: ?string}> $accounts */
    private function listAccounts(SymfonyStyle $io, array $accounts): void
    {
        $io->table(
            ['Code', 'Identité', 'Profil', 'E-mail', 'Hash', 'Dernier login'],
            array_map(fn (array $a): array => [$a['code'], $a['identity'], $a['profile'], $a['email'], $a['kind'], $a['lastLogin'] ?? self::NEVER], $accounts),
        );
    }
}
