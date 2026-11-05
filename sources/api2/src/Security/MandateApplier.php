<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\DBAL\Connection;

/**
 * Applies one of the user's mandates (kp_user_mandat) to the User entity, so that its roles and perimeter
 * (getAllowed*()) are the mandate's. Used for the X-Active-Mandate header and for signed PDF links.
 */
class MandateApplier
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** False when the mandate does not exist or does not belong to the user: nothing is applied. */
    public function apply(User $user, int $mandateId): bool
    {
        if ($mandateId <= 0) {
            return false;
        }

        $row = $this->connection->fetchAssociative(
            'SELECT id, niveau, filtre_saison, filtre_competition, limitation_equipe_club, filtre_journee, id_evenement
             FROM kp_user_mandat WHERE id = ? AND user_code = ?',
            [$mandateId, $user->getCode()]
        );
        if (!$row) {
            return false;
        }

        $user->applyMandate(
            (int) $row['id'],
            (int) $row['niveau'],
            $row['filtre_saison'],
            $row['filtre_competition'],
            $row['limitation_equipe_club'],
            $row['filtre_journee'],
            $row['id_evenement']
        );

        return true;
    }
}
