<?php

namespace App\Forms;

use Doctrine\DBAL\Connection;

/** Accès à kp_form, kp_form_field, kp_form_entry et kp_mail_log (FEATURE_FORMS.md § 4). */
final class FormRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_form WHERE id = ?', [$id]);

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_form WHERE slug = ?', [$slug]);

        return $row === false ? null : $row;
    }

    /** Verrouille la ligne du formulaire jusqu'à la fin de la transaction (capacité, FRM-03). */
    public function lock(int $id): void
    {
        $this->connection->fetchOne('SELECT id FROM kp_form WHERE id = ? FOR UPDATE', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_form ORDER BY COALESCE(closes_at, created_at) DESC, id DESC');
    }

    /** @return list<array<string, mixed>> formulaires publiés rattachés à un événement ou une compétition */
    public function publishedFor(?int $eventId, ?string $season, ?string $code): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT * FROM kp_form WHERE status = 'published'
            AND ((? IS NOT NULL AND event_id = ?) OR (? IS NOT NULL AND competition_season = ? AND competition_code = ?))
            ORDER BY opens_at, id",
            [$eventId, $eventId, $season, $season, $code],
        );
    }

    public function slugExists(string $slug, ?int $exceptId): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM kp_form WHERE slug = ? AND id <> ?', [$slug, $exceptId ?? 0]) !== false;
    }

    /** @param array<string, mixed> $values */
    public function insert(array $values): int
    {
        $this->connection->insert('kp_form', $values);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, mixed> $values */
    public function update(int $id, array $values): void
    {
        $this->connection->update('kp_form', $values, ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->connection->delete('kp_form_entry', ['form_id' => $id]);
        $this->connection->delete('kp_form_field', ['form_id' => $id]);
        $this->connection->delete('kp_form', ['id' => $id]);
    }

    /** @return list<array<string, mixed>> champs dans l'ordre */
    public function fields(int $formId): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_form_field WHERE form_id = ? ORDER BY position, id', [$formId]);
    }

    /** @param list<array<string, mixed>> $fields */
    public function replaceFields(int $formId, array $fields): void
    {
        $this->connection->delete('kp_form_field', ['form_id' => $formId]);
        foreach ($fields as $position => $field) {
            $this->connection->insert('kp_form_field', $field + ['form_id' => $formId, 'position' => $position + 1]);
        }
    }

    /** @return array<string, int> nombre d'inscriptions par statut */
    public function countByStatus(int $formId): array
    {
        $counts = array_fill_keys(FormRules::ENTRY_STATUSES, 0);
        foreach ($this->connection->fetchAllAssociative('SELECT status, COUNT(*) AS n FROM kp_form_entry WHERE form_id = ? GROUP BY status', [$formId]) as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * @param list<string> $statuses
     *
     * @return list<array<string, mixed>> inscriptions dans l'ordre d'arrivée
     */
    public function entries(int $formId, array $statuses = FormRules::ENTRY_STATUSES): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT * FROM kp_form_entry WHERE form_id = ? AND status IN (?) ORDER BY created_at, id',
            [$formId, $statuses],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ArrayParameterType::STRING],
        );
    }

    /** @return array<string, mixed>|null */
    public function findEntry(int $formId, int $entryId): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_form_entry WHERE form_id = ? AND id = ?', [$formId, $entryId]);

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $values */
    public function insertEntry(array $values): int
    {
        $this->connection->insert('kp_form_entry', $values);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, mixed> $values */
    public function updateEntry(int $entryId, array $values): void
    {
        $this->connection->update('kp_form_entry', $values, ['id' => $entryId]);
    }

    /** @param array<string, mixed> $values */
    public function logMail(array $values): int
    {
        $this->connection->insert('kp_mail_log', $values);

        return (int) $this->connection->lastInsertId();
    }

    /** @return list<array<string, mixed>> */
    public function mailLog(int $formId): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_mail_log WHERE form_id = ? ORDER BY sent_at DESC, id DESC', [$formId]);
    }

    /** @return array<string, mixed>|null */
    public function findMail(int $formId, int $mailId): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_mail_log WHERE form_id = ? AND id = ?', [$formId, $mailId]);

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed> $values */
    public function updateMail(int $mailId, array $values): void
    {
        $this->connection->update('kp_mail_log', $values, ['id' => $mailId]);
    }

    // ---------------------------------------------------------------- purge (RGPD)

    /**
     * Supprime les inscriptions des formulaires dont la conservation est échue (clôture, à défaut création, +
     * durée de conservation) en gardant leur nombre ; renvoie le nombre d'inscriptions supprimées.
     */
    public function purgeExpiredEntries(string $today): int
    {
        $expired = "DATE_ADD(DATE(COALESCE(f.closes_at, f.created_at)), INTERVAL f.retention_days DAY) < ?";
        $this->connection->executeStatement(
            "UPDATE kp_form f SET f.archived_entries = f.archived_entries
                + (SELECT COUNT(*) FROM kp_form_entry e WHERE e.form_id = f.id AND e.status <> 'cancelled')
            WHERE $expired",
            [$today],
        );

        return (int) $this->connection->executeStatement(
            "DELETE e FROM kp_form_entry e INNER JOIN kp_form f ON f.id = e.form_id WHERE $expired",
            [$today],
        );
    }

    /** Efface le corps des e-mails journalisés depuis plus de `$days` jours. */
    public function purgeMailBodies(string $before): int
    {
        return (int) $this->connection->executeStatement('UPDATE kp_mail_log SET body = NULL WHERE body IS NOT NULL AND sent_at < ?', [$before]);
    }
}
