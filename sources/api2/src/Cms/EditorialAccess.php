<?php

namespace App\Cms;

use App\Entity\User;
use Doctrine\DBAL\Connection;

/**
 * Droits éditoriaux (FEATURE_CMS.md § 4, DROITS_PAR_PROFIL.md § Éditorial) : la capacité « Rédacteur » s'ajoute au
 * profil, quel que soit son niveau ; les profils effectifs 1–2 ont tous les droits sans elle.
 */
final class EditorialAccess
{
    public const EDITOR = 'editor';

    private const ADMIN_MAX_LEVEL = 2;

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<string> capacités du compte */
    public function capabilities(string $userCode): array
    {
        return array_map('strval', $this->connection->fetchFirstColumn(
            'SELECT capability FROM kp_user_capability WHERE user_code = ? ORDER BY capability',
            [$userCode],
        ));
    }

    /** Articles, pages, médias, formulaires. */
    public function canEdit(User $user): bool
    {
        return $this->canAdminister($user) || in_array(self::EDITOR, $this->capabilities($user->getCode()), true);
    }

    /** Menu, accueil, redirections, suppression définitive, attribution de la capacité. */
    public function canAdminister(User $user): bool
    {
        return $user->getEffectiveNiveau() <= self::ADMIN_MAX_LEVEL;
    }

    public function setEditor(string $userCode, bool $editor): void
    {
        if ($editor) {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO kp_user_capability (user_code, capability) VALUES (?, ?)',
                [$userCode, self::EDITOR],
            );

            return;
        }
        $this->connection->executeStatement(
            'DELETE FROM kp_user_capability WHERE user_code = ? AND capability = ?',
            [$userCode, self::EDITOR],
        );
    }

    /** @return list<array{code: string, name: string}> comptes ayant la capacité Rédacteur */
    public function editors(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT u.Code, u.Identite FROM kp_user_capability c INNER JOIN kp_user u ON u.Code = c.user_code
            WHERE c.capability = ? ORDER BY u.Identite, u.Code',
            [self::EDITOR],
        );

        return array_map(static fn (array $row): array => ['code' => (string) $row['Code'], 'name' => (string) ($row['Identite'] ?? '')], $rows);
    }

    public function userExists(string $userCode): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM kp_user WHERE Code = ?', [$userCode]) !== false;
    }
}
