<?php

namespace App\Command;

use App\Security\PasswordVerifier;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Retrait définitif du MD5 (AUTH_HARDENING.md § 2bis, AUTH-RN-08) : vide kp_user.Pwd des comptes encore en MD5.
 * Ces comptes repassent par « mot de passe oublié ». Refuse de tourner tant que le repli MD5 est actif
 * (AUTH_LEGACY_MD5_FALLBACK=1) : couper le repli d'abord, sinon on purgerait des comptes encore servis.
 */
#[AsCommand(name: 'app:auth:purge-md5-hashes', description: 'Vide les mots de passe encore stockés en MD5 (après retrait du repli MD5)')]
final class PurgeMd5HashesCommand extends Command
{
    public const JOURNAL_ACTION = 'Purge hash MD5';

    public function __construct(private readonly Connection $connection, private readonly PasswordVerifier $verifier)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste les comptes concernés sans rien écrire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->verifier->isLegacyMd5FallbackEnabled()) {
            $io->error('Le repli MD5 est encore actif : passer AUTH_LEGACY_MD5_FALLBACK=0 et redémarrer api2 avant la purge.');
            return Command::FAILURE;
        }

        $codes = array_keys(array_filter(
            $this->connection->fetchAllKeyValue("SELECT Code, Pwd FROM kp_user WHERE CHAR_LENGTH(Pwd) = 32"),
            fn (?string $hash): bool => PasswordVerifier::isLegacyMd5Hash((string) $hash),
        ));
        $io->listing($codes);

        if ($input->getOption('dry-run')) {
            $io->success(sprintf('%d hash MD5 seraient vidés.', count($codes)));
            return Command::SUCCESS;
        }

        foreach ($codes as $code) {
            $this->connection->executeStatement("UPDATE kp_user SET Pwd = '' WHERE Code = ?", [$code]);
            $this->connection->executeStatement(
                'INSERT INTO kp_journal (Dates, Users, Actions, Journal) VALUES (NOW(), ?, ?, ?)',
                [$code, self::JOURNAL_ACTION, 'Mot de passe MD5 vidé : passer par « mot de passe oublié »'],
            );
        }
        $io->success(sprintf('%d hash MD5 vidé(s).', count($codes)));

        return Command::SUCCESS;
    }
}
