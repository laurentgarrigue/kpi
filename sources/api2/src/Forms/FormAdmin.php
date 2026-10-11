<?php

namespace App\Forms;

use App\Cms\CmsRules;
use App\Cms\HtmlSanitizer;
use App\Cms\ValidationErrors;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/** Formulaires côté app4 (PAGE_CMS.md § 6) : définition, inscriptions, export, journal des e-mails. */
final class FormAdmin
{
    private const FIELD_KEY_PATTERN = '/^[a-z0-9_]{1,40}$/';
    private const MAX_RETENTION_DAYS = 730;
    private const DATE_FORMATS = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i', 'Y-m-d'];

    public function __construct(
        private readonly FormRepository $forms,
        private readonly AnswerFormatter $formatter,
        private readonly FormMailer $mailer,
        private readonly HtmlSanitizer $sanitizer,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        $now = $this->now();

        return array_map(function (array $form) use ($now): array {
            $counts = $this->forms->countByStatus((int) $form['id']);

            return [
                'id' => (int) $form['id'],
                'slug' => $form['slug'],
                'title' => $form['title_fr'],
                'status' => $form['status'],
                'state' => $form['status'] === FormRules::STATUS_PUBLISHED ? FormRules::state($form, $now) : FormRules::STATUS_DRAFT,
                'opens_at' => $form['opens_at'],
                'closes_at' => $form['closes_at'],
                'capacity' => $form['capacity'] === null ? null : (int) $form['capacity'],
                'counts' => $counts,
                'archived_entries' => (int) $form['archived_entries'],
                'imported' => $form['legacy_nf_id'] !== null,
            ];
        }, $this->forms->all());
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $form = $this->forms->find($id);
        if ($form === null) {
            return null;
        }
        $integerOrNull = static fn (mixed $value): ?int => $value === null ? null : (int) $value;

        return [
            'id' => (int) $form['id'],
            'slug' => $form['slug'],
            'title_fr' => $form['title_fr'],
            'title_en' => $form['title_en'],
            'intro_fr' => $form['intro_fr'],
            'intro_en' => $form['intro_en'],
            'status' => $form['status'],
            'opens_at' => $form['opens_at'],
            'closes_at' => $form['closes_at'],
            'capacity' => $integerOrNull($form['capacity']),
            'waiting_list' => (int) $form['waiting_list'] === 1,
            'confirmation_mode' => $form['confirmation_mode'],
            'payment_url' => $form['payment_url'],
            'notify_emails' => $form['notify_emails'],
            'confirmation_text_fr' => $form['confirmation_text_fr'],
            'confirmation_text_en' => $form['confirmation_text_en'],
            'public_list' => (int) $form['public_list'] === 1,
            'public_columns' => json_decode((string) $form['public_columns'], true) ?: [],
            'purpose_fr' => $form['purpose_fr'],
            'purpose_en' => $form['purpose_en'],
            'retention_days' => (int) $form['retention_days'],
            'event_id' => $integerOrNull($form['event_id']),
            'competition' => $form['competition_code'] === null ? null : $form['competition_season'] . '/' . $form['competition_code'],
            'fields' => array_map(static fn (array $field): array => [
                'key' => $field['field_key'],
                'type' => $field['type'],
                'label_fr' => $field['label_fr'],
                'label_en' => $field['label_en'],
                'required' => (int) $field['required'] === 1,
                'help_fr' => $field['help_fr'],
                'help_en' => $field['help_en'],
                'options' => json_decode((string) $field['options'], true) ?: [],
                'min' => $field['min_value'] === null ? null : $field['min_value'] + 0,
                'max' => $field['max_value'] === null ? null : $field['max_value'] + 0,
            ], $this->forms->fields($id)),
            'counts' => $this->forms->countByStatus($id),
            'archived_entries' => (int) $form['archived_entries'],
            'imported' => $form['legacy_nf_id'] !== null,
            'updated_at' => $form['updated_at'],
            'updated_by' => $form['updated_by'],
        ];
    }

    /** @param array<string, mixed> $input */
    public function save(?int $id, array $input, string $userCode): int
    {
        $errors = new ValidationErrors();
        $fields = $this->validFields($input['fields'] ?? null, $errors);
        $values = $this->validForm($input, $id, $fields, $errors);
        $errors->throwIfAny();

        $now = $this->now();
        $values['updated_at'] = $now;
        $values['updated_by'] = $userCode;

        return (int) $this->connection->transactional(function () use ($id, $values, $fields, $now): int {
            if ($id === null) {
                $id = $this->forms->insert($values + ['created_at' => $now]);
            } else {
                $this->forms->update($id, $values);
            }
            $this->forms->replaceFields($id, $fields);

            return $id;
        });
    }

    public function delete(int $id): bool
    {
        if ($this->forms->find($id) === null) {
            return false;
        }
        $this->forms->delete($id);

        return true;
    }

    /** @return array<string, mixed>|null inscriptions avec réponses affichables */
    public function entries(int $id): ?array
    {
        $form = $this->forms->find($id);
        if ($form === null) {
            return null;
        }
        $fields = $this->forms->fields($id);

        return [
            'columns' => array_map(static fn (array $field): array => ['key' => $field['field_key'], 'label' => $field['label_fr']], $fields),
            'entries' => array_map(function (array $entry) use ($fields): array {
                $answers = json_decode((string) $entry['answers'], true) ?: [];

                return [
                    'id' => (int) $entry['id'],
                    'status' => $entry['status'],
                    'reason' => $entry['reason'],
                    'email' => $entry['email'],
                    'created_at' => $entry['created_at'],
                    'answers' => array_combine(
                        array_map(static fn (array $field): string => (string) $field['field_key'], $fields),
                        array_map(fn (array $field): string => $this->formatter->format($field, $answers[$field['field_key']] ?? null, 'fr'), $fields),
                    ) ?: new \stdClass(),
                ];
            }, $this->forms->entries($id)),
            'counts' => $this->forms->countByStatus($id),
        ];
    }

    /** Change le statut d'une inscription ; confirmer une inscription en attente envoie l'e-mail (ADM-CMS-06). */
    public function setEntryStatus(int $formId, int $entryId, string $status): bool
    {
        $form = $this->forms->find($formId);
        $entry = $form === null ? null : $this->forms->findEntry($formId, $entryId);
        if ($entry === null) {
            return false;
        }
        if (!in_array($status, FormRules::ENTRY_STATUSES, true)) {
            throw (new ValidationErrors())->add('status', 'invalid');
        }
        $this->forms->updateEntry($entryId, [
            'status' => $status,
            'reason' => $status === FormRules::ENTRY_WAITING ? FormRules::REASON_MANUAL : null,
            'updated_at' => $this->now(),
        ]);
        if ($status === FormRules::ENTRY_CONFIRMED && $entry['status'] !== FormRules::ENTRY_CONFIRMED) {
            $this->mailer->sendConfirmed($form, $this->forms->fields($formId), ['id' => $entryId, 'answers' => json_decode((string) $entry['answers'], true) ?: []] + $entry);
        }

        return true;
    }

    /**
     * Lignes de l'export (ADM-CMS-07) : en-tête puis une ligne par inscription.
     *
     * @return list<list<string>>|null
     */
    public function exportRows(int $id): ?array
    {
        $entries = $this->entries($id);
        if ($entries === null) {
            return null;
        }
        $rows = [array_merge(['N°', 'Date', 'Statut'], array_column($entries['columns'], 'label'))];
        foreach ($entries['entries'] as $index => $entry) {
            $rows[] = array_merge([(string) ($index + 1), (string) $entry['created_at'], (string) $entry['status']], array_values((array) $entry['answers']));
        }

        return $rows;
    }

    /** @return list<array<string, mixed>>|null */
    public function mailLog(int $id): ?array
    {
        if ($this->forms->find($id) === null) {
            return null;
        }

        return array_map(static fn (array $mail): array => [
            'id' => (int) $mail['id'],
            'recipient' => $mail['recipient'],
            'subject' => $mail['subject'],
            'status' => $mail['status'],
            'error' => $mail['error'],
            'sent_at' => $mail['sent_at'],
            'resendable' => $mail['status'] === 'failed' && $mail['body'] !== null,
        ], $this->forms->mailLog($id));
    }

    public function resend(int $formId, int $mailId): ?string
    {
        $form = $this->forms->find($formId);
        $mail = $form === null ? null : $this->forms->findMail($formId, $mailId);

        return $mail === null ? null : $this->mailer->resend($form, $mail);
    }

    // ---------------------------------------------------------------- validation

    /** @return list<array<string, mixed>> lignes de kp_form_field (sans form_id ni position) */
    private function validFields(mixed $input, ValidationErrors $errors): array
    {
        $fields = [];
        $keys = [];
        foreach (is_array($input) ? $input : [] as $index => $field) {
            $prefix = "fields.$index";
            $field = is_array($field) ? $field : [];
            $key = (string) ($field['key'] ?? '');
            $type = (string) ($field['type'] ?? '');
            if (preg_match(self::FIELD_KEY_PATTERN, $key) !== 1 || in_array($key, $keys, true)) {
                $errors->add("$prefix.key", 'invalid');
            }
            $keys[] = $key;
            if (!in_array($type, FormRules::TYPES, true)) {
                $errors->add("$prefix.type", 'invalid');
            }
            $label = $errors->maxLength("$prefix.label_fr", (string) ($field['label_fr'] ?? ''), 255);
            if ($label === '') {
                $errors->add("$prefix.label_fr", 'required');
            }
            $options = [];
            foreach (is_array($field['options'] ?? null) ? $field['options'] : [] as $option) {
                $value = trim((string) ($option['value'] ?? ''));
                if ($value === '' || in_array($value, array_column($options, 'value'), true)) {
                    $errors->add("$prefix.options", 'invalid');
                    continue;
                }
                $options[] = ['value' => $value, 'label_fr' => trim((string) ($option['label_fr'] ?? $value)), 'label_en' => trim((string) ($option['label_en'] ?? ''))];
            }
            if (in_array($type, ['select', 'radio'], true) && $options === []) {
                $errors->add("$prefix.options", 'required');
            }
            $bound = static fn (mixed $value): ?string => $value === null || $value === '' ? null : (is_numeric($value) ? (string) $value : 'invalid');
            [$min, $max] = [$bound($field['min'] ?? null), $bound($field['max'] ?? null)];
            if ($min === 'invalid' || $max === 'invalid' || ($min !== null && $max !== null && (float) $min > (float) $max)) {
                $errors->add("$prefix.min", 'invalid');
                [$min, $max] = [null, null];
            }
            $fields[] = [
                'field_key' => $key,
                'type' => $type,
                'label_fr' => $label,
                'label_en' => $errors->maxLength("$prefix.label_en", (string) ($field['label_en'] ?? ''), 255),
                'required' => !empty($field['required']) ? 1 : 0,
                'help_fr' => $errors->maxLength("$prefix.help_fr", (string) ($field['help_fr'] ?? ''), 500),
                'help_en' => $errors->maxLength("$prefix.help_en", (string) ($field['help_en'] ?? ''), 500),
                'options' => json_encode(in_array($type, FormRules::TYPES_WITH_OPTIONS, true) ? $options : [], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'min_value' => $min,
                'max_value' => $max,
            ];
        }
        if (!is_array($input)) {
            $errors->add('fields', 'invalid');
        }

        return $fields;
    }

    /**
     * @param array<string, mixed>       $input
     * @param list<array<string, mixed>> $fields
     *
     * @return array<string, mixed>
     */
    private function validForm(array $input, ?int $id, array $fields, ValidationErrors $errors): array
    {
        $values = [];
        foreach (['title' => 255] as $field => $max) {
            foreach (CmsRules::LANGUAGES as $language) {
                $values["{$field}_$language"] = $errors->maxLength("{$field}_$language", (string) ($input["{$field}_$language"] ?? ''), $max);
            }
        }
        if ($values['title_fr'] === '') {
            $errors->add('title_fr', 'required');
        }
        foreach (CmsRules::LANGUAGES as $language) {
            $values["intro_$language"] = $this->sanitizer->sanitize((string) ($input["intro_$language"] ?? ''));
            $values["confirmation_text_$language"] = $errors->maxLength("confirmation_text_$language", (string) ($input["confirmation_text_$language"] ?? ''), 2000);
            $values["purpose_$language"] = $errors->maxLength("purpose_$language", (string) ($input["purpose_$language"] ?? ''), 2000);
        }

        $slug = trim((string) ($input['slug'] ?? '')) ?: CmsRules::slugify($values['title_fr']);
        if (!CmsRules::isValidSlug($slug)) {
            $errors->add('slug', 'invalid');
        } elseif ($this->forms->slugExists($slug, $id)) {
            $errors->add('slug', 'taken');
        }
        $values['slug'] = $slug;

        $status = (string) ($input['status'] ?? FormRules::STATUS_DRAFT);
        if (!in_array($status, FormRules::STATUSES, true)) {
            $errors->add('status', 'invalid');
        }
        $values['status'] = $status;
        if ($status === FormRules::STATUS_PUBLISHED && !in_array(true, array_map(static fn (array $field): bool => $field['type'] === 'email' && $field['required'] === 1, $fields), true)) {
            $errors->add('fields', 'email_required'); // confirmation au déclarant (FEATURE_FORMS.md § 6)
        }

        $values['opens_at'] = $this->date($input['opens_at'] ?? null, 'opens_at', $errors);
        $values['closes_at'] = $this->date($input['closes_at'] ?? null, 'closes_at', $errors);
        if ($values['opens_at'] !== null && $values['closes_at'] !== null && $values['closes_at'] < $values['opens_at']) {
            $errors->add('closes_at', 'before_opening');
        }

        $capacity = $input['capacity'] ?? null;
        $values['capacity'] = null;
        if ($capacity !== null && $capacity !== '') {
            if (filter_var($capacity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                $errors->add('capacity', 'invalid');
            } else {
                $values['capacity'] = (int) $capacity;
            }
        }
        $values['waiting_list'] = !empty($input['waiting_list']) ? 1 : 0;
        $mode = (string) ($input['confirmation_mode'] ?? FormRules::CONFIRMATION_AUTO);
        if (!in_array($mode, FormRules::CONFIRMATION_MODES, true)) {
            $errors->add('confirmation_mode', 'invalid');
        }
        $values['confirmation_mode'] = $mode;

        $payment = trim((string) ($input['payment_url'] ?? ''));
        if ($payment !== '' && preg_match('~^https://[^\s]+$~i', $payment) !== 1) {
            $errors->add('payment_url', 'invalid');
        }
        $values['payment_url'] = $payment;

        $emails = array_values(array_filter(array_map('trim', explode(',', (string) ($input['notify_emails'] ?? '')))));
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors->add('notify_emails', 'invalid');
            }
        }
        $values['notify_emails'] = $errors->maxLength('notify_emails', implode(', ', $emails), 500);

        $values['public_list'] = !empty($input['public_list']) ? 1 : 0;
        $publishable = array_column(array_filter($fields, static fn (array $field): bool => FormRules::isPublishable((string) $field['type'])), 'field_key');
        $columns = array_values(array_unique(array_map('strval', is_array($input['public_columns'] ?? null) ? $input['public_columns'] : [])));
        if (array_diff($columns, $publishable) !== []) {
            $errors->add('public_columns', 'personal'); // FRM-06
        }
        $values['public_columns'] = json_encode($columns, JSON_THROW_ON_ERROR);

        $retention = filter_var($input['retention_days'] ?? 90, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_RETENTION_DAYS]]);
        if ($retention === false) {
            $errors->add('retention_days', 'invalid');
        }
        $values['retention_days'] = $retention === false ? 90 : $retention;

        $event = $input['event_id'] ?? null;
        $values['event_id'] = null;
        if ($event !== null && $event !== '') {
            if ($this->connection->fetchOne('SELECT 1 FROM kp_evenement WHERE Id = ?', [(int) $event]) === false) {
                $errors->add('event_id', 'invalid');
            } else {
                $values['event_id'] = (int) $event;
            }
        }
        [$values['competition_season'], $values['competition_code']] = [null, null];
        $competition = trim((string) ($input['competition'] ?? ''));
        if ($competition !== '') {
            $parts = explode('/', $competition, 2);
            if (count($parts) !== 2 || $this->connection->fetchOne('SELECT 1 FROM kp_competition WHERE Code_saison = ? AND Code = ?', $parts) === false) {
                $errors->add('competition', 'invalid');
            } else {
                [$values['competition_season'], $values['competition_code']] = $parts;
            }
        }

        return $values;
    }

    private function date(mixed $value, string $field, ValidationErrors $errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        foreach (self::DATE_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, (string) $value);
            if ($date !== false) {
                return $format === 'Y-m-d' && $field === 'closes_at' ? $date->format('Y-m-d 23:59:59') : $date->format('Y-m-d H:i:s');
            }
        }
        $errors->add($field, 'invalid');

        return null;
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s');
    }
}
