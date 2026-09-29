# Profils utilisateur & rôles Symfony (api2)

Ce document fait le lien entre le **niveau de profil numérique** legacy (`kp_user.Niveau`,
`$_SESSION['Profile']` côté PHP legacy) et les **rôles Symfony** utilisés par api2 pour les
contrôles d'accès (`#[IsGranted(...)]`).

## Table de correspondance

| Niveau | Libellé métier (legacy) | Rôle Symfony propre (`getRoles()`) | Rôles hérités via `role_hierarchy` |
|---|---|---|---|
| ≤ 1 | Super Admin | `ROLE_SUPER_ADMIN` | ROLE_ADMIN, ROLE_DIVISION, ROLE_DELEGATE, ROLE_COMPETITION, ROLE_ORGANIZER, ROLE_TEAM, ROLE_VIEWER, ROLE_SCORER, ROLE_USER |
| 2 | Bureau CNAKP | `ROLE_ADMIN` | ROLE_DIVISION, ROLE_DELEGATE, ROLE_COMPETITION, ROLE_ORGANIZER, ROLE_TEAM, ROLE_VIEWER, ROLE_SCORER, ROLE_USER |
| 3 | Resp. Division | `ROLE_DIVISION` | ROLE_COMPETITION, ROLE_ORGANIZER, ROLE_TEAM, ROLE_VIEWER, ROLE_SCORER, ROLE_USER |
| 4 | Resp. Compétition | `ROLE_COMPETITION` | ROLE_ORGANIZER, ROLE_TEAM, ROLE_VIEWER, ROLE_SCORER, ROLE_USER |
| 5 | Délégué fédéral | `ROLE_DELEGATE` | ROLE_ORGANIZER, ROLE_TEAM, ROLE_VIEWER, ROLE_SCORER, ROLE_USER |
| 6 | Organisateur journée | `ROLE_ORGANIZER` | ROLE_TEAM, ROLE_VIEWER, ROLE_SCORER, ROLE_USER |
| 7 | Resp. club/équipe | `ROLE_TEAM` | ROLE_VIEWER, ROLE_SCORER, ROLE_USER |
| 8 | Consultation | `ROLE_VIEWER` | ROLE_SCORER, ROLE_USER |
| 9 | Table de marque | `ROLE_SCORER` | ROLE_USER |
| ≥ 10 (défaut) | Inutilisé | `ROLE_USER` | — |

Sources :
- `sources/api2/src/Entity/User.php::getRoles()` — calcule le rôle propre à partir de
  `getEffectiveNiveau()`.
- `sources/api2/config/packages/security.yaml::role_hierarchy` — définit l'héritage (un rôle
  donné satisfait aussi tous les rôles listés à sa droite, en cascade).

**Attention à la hiérarchie** : `ROLE_DIVISION` (niveau 3) et `ROLE_DELEGATE` (niveau 5) sont deux
branches distinctes sous `ROLE_ADMIN`, mutuellement exclusives — un niveau 3 n'a pas `ROLE_DELEGATE`
et inversement. Un contrôleur qui doit accepter indifféremment les deux doit soit exiger
`ROLE_ADMIN` (accessible aux niveaux ≤ 2 seulement), soit lister explicitement
`#[IsGranted('ROLE_DIVISION')]` **et** `#[IsGranted('ROLE_DELEGATE')]` selon le besoin (à examiner
au cas par cas plutôt que de supposer une hiérarchie totale).

## Profil principal vs mandat actif

Un compte a un **profil principal** (`kp_user.Niveau`) mais peut aussi agir sous un **mandat**
(ligne `kp_user_mandat`) qui porte son propre niveau, potentiellement différent.

- `User::getEffectiveNiveau()` (`sources/api2/src/Entity/User.php`) retourne
  `$this->mandateNiveau ?? $this->niveau` — le niveau du mandat actif prime s'il est positionné,
  sinon on retombe sur le profil principal.
- `getRoles()`, `toArray()` et les méthodes `getAllowedCompetitions()`/`getAllowedSeasons()`/etc.
  utilisent tous `getEffectiveNiveau()` — donc mandat-aware de bout en bout depuis le correctif
  `f0d4714d` ("Fix: mandates profiles guard", 2026-06-10).
- Le mandat actif est **stateless** : ni en session serveur, ni dans le JWT. Le frontend
  (`sources/app4/stores/authStore.ts`) le garde en mémoire/localStorage et le renvoie à chaque
  requête via l'en-tête `X-Active-Mandate` (`sources/app4/composables/useApi.ts`).
  `ActiveMandateListener` (`sources/api2/src/EventListener/ActiveMandateListener.php`, sur
  `kernel.controller`) lit cet en-tête, vérifie que le mandat appartient bien au compte authentifié,
  et appelle `User::applyMandate()` avant l'évaluation des `#[IsGranted(...)]` de méthode (qui se fait
  sur `kernel.controller_arguments`, donc après `kernel.controller`).

  ⚠️➡️✅ **Piège découvert et corrigé (2026-09-22) : le cache de rôles du token rendait tout
  `#[IsGranted]` aveugle au mandat.** `AccessListener::authenticate()` (qui applique
  `access_control: { path: ^/admin, roles: ROLE_USER }`, voir plus bas) tourne sur `kernel.request` —
  **avant** `ActiveMandateListener` (`kernel.controller`) — et appelle `$token->getRoleNames()`. Cette
  méthode (`vendor/symfony/security-core/.../AbstractToken.php`) fait
  `$this->roleNames ??= $this->user?->getRoles()` : le résultat était **mis en cache sur le token dès
  ce premier appel**, avec les rôles du **profil principal** (le mandat n'est pas encore appliqué à ce
  stade). Toute évaluation ultérieure de rôle dans la même requête — y compris tous les
  `#[IsGranted(...)]` de classe et de méthode qui suivent, via `RoleHierarchyVoter::extractRoles()`
  qui lit aussi `$token->getRoleNames()` — lisait ce cache, jamais un `$user->getRoles()` frais.
  **Conséquence : un mandat ne restreignait ni n'élevait jamais les rôles Symfony**, alors qu'il
  restreignait/élevait bien `getEffectiveNiveau()` (checks manuels, `getAllowedXXX()`) — seule la
  logique manuelle et le filtrage de périmètre étaient mandat-aware, pas `#[IsGranted]`. Un compte
  profil principal 2 + mandat niveau 8 passait donc **tout** `#[IsGranted]` comme un profil 2, quel
  que soit le rôle exigé.

  **Correctif** : `App\Security\MandateAwareRoleHierarchyVoter`
  (`sources/api2/src/Security/MandateAwareRoleHierarchyVoter.php`) remplace le service
  `security.access.role_hierarchy_voter` (voir `sources/api2/config/services.yaml`) — il étend
  `RoleHierarchyVoter` et override `extractRoles()` pour lire `$token->getUser()->getRoles()`
  directement (jamais mis en cache, recalcul pur depuis `getEffectiveNiveau()`) au lieu de
  `$token->getRoleNames()`. Même service ID que l'original, donc remplacement pur — pas de double
  vote. Validé empiriquement dans les deux sens sur `/admin/journal` (`ROLE_ADMIN` de classe) :
  profil principal 2 + mandat niveau 8 → 403 (restriction, était 200 à tort avant) ; profil principal
  8 + mandat niveau 2 → 200 (élévation). Non-régression confirmée sur toutes les routes des deux
  vagues de correctifs de
  [MANDATE_NIVEAU_BRUT_AUDIT.md](../fixes/bugs/MANDATE_NIVEAU_BRUT_AUDIT.md).
- **Sans en-tête `X-Active-Mandate`**, c'est toujours le profil principal qui s'applique — ce n'est
  pas un bug, c'est le comportement attendu quand aucun mandat n'est sélectionné côté frontend.
- **Le header ne porte qu'un ID, jamais un niveau** : `ActiveMandateListener` (lignes 57-65) charge le
  mandat en base avec `WHERE id = ? AND user_code = ?` — le `user_code` vient de l'utilisateur
  authentifié par le JWT (`$token->getUser()`), pas d'un champ du header. Si le mandat demandé
  n'existe pas ou n'appartient pas à ce compte, la requête ne retourne rien et `applyMandate()` n'est
  jamais appelé (le profil principal reste actif). Le `niveau` effectivement appliqué vient toujours
  de la colonne `kp_user_mandat.niveau` en base, jamais d'une valeur envoyée par le client. Un
  utilisateur ne peut donc ni usurper le mandat de quelqu'un d'autre, ni s'auto-attribuer un niveau
  arbitraire via ce header — aucune élévation de privilège possible par ce canal.
- **Les filtres périmètre (compétitions, saisons, clubs, ...) suivent la même logique stricte
  mandat-ou-principal, jamais les deux cumulés.** `getAllowedCompetitions()` (et de façon identique
  `getAllowedSeasons()`, `getAllowedEvents()`, `getAllowedJournees()`, `getAllowedClubs()`,
  `User.php:208-253`) fait :
  ```php
  if ($this->activeMandateId !== null) {
      return self::parsePipeFilter($this->mandateFiltreCompetition); // filtre du mandat SEUL
  }
  return self::parsePipeFilter($this->filtreCompetition); // filtre du profil principal SEUL
  ```
  Exemple : profil principal niveau 5 restreint aux compétitions X, mandat niveau 3 restreint aux
  compétitions Y actif → `getEffectiveNiveau()` retourne 3 (mandat prioritaire) et
  `getAllowedCompetitions()` ne retourne que Y. Les compétitions X ne sont **pas** accessibles tant
  que ce mandat est actif, même si le profil principal y donnait accès — les deux périmètres ne se
  cumulent jamais dans la même requête. Il faut désactiver le mandat côté frontend (ne plus envoyer
  `X-Active-Mandate`) pour retrouver l'accès aux compétitions du profil principal.

## ⚠️ `#[IsGranted]` ne fait PAS d'override entre classe et méthode — il cumule

Piège central, découvert en corrigeant le cas ci-dessous : poser `#[IsGranted('ROLE_ADMIN')]` sur une
méthode d'un contrôleur dont la classe porte `#[IsGranted('ROLE_SUPER_ADMIN')]` **n'abaisse pas**
l'exigence à `ROLE_ADMIN`. Symfony fusionne les deux :

- `ControllerEvent::getAttributes()` (`vendor/symfony/http-kernel/Event/ControllerEvent.php`) fait
  `array_merge($class?->getAttributes() ?? [], $this->controllerReflector->getAttributes())` — tous
  les `#[IsGranted]` trouvés, classe **et** méthode, sont collectés ensemble.
- `IsGrantedAttributeListener::onKernelControllerArguments()`
  (`vendor/symfony/security-http/EventListener/IsGrantedAttributeListener.php`) boucle sur **chacun**
  de ces attributs et exige qu'**ils soient tous satisfaits**. Un seul échec suffit à lever
  l'`AccessDeniedException`.

Résultat concret : `#[IsGranted('ROLE_SUPER_ADMIN')]` (classe) + `#[IsGranted('ROLE_ADMIN')]`
(méthode) exige **les deux**, ce qui revient à exiger `ROLE_SUPER_ADMIN` seul — le rôle le plus
restrictif des deux gagne toujours. Un "override" plus permissif au niveau méthode ne sert donc à
rien tant que la classe garde un `#[IsGranted]` plus restrictif.

**Conséquence pour tout contrôleur avec un `#[IsGranted]` de classe** : soit toutes les méthodes
doivent réellement accepter le rôle de la classe, soit il ne faut **pas** mettre de `#[IsGranted]` de
classe du tout et le poser explicitement sur chaque méthode individuellement (c'est l'approche
retenue pour `AdminOperationsController`, voir ci-dessous).

### Cas corrigé : `AdminOperationsController` (searchPlayers, searchTeams, searchClubs + tout le reste)

- Contrôleur : portait `#[IsGranted('ROLE_SUPER_ADMIN')]` au niveau classe ("Super Admin only" par
  docblock, mais utilisé aussi depuis des pages non-super-admin).
- `searchPlayers()` (`GET /admin/operations/autocomplete/players`) est utilisée par :
  1. Feuille de présence d'équipe (`sources/app4/pages/presence/team/[teamId].vue`) — ajout d'un
     joueur existant, détection de doublon.
  2. Assignation d'un Responsable de Compétition (`sources/app4/pages/rc/index.vue`).
  3. Fusion de joueurs, module Operations super-admin
     (`sources/app4/components/operations/PlayersTab.vue`) — seul ce cas correspond réellement au
     périmètre "Super Admin" du contrôleur.
  `searchTeams()` et `searchClubs()` sont dans le même cas (utilisées par `ClubAutocomplete.vue` et
  consorts depuis la même page présence).
- Legacy (`sources/admin/GestionEquipeJoueur.php`) : la commande "Find" équivalente était ouverte
  jusqu'au **profil 8** (`$_SESSION['Profile'] <= 8`).
- Note pour qui relirait un premier correctif intermédiaire dans l'historique git : ajouter
  `#[IsGranted('ROLE_VIEWER')]` sur les 3 méthodes de recherche **sans retirer** le
  `#[IsGranted('ROLE_SUPER_ADMIN')]` de classe ne suffit pas — cumul, pas override, cf. ci-dessus. Ça
  reste bloqué en 403 pour un profil 2.
- **Correctif appliqué** : suppression du `#[IsGranted('ROLE_SUPER_ADMIN')]` de **classe**. Chaque
  méthode du contrôleur (28 routes) déclare désormais explicitement son propre `#[IsGranted(...)]` :
  - `ROLE_VIEWER` sur `searchPlayers()`, `searchTeams()`, `searchClubs()` (niveaux ≤ 8, aligné legacy).
  - `ROLE_ADMIN` sur les 7 méthodes de gestion de saison qui l'avaient déjà (`listSeasons`,
    `addSeason`, `updateSeason`, `deleteSeason`, `activateSeasonPreview`, `activateSeason`,
    `copyRc`) — **ces méthodes étaient probablement bloquées pour un profil 2 depuis leur écriture**,
    exactement par ce même bug de cumul, sans que personne ne l'ait remarqué.
  - `ROLE_SUPER_ADMIN` explicite sur toutes les autres (images, fusion joueurs/équipes, changement de
    code, import/export, PCE, purge cache, verrous) — préserve le comportement actuel pour les
    véritables opérations super-admin.
- **Statut : corrigé et validé** — test direct (JWT généré pour niveau 2 et niveau 1, `curl` interne
  au conteneur : `autocomplete/players|clubs|teams` → 200 pour profil 2, `images/types` toujours 403
  pour profil 2 / 200 pour profil 1) puis confirmé en conditions réelles par l'utilisateur 2001529
  (profil 2) sur la feuille de présence en dev (2026-09-22).

## Bug apparenté mais distinct : `getNiveau()` brut au lieu de `getEffectiveNiveau()`

Un audit plus large a trouvé un second pattern de bug dans ~95 contrôles de permission codés en dur
(`if ($user->getNiveau() > X)`) répartis sur 9 contrôleurs — ceux-ci utilisaient le profil
**principal** brut au lieu du mandat actif effectif. C'est un bug différent de celui documenté
ci-dessus (logique manuelle plutôt que rôle Symfony mal calibré), mais avec le même risque : des
droits incohérents avec le mandat actif de l'utilisateur. **Corrigé le 2026-09-22** (remplacement par
`getEffectiveNiveau()` + reconstruction mandat-aware de `AdminAuthController::me()`/`refresh()`),
reste à valider en dev. Le réaudit du pattern de cumul `#[IsGranted]` ci-dessus a aussi été étendu aux
7 autres contrôleurs qui en semblaient à risque — aucune anomalie trouvée. Voir le détail complet dans
[MANDATE_NIVEAU_BRUT_AUDIT.md](../fixes/bugs/MANDATE_NIVEAU_BRUT_AUDIT.md).
