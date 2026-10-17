<?php

namespace App\Forms;

/** Règles des formulaires d'inscription (FEATURE_FORMS.md) : types de champs, statuts, état d'ouverture. */
final class FormRules
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED];

    public const ENTRY_CONFIRMED = 'confirmed';
    public const ENTRY_WAITING = 'waiting';
    public const ENTRY_CANCELLED = 'cancelled';
    public const ENTRY_STATUSES = [self::ENTRY_CONFIRMED, self::ENTRY_WAITING, self::ENTRY_CANCELLED];

    /** Raison d'une attente : plus de place (liste d'attente) ou confirmation par l'organisateur (paiement…). */
    public const REASON_FULL = 'full';
    public const REASON_MANUAL = 'manual';

    public const CONFIRMATION_AUTO = 'auto';
    public const CONFIRMATION_MANUAL = 'manual';
    public const CONFIRMATION_MODES = [self::CONFIRMATION_AUTO, self::CONFIRMATION_MANUAL];

    public const TYPES = ['text', 'textarea', 'email', 'phone', 'number', 'date', 'select', 'radio', 'checkbox', 'club', 'competition'];
    public const TYPES_WITH_OPTIONS = ['select', 'radio', 'checkbox'];

    /** Données personnelles : jamais dans la liste publique (FRM-06). */
    public const PERSONAL_TYPES = ['email', 'phone', 'textarea', 'date'];

    public const STATE_UPCOMING = 'upcoming';
    public const STATE_OPEN = 'open';
    public const STATE_CLOSED = 'closed';

    /**
     * État d'un formulaire publié à un instant donné (dates en heure de Paris, `Y-m-d H:i:s`).
     *
     * @param array<string, mixed> $form
     */
    public static function state(array $form, string $now): string
    {
        if ($form['opens_at'] !== null && $now < (string) $form['opens_at']) {
            return self::STATE_UPCOMING;
        }
        if ($form['closes_at'] !== null && $now > (string) $form['closes_at']) {
            return self::STATE_CLOSED;
        }

        return self::STATE_OPEN;
    }

    /**
     * Statut d'une nouvelle inscription : en attente si la confirmation est manuelle ou s'il n'y a plus de place ;
     * null si elle est refusée (complet, sans liste d'attente) — FRM-03.
     *
     * @param array<string, mixed> $form
     *
     * @return array{status: string, reason: ?string}|null
     */
    public static function newEntryStatus(array $form, int $confirmed): ?array
    {
        $full = $form['capacity'] !== null && $confirmed >= (int) $form['capacity'];
        if ($full && (int) $form['waiting_list'] !== 1) {
            return null;
        }
        if ($full) {
            return ['status' => self::ENTRY_WAITING, 'reason' => self::REASON_FULL];
        }
        if ($form['confirmation_mode'] === self::CONFIRMATION_MANUAL) {
            return ['status' => self::ENTRY_WAITING, 'reason' => self::REASON_MANUAL];
        }

        return ['status' => self::ENTRY_CONFIRMED, 'reason' => null];
    }

    public static function isPublishable(string $type): bool
    {
        return !in_array($type, self::PERSONAL_TYPES, true);
    }
}
