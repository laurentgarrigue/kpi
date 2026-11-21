# Débloquer un compte depuis la gestion des utilisateurs (profil 1)

Status: needs-triage
Blocked by: —

Spec : [AUTH_HARDENING.md § 2.3](../../../DOC/developer/in-progress/AUTH_HARDENING.md) (« Le super-admin peut forcer le
déverrouillage via la nouvelle route `DELETE /auth/login-history/{code}` »).

## Constat (recette 2FA, 20/11/2026)

La route prévue par la spec n'existe pas : seul `GET /auth/login-history` est codé, et aucun écran d'app4 ne permet de
débloquer un compte. Aujourd'hui, débloquer quelqu'un demande un accès serveur :
`cache:pool:clear cache.rate_limiter`, qui lève la limite de **toutes** les IP et de **tous** les comptes, et ne lève
pas le verrouillage (423).

## Attendu

- `DELETE /auth/login-history/{code}` : remet à zéro le **verrouillage** (échecs récents de ce compte) et le **quota par
  compte** (`LoginRateLimiter::resetUser`). Ne touche **jamais** au quota par IP : il protège contre le balayage de
  plusieurs comptes, et l'administrateur ne connaît pas l'IP de la personne.
- Mêmes garde-fous que la réinitialisation de la 2FA (`SecondFactorController::reset`) : profil 1 seulement, **avec son
  propre second facteur actif** ; opération journalisée ; e-mail à la personne débloquée.
- app4, gestion des utilisateurs : bouton « Débloquer » sur la fiche (profil 1), visible quand le compte est verrouillé
  ou limité.
- Plutôt que de supprimer des lignes du journal (qui sert d'historique), la remise à zéro peut s'écrire comme une ligne
  de journal « déblocage » qui sert de borne au calcul de `isAccountLocked()`, comme le fait déjà un succès.

## Risque accepté

La ruse par téléphone (« c'est moi, débloque-moi ») ne rend à un attaquant que quelques essais, et le second facteur
reste à franchir.
