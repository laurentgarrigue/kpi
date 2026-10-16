<?php

namespace App\Command;

use App\Forms\FormRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Purge RGPD quotidienne des formulaires (FEATURE_FORMS.md § 6, FRM-09) : inscriptions supprimées à l'échéance de
 * conservation (le nombre d'inscrits est conservé), corps des e-mails journalisés effacés après 30 jours.
 * À planifier une fois par jour (cron du serveur, comme les autres tâches planifiées).
 */
#[AsCommand(name: 'app:forms:purge', description: 'Purge RGPD des inscriptions échues et du corps des e-mails journalisés')]
final class FormsPurgeCommand extends Command
{
    private const MAIL_BODY_DAYS = 30;

    public function __construct(private readonly FormRepository $forms, private readonly ClockInterface $clock)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'));
        $entries = $this->forms->purgeExpiredEntries($now->format('Y-m-d'));
        $bodies = $this->forms->purgeMailBodies($now->modify(sprintf('-%d days', self::MAIL_BODY_DAYS))->format('Y-m-d H:i:s'));
        $output->writeln(sprintf('%d inscription(s) supprimée(s), %d corps d\'e-mail effacé(s).', $entries, $bodies));

        return Command::SUCCESS;
    }
}
