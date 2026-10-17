<?php

namespace App\Forms;

use App\Cms\CmsRules;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * E-mails des formulaires (FEATURE_FORMS.md § 3–4, FRM-04, FRM-07) : accusé au déclarant, notification aux
 * organisateurs, confirmation d'une inscription en attente. Texte brut seulement (aucun HTML saisi n'est rendu) ;
 * chaque envoi est journalisé dans kp_mail_log ; un échec n'annule jamais l'inscription.
 */
final class FormMailer
{
    private const TEXTS = [
        'fr' => [
            'received_subject' => 'Inscription : %s',
            'confirmed_subject' => 'Inscription confirmée : %s',
            'notify_subject' => 'Nouvelle inscription : %s',
            'confirmed' => 'Votre inscription est confirmée.',
            'waiting_full' => 'Votre inscription est enregistrée en liste d\'attente : toutes les places sont prises.',
            'waiting_manual' => 'Votre inscription est enregistrée et en attente de confirmation par l\'organisateur (par exemple à réception du paiement).',
            'payment' => 'Paiement : %s',
            'answers' => 'Vos réponses :',
            'withdraw' => 'Pour modifier ou retirer votre inscription, répondez simplement à ce message.',
            'status' => 'Statut : %s',
            'statuses' => ['confirmed' => 'confirmée', 'waiting' => 'en attente', 'cancelled' => 'annulée'],
        ],
        'en' => [
            'received_subject' => 'Registration: %s',
            'confirmed_subject' => 'Registration confirmed: %s',
            'notify_subject' => 'New registration: %s',
            'confirmed' => 'Your registration is confirmed.',
            'waiting_full' => 'Your registration is on the waiting list: all places are taken.',
            'waiting_manual' => 'Your registration is recorded and awaits confirmation by the organiser (for instance once the payment is received).',
            'payment' => 'Payment: %s',
            'answers' => 'Your answers:',
            'withdraw' => 'To change or withdraw your registration, simply reply to this message.',
            'status' => 'Status: %s',
            'statuses' => ['confirmed' => 'confirmed', 'waiting' => 'waiting', 'cancelled' => 'cancelled'],
        ],
    ];

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly FormRepository $forms,
        private readonly AnswerFormatter $formatter,
        private readonly ClockInterface $clock,
        #[Autowire('%env(MAILER_FROM)%')]
        private readonly string $from,
    ) {
    }

    /**
     * Accusé de réception au déclarant et notification aux organisateurs.
     *
     * @param array<string, mixed>       $form
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed>       $entry  ligne de kp_form_entry (answers décodées)
     */
    public function sendReceived(array $form, array $fields, array $entry, string $language): void
    {
        $texts = self::TEXTS[CmsRules::language($language)];
        $title = CmsRules::localized($form, 'title', $language);
        $intro = match (true) {
            $entry['status'] === FormRules::ENTRY_CONFIRMED => $texts['confirmed'],
            $entry['reason'] === FormRules::REASON_FULL => $texts['waiting_full'],
            default => $texts['waiting_manual'],
        };
        $body = [$intro];
        $confirmationText = CmsRules::localized($form, 'confirmation_text', $language);
        if ($confirmationText !== '') {
            $body[] = $confirmationText;
        }
        if ((string) $form['payment_url'] !== '') {
            $body[] = sprintf($texts['payment'], $form['payment_url']);
        }
        $body[] = $texts['answers'] . "\n" . $this->recap($fields, $entry['answers'], $language);
        $body[] = $texts['withdraw'];
        $this->send($form, $entry, (string) $entry['email'], sprintf($texts['received_subject'], $title), implode("\n\n", $body));

        foreach (self::organisers($form) as $organiser) {
            $notification = sprintf($texts['status'], $texts['statuses'][$entry['status']]) . "\n\n" . $this->recap($fields, $entry['answers'], 'fr');
            $this->send($form, $entry, $organiser, sprintf(self::TEXTS['fr']['notify_subject'], CmsRules::localized($form, 'title', 'fr')), $notification, false);
        }
    }

    /**
     * Confirmation d'une inscription qui était en attente (FRM-07, ADM-CMS-06).
     *
     * @param array<string, mixed>       $form
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed>       $entry
     */
    public function sendConfirmed(array $form, array $fields, array $entry): void
    {
        $texts = self::TEXTS['fr'];
        $title = CmsRules::localized($form, 'title', 'fr');
        $body = $texts['confirmed'] . "\n\n" . $texts['answers'] . "\n" . $this->recap($fields, $entry['answers'], 'fr') . "\n\n" . $texts['withdraw'];
        $this->send($form, $entry, (string) $entry['email'], sprintf($texts['confirmed_subject'], $title), $body);
    }

    /** Renvoie un e-mail journalisé (échec précédent) ; renvoie le nouveau statut. */
    public function resend(array $form, array $mail): string
    {
        if ($mail['body'] === null) {
            return (string) $mail['status'];
        }
        $status = $this->deliver($form, (string) $mail['recipient'], (string) $mail['subject'], (string) $mail['body'], true, $error);
        $this->forms->updateMail((int) $mail['id'], ['status' => $status, 'error' => $error, 'sent_at' => $this->now()]);

        return $status;
    }

    /** @param array<string, mixed> $form */
    private function send(array $form, array $entry, string $to, string $subject, string $body, bool $replyToOrganisers = true): void
    {
        if ($to === '') {
            return;
        }
        $status = $this->deliver($form, $to, $subject, $body, $replyToOrganisers, $error);
        $this->forms->logMail([
            'form_id' => (int) $form['id'],
            'entry_id' => isset($entry['id']) ? (int) $entry['id'] : null,
            'recipient' => $to,
            'subject' => mb_substr($subject, 0, 255),
            'body' => $body,
            'status' => $status,
            'error' => mb_substr($error, 0, 255),
            'sent_at' => $this->now(),
        ]);
    }

    /** @param array<string, mixed> $form */
    private function deliver(array $form, string $to, string $subject, string $body, bool $replyToOrganisers, ?string &$error): string
    {
        $error = '';
        try {
            $email = (new Email())->from($this->from)->to($to)->subject($subject)->text($body);
            $organisers = self::organisers($form);
            if ($replyToOrganisers && $organisers !== []) {
                $email->replyTo(...array_map(static fn (string $address): Address => new Address($address), $organisers));
            }
            $this->mailer->send($email);

            return 'sent';
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();

            return 'failed';
        }
    }

    /** @return list<string> adresses valides des organisateurs */
    private static function organisers(array $form): array
    {
        return array_values(array_filter(
            array_map('trim', explode(',', (string) $form['notify_emails'])),
            static fn (string $address): bool => filter_var($address, FILTER_VALIDATE_EMAIL) !== false,
        ));
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed>       $answers
     */
    private function recap(array $fields, array $answers, string $language): string
    {
        $lines = [];
        foreach ($fields as $field) {
            $value = $answers[$field['field_key']] ?? null;
            if ($value === null) {
                continue;
            }
            $lines[] = sprintf('- %s : %s', CmsRules::localized($field, 'label', $language), $this->formatter->format($field, $value, $language));
        }

        return implode("\n", $lines);
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s');
    }
}
