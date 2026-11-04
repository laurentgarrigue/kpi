<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class NotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $mailFrom,
        private readonly string $mailAdminTo,
        private readonly string $app4Url = ''
    ) {
    }

    /**
     * Send a notification email to the admin address.
     */
    public function sendAdminNotification(string $subject, string $body, ?string $to = null): void
    {
        try {
            $email = (new Email())
                ->from($this->mailFrom)
                ->to($to ?? $this->mailAdminTo)
                ->subject($subject)
                ->text($body);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send notification email', [
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send a password reset email to a user.
     */
    public function sendPasswordReset(string $toEmail, string $token, bool $includeDocLink, string $complementaryMessage, string $loginCode = '', string $locale = 'fr'): void
    {
        $resetUrl = rtrim($this->app4Url, '/') . '/reset-password?token=' . $token;
        $isFr = ($locale !== 'en');

        if ($isFr) {
            $subject = 'Accès KPI — Définition de votre mot de passe';
            $body  = "Bonjour,\n\n";
            $body .= "Votre compte KPI a été créé ou mis à jour.\n\n";
            if ($loginCode !== '') {
                $body .= "Votre identifiant de connexion : " . $loginCode . "\n";
                $body .= "(vous pouvez aussi vous connecter avec votre adresse email)\n\n";
            }
            $body .= "Pour définir votre mot de passe, cliquez sur le lien ci-dessous (valable 48h) :\n";
            $body .= $resetUrl . "\n\n";
            if ($includeDocLink) {
                $body .= "Documentation : " . rtrim($this->app4Url, '/') . "/doc\n\n";
            }
            if ($complementaryMessage !== '') {
                $body .= $complementaryMessage . "\n\n";
            }
            $body .= "Cordialement,\nL'équipe KPI";
        } else {
            $subject = 'KPI Access — Set your password';
            $body  = "Hello,\n\n";
            $body .= "Your KPI account has been created or updated.\n\n";
            if ($loginCode !== '') {
                $body .= "Your login identifier: " . $loginCode . "\n";
                $body .= "(you can also sign in with your email address)\n\n";
            }
            $body .= "To set your password, click the link below (valid for 48 hours):\n";
            $body .= $resetUrl . "\n\n";
            if ($includeDocLink) {
                $body .= "Documentation: " . rtrim($this->app4Url, '/') . "/doc\n\n";
            }
            if ($complementaryMessage !== '') {
                $body .= $complementaryMessage . "\n\n";
            }
            $body .= "Best regards,\nThe KPI Team";
        }

        try {
            $email = (new Email())
                ->from($this->mailFrom)
                ->to($toEmail)
                ->subject($subject)
                ->text($body);

            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send password reset email', [
                'to' => $toEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Alerte « nouvelle connexion depuis un appareil inconnu » (AUTH_HARDENING.md AUTH-DV-01).
     * Le lien de révocation invalide l'appareil ET tous les refresh tokens actifs du compte.
     */
    public function sendNewDeviceAlert(string $toEmail, string $userCode, string $deviceLabel, string $revokeToken): void
    {
        $revokeUrl = rtrim($this->app4Url, '/') . '/auth/device/revoke/' . $revokeToken;
        $subject = 'KPI — Nouvelle connexion à votre compte';
        $when = (new \DateTimeImmutable())->format('d/m/Y H:i');

        $body  = "Bonjour,\n\n";
        $body .= "Une nouvelle connexion vient d'avoir lieu sur votre compte KPI :\n\n";
        $body .= "  Compte     : {$userCode}\n";
        $body .= "  Appareil   : {$deviceLabel}\n";
        $body .= "  Date       : {$when}\n\n";
        $body .= "Si vous êtes à l'origine de cette connexion, aucune action n'est nécessaire.\n\n";
        $body .= "Dans le cas contraire, cliquez sur le lien ci-dessous pour révoquer cet appareil\n";
        $body .= "et déconnecter immédiatement toutes vos sessions :\n\n";
        $body .= "  {$revokeUrl}\n\n";
        $body .= "Pensez ensuite à changer votre mot de passe.\n\n";
        $body .= "Cordialement,\nL'équipe KPI";

        try {
            $email = (new Email())
                ->from($this->mailFrom)
                ->to($toEmail)
                ->subject($subject)
                ->text($body);
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send new device alert', [
                'to' => $toEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
