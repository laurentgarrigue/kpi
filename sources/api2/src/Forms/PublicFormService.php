<?php

namespace App\Forms;

use App\Cms\CmsRules;
use App\Cms\ValidationErrors;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/** Formulaires côté public (FEATURE_FORMS.md § 3–4) : définition, envoi, liste publique. */
final class PublicFormService
{
    public function __construct(
        private readonly FormRepository $forms,
        private readonly FormValidator $validator,
        private readonly FormOptions $options,
        private readonly AnswerFormatter $formatter,
        private readonly FormMailer $mailer,
        private readonly FormToken $token,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return array<string, mixed>|null définition d'un formulaire publié (FRM-01) */
    public function definition(string $slug, string $language): ?array
    {
        $form = $this->published($slug);
        if ($form === null) {
            return null;
        }
        $fields = $this->forms->fields((int) $form['id']);
        $counts = $this->forms->countByStatus((int) $form['id']);
        $capacity = $form['capacity'] === null ? null : (int) $form['capacity'];

        return [
            'slug' => $form['slug'],
            'title' => CmsRules::localized($form, 'title', $language),
            'intro' => CmsRules::localized($form, 'intro', $language),
            'state' => FormRules::state($form, $this->now()),
            'opens_at' => $form['opens_at'],
            'closes_at' => $form['closes_at'],
            'capacity' => $capacity,
            'confirmed' => $counts[FormRules::ENTRY_CONFIRMED],
            'full' => $capacity !== null && $counts[FormRules::ENTRY_CONFIRMED] >= $capacity,
            'waiting_list' => (int) $form['waiting_list'] === 1,
            'manual_confirmation' => $form['confirmation_mode'] === FormRules::CONFIRMATION_MANUAL,
            'has_payment' => (string) $form['payment_url'] !== '',
            'public_list' => (int) $form['public_list'] === 1,
            'purpose' => CmsRules::localized($form, 'purpose', $language),
            'retention_days' => (int) $form['retention_days'],
            'event' => $form['event_id'] === null ? null : (int) $form['event_id'],
            'competition' => $form['competition_code'] === null ? null : $form['competition_season'] . '/' . $form['competition_code'],
            'fields' => array_map(fn (array $field): array => $this->publicField($field, $language), $fields),
            'token' => $this->token->issue((string) $form['slug']),
        ];
    }

    /**
     * Enregistre une inscription (FRM-02 à FRM-05). Lève {@see ValidationErrors} (champs) ou
     * {@see FormRejected} (formulaire fermé, complet, robot).
     *
     * @param array<string, mixed> $input réponses + `_token` + `website` (pot de miel)
     *
     * @return array{status: string, reason: ?string, payment_url: ?string}
     */
    public function submit(string $slug, array $input, string $language): array
    {
        $form = $this->published($slug);
        if ($form === null) {
            throw new FormRejected('not_found');
        }
        if (trim((string) ($input['website'] ?? '')) !== '') {
            throw new FormRejected('rejected'); // pot de miel rempli : robot
        }
        $tokenError = $this->token->check($slug, isset($input['_token']) ? (string) $input['_token'] : null);
        if ($tokenError !== null) {
            throw new FormRejected($tokenError);
        }
        if (FormRules::state($form, $this->now()) !== FormRules::STATE_OPEN) {
            throw new FormRejected('closed');
        }

        $fields = $this->forms->fields((int) $form['id']);
        $errors = new ValidationErrors();
        $answers = $this->validator->validate($fields, $input, $errors);
        $errors->throwIfAny();

        // The capacity check and the insert are atomic: concurrent submissions never exceed it (FRM-03).
        $entry = $this->connection->transactional(function () use ($form, $answers, $fields): array {
            $this->forms->lock((int) $form['id']);
            $status = FormRules::newEntryStatus($form, $this->forms->countByStatus((int) $form['id'])[FormRules::ENTRY_CONFIRMED]);
            if ($status === null) {
                throw new FormRejected('full');
            }
            $now = $this->now();
            $entry = $status + [
                'form_id' => (int) $form['id'],
                'answers' => json_encode($answers, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'email' => self::declarantEmail($fields, $answers),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $entry['id'] = $this->forms->insertEntry($entry);

            return $entry;
        });
        $this->mailer->sendReceived($form, $fields, ['answers' => $answers] + $entry, $language);

        return [
            'status' => $entry['status'],
            'reason' => $entry['reason'],
            'payment_url' => (string) $form['payment_url'] !== '' ? (string) $form['payment_url'] : null,
        ];
    }

    /** @return array<string, mixed>|null liste publique : confirmées puis en attente, colonnes publiées (FRM-06) */
    public function publicEntries(string $slug, string $language): ?array
    {
        $form = $this->published($slug);
        if ($form === null || (int) $form['public_list'] !== 1) {
            return null;
        }
        $published = json_decode((string) $form['public_columns'], true) ?: [];
        $columns = array_values(array_filter(
            $this->forms->fields((int) $form['id']),
            static fn (array $field): bool => in_array($field['field_key'], $published, true) && FormRules::isPublishable((string) $field['type']),
        ));
        $rows = function (string $status) use ($form, $columns, $language): array {
            return array_map(function (array $entry) use ($columns, $language): array {
                $answers = json_decode((string) $entry['answers'], true) ?: [];

                return array_map(fn (array $field): string => $this->formatter->format($field, $answers[$field['field_key']] ?? null, $language), $columns);
            }, $this->forms->entries((int) $form['id'], [$status]));
        };

        return [
            'title' => CmsRules::localized($form, 'title', $language),
            'columns' => array_map(static fn (array $field): string => CmsRules::localized($field, 'label', $language), $columns),
            'confirmed' => $rows(FormRules::ENTRY_CONFIRMED),
            'waiting' => $rows(FormRules::ENTRY_WAITING),
            'capacity' => $form['capacity'] === null ? null : (int) $form['capacity'],
        ];
    }

    /** @return list<array{slug: string, title: string, state: string}> formulaires publiés d'un événement ou d'une compétition (FRM-10) */
    public function attachedTo(?int $eventId, ?string $competition, string $language): array
    {
        [$season, $code] = $competition === null ? [null, null] : explode('/', $competition, 2);
        $now = $this->now();

        return array_map(static fn (array $form): array => [
            'slug' => (string) $form['slug'],
            'title' => CmsRules::localized($form, 'title', $language),
            'state' => FormRules::state($form, $now),
        ], $this->forms->publishedFor($eventId, $season, $code));
    }

    /** @return list<array{value: string, label: string}> */
    public function competitionOptions(): array
    {
        return $this->options->competitions();
    }

    /** @return array<string, mixed>|null */
    private function published(string $slug): ?array
    {
        $form = $this->forms->findBySlug($slug);

        return $form !== null && $form['status'] === FormRules::STATUS_PUBLISHED ? $form : null;
    }

    /** @param array<string, mixed> $field */
    private function publicField(array $field, string $language): array
    {
        $type = (string) $field['type'];
        $options = match ($type) {
            'club' => $this->options->clubs(),
            'competition' => $this->options->competitions(),
            default => array_map(static fn (array $option): array => [
                'value' => (string) $option['value'],
                'label' => CmsRules::localized($option, 'label', $language) ?: (string) $option['value'],
            ], json_decode((string) $field['options'], true) ?: []),
        };

        return [
            'key' => $field['field_key'],
            'type' => $type,
            'label' => CmsRules::localized($field, 'label', $language),
            'help' => CmsRules::localized($field, 'help', $language),
            'required' => (int) $field['required'] === 1,
            'options' => $options,
            'min' => $field['min_value'] === null ? null : $field['min_value'] + 0,
            'max' => $field['max_value'] === null ? null : $field['max_value'] + 0,
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed>       $answers
     */
    private static function declarantEmail(array $fields, array $answers): string
    {
        foreach ($fields as $field) {
            if ($field['type'] === 'email' && isset($answers[$field['field_key']])) {
                return (string) $answers[$field['field_key']];
            }
        }

        return '';
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s');
    }
}
