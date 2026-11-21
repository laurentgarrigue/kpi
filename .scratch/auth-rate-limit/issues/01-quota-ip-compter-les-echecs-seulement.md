# Quota par IP : ne compter que les échecs (AUTH-RL-01)

Status: ready-for-agent
Blocked by: —

Spec : [AUTH_HARDENING.md § 2, AUTH-RL-01](../../../DOC/developer/in-progress/AUTH_HARDENING.md)

## Constat

AUTH-RL-01 dit : « après **5 échecs** depuis la même IP en 15 min → `429` ». Le code en production compte
**toutes** les tentatives, y compris celles qui réussissent :

- `LoginRateLimiter::consume()` (`sources/api2/src/Security/LoginRateLimiter.php`) consomme un jeton IP et un jeton
  compte **avant** la vérification du mot de passe.
- Une connexion réussie ne remet à zéro que le quota par compte (`resetUser()`). Le quota par IP n'est jamais rendu :
  c'est voulu, d'après le commentaire de `resetUser()`, mais ça contredit la spec.

Cas observé en dev le 2026-11-21 (compte 42054) : 2 mauvais mots de passe puis 3 connexions réussies en 3 min →
la 6ᵉ tentative est refusée (« Trop de tentatives de connexion. Réessayez dans 6 minutes. »,
`failure_reason = rate_limited`).

## Risque

Sur un lieu de compétition, les tables de marque et les bénévoles partagent souvent le même wifi, donc la même IP
publique. Au-delà de **5 connexions en 15 min, même réussies**, les suivantes sont refusées pendant la fenêtre.
Même problème pour un club derrière un NAT ou un réseau mobile en CGNAT.

## Attendu

- Le quota IP (5 / 15 min) **ne consomme un jeton que sur un échec** : `unknown_user`, `bad_password`
  (et l'échec équivalent de `OAuthController`, l. ~255).
- Avant de vérifier les identifiants, on **lit** le quota sans le consommer (par exemple `consume(0)` sur la
  sliding window, ou `reserve()`). S'il est épuisé → `429` + `Retry-After`, comme aujourd'hui.
- Les succès ne consomment rien. On ne remet pas non plus le quota IP à zéro sur un succès : l'argument du
  commentaire actuel (« un compte valide viderait le quota de son IP entre deux salves ») reste valable.
- Le quota par compte (AUTH-RL-02) suit la même règle, pour que les deux quotas restent cohérents.
- Le verrouillage de compte (`isAccountLocked`, `423`) et le journal (`kp_user_login_history`) ne changent pas.

## Critères d'acceptation (tests d'intégration, `AuthLoginHardeningTest`)

1. **Existant, à garder vert** : 5 échecs depuis la même IP → la 6ᵉ tentative répond `429` avec `Retry-After`.
2. **Nouveau** : 6 connexions **réussies** de suite depuis la même IP (comptes différents ou même compte) → toutes
   répondent `200`.
3. **Nouveau** : 4 échecs puis 3 succès depuis la même IP → succès en `200` ; un 5ᵉ échec → `401` ; la tentative
   suivante → `429`.
4. **Existant, à garder vert** : AUTH-RL-02, 10 échecs sur le même compte depuis des IP distinctes → `429`.
5. Même comportement sur le login Google (`OAuthController`) si un test le couvre ; sinon ajouter le cas 2.

À compléter dans la spec : préciser dans AUTH-RL-01 que « les connexions réussies ne sont pas comptées »
(cas 2 ci-dessus en critère de test).

## Contournement en attendant

Débloquer une IP : `docker exec <app>_api2 php bin/console cache:pool:clear cache.rate_limiter`
(vide tous les compteurs de connexion, sans toucher au cache applicatif).

## Comments
