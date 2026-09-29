# Audit : usage du profil principal brut (`getNiveau()`) au lieu du mandat actif (`getEffectiveNiveau()`)

**Statut** : ✅ **Corrections appliquées et lintées** (P1, P2, P3, + deuxième vague : 4 contrôleurs
`#[IsGranted]` cumulatif + resserrement présence niveau 8 + **correctif du cache de rôles Symfony**,
voir `MandateAwareRoleHierarchyVoter`) — reste à **tester en dev** avant merge. Le bug déclencheur
(`autocomplete/players` 403 pour profil 2) est, lui, **corrigé et validé** — voir
[PROFILE_ROLES.md](../../reference/PROFILE_ROLES.md), ce n'était en fait pas un usage de `getNiveau()`
brut mais un cumul d'attributs `#[IsGranted]` (cf. § "À revérifier" ci-dessous) — pattern qui s'est
avéré, lors de tests manuels ultérieurs, **également présent** sur `AdminCompetitionsController`,
`AdminTeamsController`, `AdminRankingsController`, `AdminPresenceController` (voir "Deuxième vague"
ci-dessous), et a mené à la découverte d'un bug plus large et plus grave : **`#[IsGranted]` ignorait
le mandat actif sur tout le projet**, à cause d'un cache de rôles Symfony figé avant l'application du
mandat — également corrigé (voir "Deuxième vague" § cache de rôles).
**Date de l'audit** : 2026-09-22
**Date des corrections** : 2026-09-22 (premier lot), 2026-09-22 (deuxième vague, suite à tests manuels,
y compris le correctif du cache de rôles)
**Contexte de découverte** : bug 403 rapporté sur `GET /admin/operations/autocomplete/players` pour un
utilisateur profil principal 2 (voir aussi [PROFILE_ROLES.md](../../reference/PROFILE_ROLES.md)).

## Corrections appliquées

1. **`AdminStatsController.php`** (lignes 126, 409) — `getNiveau()` → `getEffectiveNiveau()`.
2. **7 contrôleurs transactionnels** (`AdminCompetitionsController`, `AdminPresenceController`,
   `AdminRankingsController`, `AdminGamedaysController`, `AdminCompetitionCopyController`,
   `AdminTeamsController`, `AdminGamesController`) — remplacement mécanique `getNiveau()` →
   `getEffectiveNiveau()` sur les 86 occurrences, chacune revérifiée manuellement au fil du
   remplacement (toutes confirmées comme des contrôles de permission purs, aucun usage volontaire du
   profil de base détecté dans ce lot — contrairement à `switchMandate()` qui, lui, reste inchangé).
3. **`AdminAuthController::me()`/`refresh()`** — nouvelle méthode privée
   `applyActiveMandateToUserData()` qui reconstruit `activeMandate`/`effectiveProfile`/
   `effectiveFilters` à partir de `$user->getActiveMandateId()` (positionné par
   `ActiveMandateListener` depuis l'en-tête `X-Active-Mandate`), en miroir de
   `AdminAuthMandateController::switchMandate()`. `login()` reste inchangé (aucun mandat ne peut être
   actif à cet instant, c'est correct).
4. **Frontend** — `sources/app4/composables/useAuth.ts::refreshToken()` et `checkAuth()` envoient
   désormais l'en-tête `X-Active-Mandate` (repris du store) comme le fait déjà `useApi.ts`.

Tous les fichiers PHP modifiés passent `php -l` (lint via `kpi_api2`), et `make app4_lint` (ESLint)
passe sans erreur sur le frontend.

**Non appliqué / hors-scope de ce lot** : le §"Réaudit `#[IsGranted]` cumulatif" ci-dessous a été
refait pour les 7 contrôleurs listés — **aucune anomalie trouvée**, ils sont sains vis-à-vis de ce
pattern précis (voir section correspondante, mise à jour).

## Deuxième vague de correctifs (2026-09-22) — cumul `#[IsGranted]` sur 4 contrôleurs à checks manuels

Après le premier lot ci-dessus, des tests manuels en dev ont révélé deux problèmes supplémentaires,
non couverts par l'audit initial car celui-ci s'était concentré sur les usages de `getNiveau()` brut
(problème 1) et, séparément, sur 7 contrôleurs *sans* logique manuelle (problème du §"Réaudit"). Les
4 contrôleurs ci-dessous ont **les deux mécanismes en même temps** (checks manuels `getEffectiveNiveau()`
déjà corrects depuis le premier lot, **et** un `#[IsGranted]` de classe trop restrictif) — le second
masquait toute lecture pour niveau 8, indépendamment des checks manuels.

### Bug confirmé : `#[IsGranted('ROLE_TEAM')]` de classe bloque toute lecture pour niveau 8 (Consultation)

Même pattern que `AdminOperationsController` (déjà corrigé, voir plus bas) : `ROLE_TEAM` (niveau ≤ 7)
posé en classe, cumulé avec Symfony (pas d'override), bloque tout niveau 8 (`ROLE_VIEWER`) — **y
compris les routes de simple lecture**, qui n'ont pourtant aucune raison d'exiger plus que
`ROLE_VIEWER`. Reproduit et confirmé empiriquement par génération de JWT réel pour un compte niveau 8
(`lexik:jwt:generate-token`) sur les 5 routes signalées par l'utilisateur, toutes en 403 avant fix,
toutes en 200 après (pour une compétition dans le périmètre `Filtre_competition` du compte test) :
`GET /admin/competitions`, `/admin/competitions-groups`, `/admin/competitions-for-multi`,
`/admin/competitions/{code}`, `/admin/competition-teams`, `/admin/rankings`.

**Contrôleurs corrigés** : `AdminCompetitionsController`, `AdminTeamsController`,
`AdminRankingsController`, `AdminPresenceController`. Même remède que `AdminOperationsController` :
retrait du `#[IsGranted]` de classe, ajout explicite de `#[IsGranted('ROLE_VIEWER')]` sur chaque
méthode `GET` (17 méthodes au total sur les 4 contrôleurs), les méthodes d'écriture restant protégées
par leurs checks manuels `getEffectiveNiveau()` déjà en place (vérifié une à une : aucun trou, toutes
les routes POST/PATCH/DELETE ont bien leur check).

**Piège méthodologique découvert pendant l'investigation, puis corrigé séparément** : un mandat de
test s'est révélé inutile pour reproduire ce genre de bug — `ActiveMandateListener` mute l'objet
`User` sur `kernel.controller`, mais `AccessListener::authenticate()` (qui évalue
`access_control: { path: ^/admin, roles: ROLE_USER }`) tourne sur `kernel.request`, **avant**, et
appelle `$token->getRoleNames()`, qui **mettait le résultat en cache**
(`$this->roleNames ??= $this->user?->getRoles()`). Conséquence : **tout** `#[IsGranted]` du projet —
pas seulement ces 4 contrôleurs — ignorait le mandat, dans les deux sens (ni restriction, ni
élévation). Ce bug, bien plus large que les `#[IsGranted]` de classe traités ici, a été **corrigé** via
`App\Security\MandateAwareRoleHierarchyVoter` — voir
[PROFILE_ROLES.md § "Profil principal vs mandat actif"](../../reference/PROFILE_ROLES.md) pour le
détail complet du mécanisme et du correctif. Sans ce second correctif, celui documenté dans cette
section (retrait des `#[IsGranted]` de classe trop stricts) n'aurait été qu'à moitié utile : les
lectures auraient bien été débloquées pour niveau 8, mais un mandat restrictif n'aurait toujours pas pu
bloquer un profil principal élevé sur aucune route protégée par `#[IsGranted]`.

### Changement de règle métier : présence — niveau 8 (Consultation) doit être strictement lecture seule

Indépendamment du bug ci-dessus, la vérification manuelle a révélé qu'un mandat "Consultation"
(niveau 8) pouvait ajouter/supprimer des joueurs sur une feuille de présence. Ce n'était **pas une
régression** : le seuil `≤ 8` était déjà celui du legacy (`GestionEquipeJoueur.php`, commandes `Add2`/
`Remove`, `$_SESSION['Profile'] <= 8`), donc fidèlement reproduit. Décision utilisateur : resserrer la
règle — **niveau 8 doit être strictement read-only**, l'édition démarre à **niveau 7** (Resp. club/
équipe), qui peut modifier les feuilles de présence des équipes de son club si elles ne sont pas
verrouillées.

**Corrigé** dans `AdminPresenceController.php` (11 occurrences : `getTeamPlayers` (canEdit),
`addTeamPlayer`, `updateTeamPlayer`, `deleteTeamPlayers`, `copyComposition`, `addMatchPlayer`,
`initializeMatchPlayers`, `updateMatchPlayer`, `deleteMatchPlayers`, `clearMatchPlayers`,
`copyMatchPlayersToMatches`) : seuil `> 8`/`<= 8` → `> 7`/`<= 7`. Et côté frontend
`sources/app4/composables/usePresencePermissions.ts` (`canEdit` team/match, `canInitializeFromTeam`,
`canClearAll`) : `authStore.profile <= 8`/`<= 9` → `<= 7`. Les lectures (`GET`) restent ouvertes à
`ROLE_VIEWER` (niveau 8) — seule l'édition est resserrée.

## Résumé

Le mécanisme de rôle Symfony (`#[IsGranted('ROLE_XXX')]`, via `User::getRoles()`) est **correctement**
mandat-aware depuis le commit `f0d4714d` ("Fix: mandates profiles guard", 2026-06-10) : il utilise
`getEffectiveNiveau()` = `mandateNiveau ?? niveau`.

Mais ce fix n'a couvert que `getRoles()` et les méthodes `getAllowedXXX()` (filtrage de données). Un
pattern de bug **du même type, non corrigé**, subsiste dans des **contrôles de permission codés en dur
dans le corps des méthodes de contrôleur** (`if ($user->getNiveau() > X) { ... 403 ... }`), qui
utilisent le champ brut `niveau` du profil principal au lieu de `getEffectiveNiveau()`.

Ce n'est pas un cas isolé : environ **95 occurrences** dans **9 contrôleurs**.

## Mécanisme correct de référence

- `User::getEffectiveNiveau()` (`sources/api2/src/Entity/User.php:176-179`) : `mandateNiveau ?? niveau`.
- Bon exemple à suivre : `AdminUsersController.php` — usage exclusif et cohérent de
  `getEffectiveNiveau()` (22 occurrences), aucun `getNiveau()` brut.
- Contre-exemple révélateur : dans plusieurs des fichiers listés ci-dessous, certaines lignes utilisent
  déjà correctement `getEffectiveNiveau()` pour du **filtrage de données** (ex. `AdminGamedaysController.php:111`,
  `AdminGamesController.php:172,1949`, tous `getEffectiveNiveau() === 7`), mais le même fichier retombe sur
  `getNiveau()` brut pour les **contrôles de permission d'action** juste à côté. Les développeurs
  connaissent donc la bonne méthode ; l'oubli est localisé aux contrôles de permission, pas au filtrage.

## Impact fonctionnel

Un utilisateur opérant sous un mandat actif (header `X-Active-Mandate`) dont le niveau diffère de son
profil principal se voit appliquer les règles de **profil principal** dans ces contrôles manuels,
alors que le reste de la requête (routing `#[IsGranted]`, filtrage de données) applique bien les règles
du **mandat** :

- **Mandat qui élève les privilèges** (ex. profil principal niveau 8, mandat organisateur niveau 4) :
  actions bloquées à tort (403 applicatif renvoyé manuellement) alors que la route elle-même les
  autoriserait. Symptôme : incohérence UX, fonctionnalité indisponible sans raison apparente.
- **Mandat qui restreint les privilèges** (ex. profil principal niveau 2, mandat cloisonné niveau 8) :
  ces contrôles manuels **laissent passer** des actions sensibles (delete, publish, bulk*) que le
  mandat était censé interdire. C'est le scénario le plus grave — un contournement de la restriction
  voulue par le mandat, potentiellement un problème de sécurité selon le contexte métier des mandats
  concernés.

## Inventaire par sévérité

### Priorité 1 — `AdminStatsController.php` (aucun filet `#[IsGranted]` de classe)

- Fichier : `sources/api2/src/Controller/AdminStatsController.php`
- Lignes : 126, 409 — méthodes `getData()` et `exportPdf()`.
- `$profile = $user->getNiveau()`, comparé à `> 6` pour restreindre l'accès à des types de stats
  sensibles (`CJouees3`, `LicenciesNationaux`, `CoherenceMatchs`, ...).
- **Ce contrôleur n'a aucun `#[IsGranted]` de classe** — ce contrôle manuel bugué est donc la
  **seule protection** pour ces données. C'est le cas le plus exposé de l'audit : à traiter en premier.

### Priorité 2 — contrôleurs à fort usage transactionnel (create/update/delete/publish/bulk)

| Contrôleur | Lignes concernées | Méthodes |
|---|---|---|
| `AdminCompetitionsController.php` | 424, 527, 640, 706, 788, 828, 868, 886 | `create`, `update`, `delete`, `bulkDelete`, `togglePublish`, `toggleLock`, `changeStatus` (x2) |
| `AdminPresenceController.php` | 78, 172, 186, 241, 322, 382, 541, 762, 818, 868, 924, 963, 1111 | quasi toutes les méthodes de gestion de présence |
| `AdminRankingsController.php` | 100, 200, 285, 359, 433, 476, 523, 648, 685, 755, 798, 845 | `compute`, `publish`, `unpublish`, `inlineEdit`, `toggleConsolidation`, `deletePhaseTeam`, `transfer`, `transferCompetitions`, `initialList`, `initialEdit`, `initialReset`, `justification` |
| `AdminGamedaysController.php` | 279, 362, 414, 459, 491, 523, 596, 679, 735, 784, 857, 937 | `create`, `update` (x2), `togglePublication`, `toggleType`, `inlineUpdate`, `duplicate`, `delete`, `bulkPublication`, `bulkCalendar`, `bulkOfficials`, `bulkDelete` |
| `AdminCompetitionCopyController.php` | 51, 160, 263, 329, 540 | `searchSchemas`, `getCopyDetail`, `getCompetitionOptions`, `copyCompetition`, `updateComments` |
| `AdminTeamsController.php` | 125, 174, 214, 359, 497, 559, 649, 703, 803, 909, 986, 1088 | `searchTeams`, `getCompositions`, `searchClubs`, `create`, `delete`, `bulkDelete`, `updatePoolDraw`, `updateColors`, `duplicate`, `updateLogos`, `initStarters`, `toggleLock` |
| `AdminGamesController.php` | 392, 467, 561, 636, 640, 713, 747, 781, 813, 862, 894, 955, 992, 1056, 1099, 1141, 1177, 1214, 1265, 1307, 1369, 1424, 1491, 1767 | quasi toutes les actions de gestion de matchs (create/update/delete/toggle*/bulk*) |

### Priorité 3 — cohérence des réponses d'authentification (UX, pas une faille de sécurité)

- `sources/api2/src/Controller/AdminAuthController.php` — `me()` (L121) et `refresh()` (L152) :
  renvoient `effectiveProfile = $user->getNiveau()` et `activeMandate = null` sans tenir compte du
  mandat éventuellement actif sur `$user` au moment de l'appel (positionné par `ActiveMandateListener`
  si le header `X-Active-Mandate` est présent).
  - `login()` (L92) est correct : à ce stade, aucun mandat n'est encore choisi.
  - Bug à double face : côté frontend, `sources/app4/composables/useAuth.ts::refreshToken()` et
    `checkAuth()` font un `fetch()` natif **sans passer par `useApi.ts`**, donc sans jamais envoyer
    `X-Active-Mandate` — même corrigé côté backend, le mandat ne serait pas transmis sur ces deux
    appels précis.
  - Effet observable : après un refresh silencieux de token (toutes les 45 min via
    `sources/app4/plugins/token-refresh.client.ts` pour profile < 7), l'UI peut perdre l'affichage du
    mandat actif — l'utilisateur croit toujours agir sous son mandat mais le store le recalcule sur le
    profil de base. Les routes API elles-mêmes restent protégées côté serveur (mandat-aware via
    `#[IsGranted]` sur les vrais appels métier, qui eux passent par `useApi.ts`), donc pas de trou de
    sécurité ici — uniquement une UX incohérente.

## Points vérifiés sans anomalie (pour ne pas les re-auditer)

- `AdminUsersController.php` : référence propre, aucun `getNiveau()` brut.
- `AdminAuthMandateController::switchMandate()` (lignes 51, 93, 122) : usages de `getNiveau()`
  **corrects et volontaires**, cas du retour explicite au profil de base (`mandateId === null`).
- `ScoringController` : `getAllowedJournees()` mandat-aware ; restriction `ROLE_ADMIN` documentée
  comme choix produit temporaire assumé ("Experimentation phase"), pas une anomalie.
- Pas d'accès direct aux getters bruts `getFiltreCompetition/Saison/Journee/IdEvenement/LimitClubs()`/
  `getClub()` en dehors de `User.php` — le filtrage de données (listes visibles) est globalement sain
  à travers tout `src/Controller`.
- Aucun Voter Symfony custom dans le projet (`src/Security/` ne contient que `UserProvider.php`, sans
  logique de rôle).

### ✅ Réaudité le 2026-09-22 : aucun bug de cumul `#[IsGranted]` trouvé dans ces 7 contrôleurs

L'audit initial jugeait `AdminRefereesPoolController`, `AdminSchemaController`, `AdminGroupsController`,
`AdminJournalController`, `AdminEventController`, `AdminRcController`, `AdminClubsController` comme
sans anomalie malgré un « héritage de rôle de classe sans override », en supposant qu'un
`#[IsGranted]` de méthode **remplace** celui de la classe. **Cette hypothèse est fausse** — voir
[PROFILE_ROLES.md § "IsGranted ne fait PAS d'override"](../../reference/PROFILE_ROLES.md) : Symfony
cumule les deux et exige que tous les attributs `#[IsGranted]` trouvés (classe + méthode) soient
satisfaits. Le cas réel rencontré (`AdminOperationsController`) le confirme : un
`#[IsGranted('ROLE_ADMIN')]` de méthode posé à côté d'un `#[IsGranted('ROLE_SUPER_ADMIN')]` de classe
ne changeait strictement rien — 7 méthodes de ce contrôleur étaient bloquées pour tout profil ≠ 1
depuis leur écriture, sans que personne ne l'ait remarqué.

Ces 7 contrôleurs ont donc été **réaudités spécifiquement pour ce pattern** (classe + méthodes,
comparaison des rôles, cross-check des appelants frontend `app4`) — **résultat : sains**.

⚠️ **Ce réaudit ciblait les 7 contrôleurs listés ci-dessous, choisis car sans logique manuelle
`getNiveau()`/`getEffectiveNiveau()`.** Le même pattern de cumul `#[IsGranted]` s'est en fait révélé
**également présent** sur 4 *autres* contrôleurs — `AdminCompetitionsController`,
`AdminTeamsController`, `AdminRankingsController`, `AdminPresenceController` — qui, eux, avaient déjà
des checks manuels corrects (corrigés dans le premier lot ci-dessus), ce qui les avait fait passer
sous le radar du réaudit initial. Voir "Deuxième vague de correctifs" plus haut pour le détail. La
leçon : la présence de checks manuels corrects n'exempte pas un contrôleur d'un audit `#[IsGranted]`
de classe — les deux mécanismes coexistent et se cumulent indépendamment.

| Contrôleur | Rôle de classe | `#[IsGranted]` de méthode trouvés | Verdict |
|---|---|---|---|
| `AdminRefereesPoolController` | `ROLE_ADMIN` | aucun | RAS |
| `AdminSchemaController` | `ROLE_DIVISION` | aucun | RAS |
| `AdminGroupsController` | `ROLE_ADMIN` | `delete()` → `ROLE_SUPER_ADMIN` (plus strict, voulu, docblock explicite) | RAS |
| `AdminJournalController` | `ROLE_ADMIN` | aucun | RAS |
| `AdminEventController` | `ROLE_ADMIN` | `delete()`, `bulkDelete()` → `ROLE_SUPER_ADMIN` (plus strict, voulu, docblock explicite) | RAS |
| `AdminRcController` | `ROLE_ADMIN` | `create/update/delete/bulkDelete` → `ROLE_ADMIN` (égal, redondant mais inoffensif) | RAS |
| `AdminClubsController` | `ROLE_USER` (lecture large, volontaire) | `update/create/createDepartmentalCommittee` → `ROLE_ADMIN` (plus strict, voulu — écriture restreinte, lecture ouverte) | RAS |

Dans les 7 cas, chaque `#[IsGranted]` de méthode est **égal ou plus strict** que celui de la classe
(cumul sans effet pervers), jamais l'inverse. Aucun signal frontend (`grep -rn` sur `sources/app4/`)
n'indique qu'une page de profil bas appellerait une route bloquée à tort par ce cumul. `security.yaml
::role_hierarchy` vérifiée conforme à [PROFILE_ROLES.md](../../reference/PROFILE_ROLES.md).
Aucune correction nécessaire pour ces 7 contrôleurs.

## Bug sœur déjà corrigé et validé dans le même tour (référence)

`AdminOperationsController.php` portait un `#[IsGranted('ROLE_SUPER_ADMIN')]` de **classe**
(contrôleur "Super Admin only" par docblock, mais utilisé aussi depuis des pages non-super-admin
comme la feuille de présence). Trois routes d'autocomplete (`searchPlayers()`, `searchTeams()`,
`searchClubs()`) en héritaient à tort. Le correctif n'est **pas** un simple ajout de
`#[IsGranted('ROLE_VIEWER')]` par méthode — un tel ajout à côté du `#[IsGranted]` de classe n'aurait
aucun effet, cf. § "À revérifier" ci-dessous — mais le retrait complet du `#[IsGranted]` de classe,
chaque méthode déclarant désormais explicitement son propre rôle. Corrigé et **validé en conditions
réelles** (2026-09-22). Voir [PROFILE_ROLES.md](../../reference/PROFILE_ROLES.md) pour le détail
complet.

**Ce n'est pas le même bug** que celui documenté ici (cumul d'attributs `#[IsGranted]` vs. logique
manuelle utilisant le mauvais champ de niveau), mais il partage la même cause racine générale : une
fonctionnalité générique réutilisée depuis un contexte au périmètre de droits plus étroit, sans
réévaluer le contrôle d'accès pour son usage réel. C'est d'ailleurs cette découverte qui a mis en
évidence le pattern décrit au § "À revérifier" ci-dessus (désormais résolu, cf. section
correspondante plus haut), qui a été appliqué aux 7 autres contrôleurs concernés (résultat : sains).

## Suivi (tests manuels en dev)

Pas de tests automatisés (PHPUnit/Playwright) ne couvrent actuellement ces routes — à ajouter dans un
chantier séparé pour éviter une régression future du même type. En attendant, validation manuelle en
dev nécessaire avant merge (voir checklist fournie séparément).
