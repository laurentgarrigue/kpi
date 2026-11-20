<?php

namespace App\Command;

use App\Security\SecondFactorDecision;
use App\Security\SecondFactorPolicy;
use App\Security\SecondFactorSecretBox;
use App\Security\SecondFactorSettings;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Suivi du second facteur (AUTH_HARDENING.md § 5.2bis) : date d'entrée en vigueur, puis chaque compte de profil
 * maximal ≤ 4 (mandats compris) ou doté d'un second facteur, avec sa méthode et son état. Lecture seule.
 */
#[AsCommand(name: 'app:auth:second-factor-audit', description: 'Second facteur : date en vigueur et état des comptes concernés')]
final class SecondFactorAuditCommand extends Command
{
    private const NONE = 'aucun';

    public function __construct(
        private readonly Connection $connection,
        private readonly SecondFactorSettings $settings,
        private readonly SecondFactorSecretBox $secretBox,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $enforcedFrom = $this->settings->enforcedFrom();
        $now = $this->clock->now();

        $io->title('Second facteur (AUTH_HARDENING.md § 5)');
        $io->writeln(sprintf('Entrée en vigueur (AUTH_2FA_ENFORCED_FROM) : <info>%s</info>', $enforcedFrom?->format('Y-m-d') ?? 'non fixée'));
        $io->writeln(sprintf('TOTP (AUTH_2FA_ENCRYPTION_KEY) : <info>%s</info>', $this->secretBox->isConfigured() ? 'disponible' : 'indisponible, code par e-mail seul'));

        $rows = [];
        $byState = [];
        foreach ($this->accounts() as $account) {
            $maxProfile = SecondFactorPolicy::maxProfile((int) $account['Niveau'], $account['mandat'] === null ? [] : [(int) $account['mandat']]);
            $outcome = SecondFactorPolicy::decide(
                maxProfile: $maxProfile,
                enforcedFrom: $enforcedFrom,
                activeMethod: $account['method'],
                enrollmentRequiredSince: $account['since'] === null ? null : new \DateTimeImmutable($account['since']),
                trustedDevice: false,
                now: $now,
            );
            $state = $this->stateLabel($outcome->decision, $outcome->enrollmentDueAt, $account['since'] !== null);
            $byState[$state] = ($byState[$state] ?? 0) + 1;
            $rows[] = [$account['Code'], $account['Identite'], $maxProfile, $account['method'] ?? self::NONE, $state];
        }

        $io->section(sprintf('Comptes (%d)', count($rows)));
        $io->table(['Code', 'Identité', 'Profil max.', 'Méthode', 'État'], $rows);
        ksort($byState);
        $io->table(['État', 'Comptes'], array_map(null, array_keys($byState), array_values($byState)));

        return Command::SUCCESS;
    }

    /** @return list<array{Code: string, Identite: string, Niveau: int|string, mandat: int|string|null, method: ?string, since: ?string}> */
    private function accounts(): array
    {
        /** @var list<array{Code: string, Identite: string, Niveau: int|string, mandat: int|string|null, method: ?string, since: ?string}> */
        return $this->connection->fetchAllAssociative(
            'SELECT u.Code, u.Identite, u.Niveau, m.mandat, sf.method, sf.enrollment_required_since AS since
             FROM kp_user u
             LEFT JOIN (SELECT user_code, MIN(niveau) AS mandat FROM kp_user_mandat GROUP BY user_code) m ON m.user_code = u.Code
             LEFT JOIN kp_user_second_factor sf ON sf.user_code = u.Code
             WHERE LEAST(u.Niveau, COALESCE(m.mandat, u.Niveau)) <= ? OR sf.method IS NOT NULL
             ORDER BY LEAST(u.Niveau, COALESCE(m.mandat, u.Niveau)), u.Code',
            [SecondFactorPolicy::MAX_REQUIRED_PROFILE],
        );
    }

    private function stateLabel(SecondFactorDecision $decision, ?\DateTimeImmutable $dueAt, bool $graceOpened): string
    {
        return match ($decision) {
            SecondFactorDecision::Challenge => 'actif',
            SecondFactorDecision::EnrollmentRequired => 'enrôlement exigé',
            SecondFactorDecision::Grace => $graceOpened
                ? 'en grâce jusqu\'au ' . $dueAt?->format('Y-m-d H:i')
                : 'grâce à la prochaine connexion',
            SecondFactorDecision::Pass => 'pas d\'obligation en vigueur',
        };
    }
}
