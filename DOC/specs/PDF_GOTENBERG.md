# Spécification - Génération PDF modernisée (Gotenberg)

> **Statut** : 🚧 En cours — spec validée (2026-10-28) ; **phase 0** (colmatage des PDF legacy) et **phase 1** (socle
> api2 + Gotenberg, documents de référence `teams-groups` et `ranking`) implémentées, cf. § 15.
> **Périmètre** : tous les PDF produits par KPI, appelés depuis app4 (admin2), app3 (site public beta)
> et app2 (résultats). Remplace à terme `commun/MyPDF.php`, les `admin/Feuille*.php`, les `Pdf*.php`
> de la racine et les rendus mPDF de api2.
> **Remplace** : la phase 1 de [DOCUMENTS_MIGRATION.md](../developer/in-progress/DOCUMENTS_MIGRATION.md)
> (choix de librairie) et rend caduque, une fois livrée, la rustine
> [LEGACY_PDF_STANDALONE_ACCESS.md](../developer/in-progress/LEGACY_PDF_STANDALONE_ACCESS.md).
> **Pages app4 concernées** : [PAGE_DOCUMENTS.md](PAGE_DOCUMENTS.md), [PAGE_EQUIPES.md](PAGE_EQUIPES.md),
> [PAGE_MATCHS.md](PAGE_MATCHS.md), [PAGE_CLASSEMENT.md](PAGE_CLASSEMENT.md), [PAGE_PRESENCE.md](PAGE_PRESENCE.md),
> [PAGE_SCRUTINEERING.md](PAGE_SCRUTINEERING.md), [PAGE_STATISTIQUES.md](PAGE_STATISTIQUES.md),
> [PAGE_POOL_ARBITRES.md](PAGE_POOL_ARBITRES.md). Droits : [DROITS_PAR_PROFIL.md](DROITS_PAR_PROFIL.md).

---

## 1. Constat (état au 2026-10-26)

### 1.1 Ce qui génère des PDF aujourd'hui

| Famille | Fichiers | Moteur | Auth | Contexte lu dans |
|---|---|---|---|---|
| PDF « admin » | 25 × `sources/admin/Feuille*.php` | mPDF via `MyPDF` (API façon FPDF : `Cell`, `SetXY`…) | **aucune** (cf. 1.2) | `$_SESSION` + paramètres GET ajoutés au cas par cas |
| PDF « publics » | 20 × `sources/Pdf*.php` | idem | aucune (normal) | `$_SESSION` + GET |
| Exports api2 | `AdminStatsController::exportPdf`, `AdminRankingsController` (justification départage) | mPDF + HTML | JWT | paramètres de requête |

Points saillants :

- **FPDF n'est déjà plus là** : il a été remplacé par mPDF derrière le wrapper `MyPDF`
  ([archive/pdf-exports](../developer/archive/pdf-exports/)). Mais le code reste écrit **en coordonnées FPDF**
  (cellules positionnées au millimètre) : aucun design possible, chaque variante est un copier-coller
  (`PdfListeMatchs4TerrainsEn`, `…En2`, `…En3`, `…En4`, `5TerrainsEn`, `5TerrainsEnTeams` ; 9 variantes de feuille de présence).
  Pour la grille terrains, il n'existe en réalité que **2 vues** (Équipes, Phases) : les autres variantes
  (terrains 1 à 4, 1 à 5, 5 à 8) ne compensaient que l'incapacité de FPDF/mPDF à s'adapter au nombre de colonnes.
- **Dépendance aux sessions** : les pages lisent `$_SESSION['codeCompet']`, `listMatch`, la saison active…
  App4 n'a pas de session PHP ; chaque page a reçu des alias GET (`Compet`/`compet`/`codeCompet`, `S`/`season`,
  `equipe`/`team`) au fil de l'eau. Ce n'est ni homogène ni testable.
- **Liens appelants** : app4 (`documents`, `teams`, `games`, `rankings`, `presence/team`, `scrutineering/team`,
  `referees-pool`), app3 (`app/utils/page-links.ts` : `PdfCltChpt`, `PdfCltNiveauPhase`, `PdfCltMulti`,
  `PdfMatchMulti`, `PdfListeMatchs[EN]`), app2 (`pages/game/[id].vue` → `PdfMatchMulti`), plus l'admin legacy
  (Smarty), `live/`, `js/kptv.js`, WordPress et… des **QR codes déjà imprimés**.

### 1.2 ⚠️ Faille existante : les PDF admin sont publics

Les 25 `admin/Feuille*.php` qui produisent un PDF héritent de `MyPage` et **pas** de `MyPageSecure` :
**aucun contrôle d'authentification ni de profil**. (Seuls `FeuilleMarque2/2stats/3`, qui ne sont pas des PDF,
sont protégés.) C'est la conséquence directe de l'ouverture à app4, qui n'a pas de session.

Conséquence : n'importe qui connaissant l'URL `…/admin/FeuillePresence.php?S=2026&Compet=N1H` obtient la liste
des joueurs avec **date de naissance, n° de licence, état des certificats médicaux, photos** (FeuillePresence*,
FeuilleControle, FeuillePoolArbitres, FeuilleInstances…). C'est un sujet **RGPD**, indépendant de Gotenberg,
qui justifie de traiter en priorité les documents à données personnelles (§10, phase 0).

---

## 2. Objectifs et non-objectifs

### Objectifs

1. **Un seul producteur de PDF** : api2. Plus aucun PDF généré par le PHP legacy après la migration finale.
2. **Design moderne et fidèle aux chartes existantes** : HTML/CSS (flex, grid, polices web, SVG) ; les PDF admin
   respectent la charte d'app4, les PDF publics celle d'app3 (kpi-layer), à partir des mêmes jetons (§8) ;
   lisibles à l'impression noir & blanc.
3. **Sans session** : tout le contexte est dans la requête (paramètres explicites, validés). Une même URL
   (+ même identité) produit toujours le même document.
4. **Sécurité** : PDF admin protégés par **profil + mandat actif** (rôle *et* périmètre : saisons, compétitions,
   événements, journées, clubs). PDF publics accessibles sans authentification mais **limités aux données publiées**.
5. **Rationaliser le catalogue** : une variante = des options, pas un fichier.
6. **Décommissionner** `MyPDF`, `mpdf/mpdf` (sources/ et api2), `admin/Feuille*.php` (PDF), `Pdf*.php`.

### Non-objectifs

- Pas de refonte du **contenu réglementaire** des documents officiels (feuille de marque, feuille de présence) :
  on modernise la forme, les rubriques restent celles exigées par le règlement.
- Pas de PDF générés côté navigateur (pas de jsPDF / impression navigateur) : le rendu doit être identique
  pour tous et archivable.
- Pas d'export tableur ici (OpenSpout reste en place).
- Les pages HTML `FeuilleMarque2/3` (feuilles de marque électroniques) ne sont pas des PDF : hors périmètre.

---

## 3. Pourquoi Gotenberg

| Option | Verdict |
|---|---|
| **mPDF / Dompdf** (HTML → PDF en PHP) | CSS 2.1 partiel : pas de flex/grid, pas de variables CSS fiables. On garde la dette de mise en page. ❌ |
| **Chromium headless dans le conteneur api2** (Browsershot, chrome-php) | Alourdit l'image FrankenPHP (~+500 Mo), Chromium dans le même processus que le worker HTTP : une fuite mémoire ou un crash touche l'API. ❌ |
| **WeasyPrint** | Excellent support CSS Paged Media, mais pas d'API HTTP officielle, service Python à maintenir. 〰️ |
| **Gotenberg 8** (conteneur Docker, API HTTP, Chromium + LibreOffice) | Rendu Chromium moderne, **isolé** dans son propre conteneur, sans état, API simple (`POST /forms/chromium/convert/html`), en-têtes/pieds de page avec numéros de page. Appelé par un petit client Symfony HttpClient (`App\Pdf\GotenbergClient`), sans bundle tiers. ✅ |

**Conclusion : Gotenberg est pertinent.** Il sépare proprement « préparer les données » (api2, testable en PHPUnit)
de « rendre » (Chromium, isolé), et permet des gabarits HTML/CSS que n'importe quel développeur front sait maintenir.

Limites connues à intégrer dans les gabarits : pas de CSS Paged Media complet (pas de `@page :left` avancé,
pas de *running elements*) ; en-tête/pied passés en documents HTML séparés (`header.html`/`footer.html`,
classes `pageNumber`/`totalPages`) ; `thead` répété par Chromium sur chaque page (suffisant pour nos tableaux).

---

## 4. Topologie : une instance partagée préprod + prod ?

Contexte : préprod et prod tournent **sur le même VPS**, en deux stacks Compose distinctes
(`APPLICATION_NAME` différent, réseaux `network_${APPLICATION_NAME}` séparés).

**Mesures du VPS (2026-10-27)** : x86_64, **2 cœurs**, 7,8 Gio de RAM dont 3,6 utilisés et **4,2 disponibles**,
disque 56 Go libres sur 99 ; **21 conteneurs actifs** : 8 par environnement (php, api2, event-cache-worker, app3,
nginx_app2, nginx_app4, db, dbwp) et 5 partagés (traefik, matomo + sa base, phpMyAdmin, broker websocket).
Charge au repos ~0,1, mais une moyenne sur 15 min de 1,83 relevée peu après un déploiement préprod : les builds
saturent déjà presque les 2 cœurs.

**Conclusion** : la RAM n'est pas la contrainte (deux instances plafonnées tiennent dans 1,5 Gio, soit ~35 % de la
marge disponible). **Le CPU l'est** : un rendu Chromium occupe un cœur pendant quelques secondes, et le VPS n'en a
que deux, partagés avec tout le reste. D'où des plafonds CPU stricts, une concurrence de rendu à 1 par instance et
une instance préprod bridée, pour qu'elle ne puisse jamais prendre plus d'un demi-cœur à la prod.

### Options

| | A. Une instance **par environnement** (service `gotenberg` dans chaque compose) | B. Une instance **partagée** sur le VPS (stack infra à part, réseau `gotenberg_network`) | C. Une instance sur un **autre serveur** |
|---|---|---|---|
| RAM | ~2 × 150-250 Mo au repos (Chromium démarré à la demande), pics ~300-600 Mo par rendu concurrent | 1 × | 0 sur le VPS |
| Isolation de charge | ✅ un test de charge ou un gros lot de feuilles de marque en préprod ne ralentit pas la prod | ❌ la préprod consomme la file de la prod | ✅ |
| Montée de version | ✅ testée en préprod avant la prod | ❌ toute montée touche les deux à la fois : la préprod ne sert plus de filet | 〰️ |
| Cycle de vie | ✅ suit le déploiement de l'environnement (rollback compris) | ❌ couplage : redémarrer pour la préprod coupe la prod | ❌ |
| Exposition réseau | ✅ réseau interne de la stack, jamais routé par Traefik | 〰️ réseau Docker partagé entre deux stacks (pattern `pma_network` existant) | ❌ API exposée sur Internet : TLS + auth + filtrage IP, latence, panne réseau en plus |
| Exploitation | même pattern que `db`, `api2`… | une stack de plus à maintenir hors `kpi` (vps-manager) | un serveur de plus |

### Recommandation : **A, une instance par environnement**

Le gain mémoire de B (~200 Mo au repos) ne compense pas la perte des deux garanties qui font l'intérêt de la préprod :
**isolation de charge** et **validation des montées de version**. Gotenberg étant sans état, le dupliquer ne coûte
qu'un peu de RAM. C n'apporte que de la surface d'attaque.

Les mesures ci-dessus confirment A : avec 4,2 Gio disponibles, la mutualisation (B) ne résoudrait aucun problème
réel, alors que le seul goulot (le CPU) est mieux maîtrisé par des plafonds **par environnement**. Mutualiser ferait
au contraire partager la même file entre préprod et prod. À réévaluer seulement si un troisième environnement apparaît
ou si la RAM disponible passe sous ~2 Gio.

| Environnement | `mem_limit` | `cpus` | Concurrence Chromium | File max |
|---|---|---|---|---|
| prod | 1 Gio | 1.0 | 1 | 10 |
| préprod | 512 Mio | 0.5 | 1 | 5 |
| dev | libre | libre | 1 | 10 |

À surveiller après mise en service (`docker stats`, `health-check.sh`) : si la prod sature régulièrement sa file,
on passe d'abord la concurrence à 2 avant de toucher aux `cpus`.

En dev, le service est ajouté à `compose.dev.yaml` de la même manière.

### Configuration de référence (à confirmer sur la version épinglée)

Configuration retenue (`docker/compose.*.yaml`, Gotenberg **8.39.0**, options vérifiées sur cette version) :

```yaml
gotenberg:
    image: gotenberg/gotenberg:8.39.0           # version exacte épinglée, montée via PR
    container_name: ${APPLICATION_NAME}_gotenberg
    command:
        - gotenberg
        - --api-timeout=60s
        - --api-enable-basic-auth               # GOTENBERG_PASSWORD obligatoire (refus explicite de compose sinon)
        - --chromium-max-concurrency=1          # 2 cœurs seulement sur le VPS
        - --chromium-max-queue-size=10          # 5 en préprod ; au-delà : refus immédiat (503 côté api2)
        - --chromium-deny-list=^(?!file:///tmp/).*   # seuls les fichiers joints à la requête sont lisibles
        - --chromium-deny-private-ips           # et aucune adresse réseau, privée…
        - --chromium-deny-public-ips            # …ni publique (anti-SSRF, PDF-INFRA-02)
        - --libreoffice-disable-routes
        - --webhook-disable
    environment: [GOTENBERG_API_BASIC_AUTH_USERNAME, GOTENBERG_API_BASIC_AUTH_PASSWORD]
    mem_limit: 1g                               # 512m en préprod, libre en dev
    cpus: 1.0                                   # 0.5 en préprod
    networks: [network_kpi]                     # jamais traefiknetwork, aucun port publié
    healthcheck: curl -fsS http://localhost:3000/health   # route non soumise à l'authentification
```

Chromium démarre de lui-même à la première conversion (comportement par défaut de cette version). Vérifié sur
8.39.0 : une page qui référence `http://169.254.169.254/…`, `http://kpi_db:3306/`, `https://example.com/…` ou
`file:///etc/passwd` est convertie **sans** qu'aucune de ces ressources ne soit chargée (refus journalisés).

Côté api2 : `GOTENBERG_URL=http://${APPLICATION_NAME}_gotenberg:3000`, `GOTENBERG_USER`, `GOTENBERG_PASSWORD` et
`PDF_LINK_SECRET`, injectés par compose depuis `docker/.env` (cf. `docker/.env.dist`).

---

## 5. Architecture cible

```
app4 ──(fetch + Bearer JWT)──▶ api2 /admin/pdf/{doc}  ─┐
app3 / app2 / QR ──(lien GET)─▶ api2 /pdf/{doc}       ─┤
                                                       ▼
                    Contrôleur PdfDocument* : valide les paramètres, contrôle les droits
                                                       ▼
                    Provider de données (un par document, requêtes DBAL) → DTO
                                                       ▼
                    Gabarit Twig (templates/pdf/…) + CSS commun + polices et logos joints
                                                       ▼
                    GotenbergClient (Symfony HttpClient, multipart) ──HTTP interne──▶ gotenberg:3000
                                                       ▼
                    Réponse application/pdf (inline), cache HTTP pour le public
```

Principes :

- **Contrôleur mince, provider testable** : la logique de données (classements publiés vs calculés, tris,
  filtres de présence) vit dans des services couverts par PHPUnit *sans* Gotenberg.
- **Un gabarit Twig par document**, des *partials* communs (en-tête compétition + logos, pied de page avec date
  de génération / pagination, tableau de classement, ligne de match, bloc QR code).
- **Aucune ressource distante** : polices (WOFF2), logos (lus via `legacy_document_root`, cf. CLAUDE.md « Media »),
  QR codes (SVG générés en PHP) sont **joints à la requête multipart** ou inlinés. Chromium n'a aucun accès réseau.
- **i18n** : paramètre `lang` (`fr` | `en`, défaut `fr`) → traductions Symfony. Fin des fichiers `…EN.php`.
- **Fuseau** : paramètre `tz` (IANA) pour les horaires de match, comme aujourd'hui.

---

## 6. Contrat d'API

### 6.1 Routes

| Route | Auth | Données |
|---|---|---|
| `GET /api2/pdf/{doc}` | aucune | publiées uniquement (`*_publi`, compétitions publiques, matchs publiés) |
| `GET /api2/admin/pdf/{doc}` | JWT `Authorization: Bearer` (déjà couvert par `access_control ^/admin`) + rôle + périmètre du mandat | calculées / provisoires / données personnelles selon le document |

`{doc}` est un identifiant stable en kebab-case (catalogue §7). Paramètres communs :

| Paramètre | Type | Défaut | Note |
|---|---|---|---|
| `season` | `YYYY` | **obligatoire** | plus de « saison active » implicite |
| `competition` | code | selon doc | |
| `event` | id | selon doc | alternative à `competition` |
| `team` | id | — | restreint à une équipe |
| `games` | liste d'ids séparés par `,` | — | plafonnée (cf. §9) |
| `lang` | `fr`/`en` | `fr` | |
| `tz` | IANA | `Europe/Paris` | |
| options du doc | voir §7 | | ex. `photos=1`, `visa=1`, `view=teams` |

Paramètre inconnu, mal formé ou incohérent → **400** avec message explicite (pas de PDF vide, pas de `die()`).
Aucune donnée vide → **404** (pas un PDF blanc).

### 6.2 Réponse

- `200 application/pdf`, `Content-Disposition: inline; filename="<doc>_<competition>_<season>[_<lang>].pdf"`.
- Public : `Cache-Control: public, max-age=300`, `ETag` calculé sur les données (pas sur le PDF), `304` possible.
- Admin : `Cache-Control: private, no-store`.
- Gotenberg indisponible / file pleine → **503** avec `Retry-After` ; timeout → **504**. Jamais de stack trace.

### 6.3 Ouverture depuis app4 (admin, sans session)

Un lien `<a target="_blank">` ne peut pas porter l'en-tête `Authorization`. app4 ouvre donc les PDF admin par un
**lien signé court** (composable `usePdf()`) :

- `POST /api2/admin/pdf/{doc}/link` (JWT) vérifie le rôle et le périmètre, puis renvoie
  `GET /api2/pdf-signed/{doc}?…&user=…&mandate=…&expires=…&signature=…` : HMAC-SHA256 du chemin et de **tous** les
  paramètres (`App\Pdf\PdfLinkSigner`, secret `PDF_LINK_SECRET`), **TTL 5 min**, lié au code utilisateur et au mandat
  actif. Les droits sont **re-vérifiés** au téléchargement (révocation ou changement de mandat pris en compte). Pas de
  JWT dans l'URL. L'URL est bâtie sur `DEFAULT_URI` (préfixe `/api2` compris, que Traefik retire de la requête).
- L'onglet est ouvert **pendant** le clic, avant l'appel à l'API, puis redirigé : aucun bloqueur de pop-up (Safari iOS
  compris) ne l'arrête, et le visualiseur PDF reçoit une vraie URL (impression, partage à un autre poste).
- Le même schéma de signature protège les pages legacy en phase 0 (`POST /api2/admin/pdf/legacy-link`, vérifié par
  `sources/commun/MySignedLink.php`) ; un vecteur de test commun fige ce schéma des deux côtés.

`GET /api2/admin/pdf/{doc}` (JWT, réponse binaire) reste disponible pour un téléchargement par `fetch` + blob,
comme l'export des statistiques.

### 6.4 Contrôle d'accès admin

Pour chaque document, la spec définit (§7) :

- un **rôle minimum** (`#[IsGranted]` mandat-aware via `MandateAwareRoleHierarchyVoter`) ;
- un **contrôle de périmètre** factorisé dans un service unique `PdfAccessChecker` (ou équivalent réutilisant la logique
  existante de `User::getAllowedSeasons/Competitions/Events/Journees/Clubs`) : saison, compétition, événement, journées,
  et pour les profils limités à des clubs, **équipes de leurs clubs uniquement** ;
- cas particulier **profil 10 (`ROLE_SCRUTINEER`)** : feuilles de présence et de contrôle de **toutes** les équipes,
  rien d'autre (cf. CLAUDE.md, PAGE_SCRUTINEERING).

Refus → **403** (rôle insuffisant ou hors périmètre), sans révéler l'existence de la ressource hors périmètre.
Chaque génération admin d'un document à données personnelles est journalisée (`AdminLoggableTrait`, sans les données).

---

## 7. Catalogue cible et droits d'accès

« Public » = route `/pdf` (sans authentification, données publiées) ; « Admin » = route `/admin/pdf`
(JWT + rôle minimum + périmètre du mandat, §6.4). Correspondance profils ↔ rôles (`User::getRoles()` et
`security.yaml`) :

| Profil | 1 | 2 | 3 | 4 | 5 | 6 | 7 | 8 | 9 | 10 |
|---|---|---|---|---|---|---|---|---|---|---|
| Rôle | SUPER_ADMIN | ADMIN | DIVISION | COMPETITION | DELEGATE | ORGANIZER | TEAM | VIEWER | SCORER | SCRUTINEER |

Rôles hérités utilisés ci-dessous : `ROLE_ADMIN` = 1–2 · `ROLE_ORGANIZER` = 1–6 · `ROLE_VIEWER` = 1–8 ·
`ROLE_SCORER` = 1–9 · `ROLE_PRESENCE_VIEWER` = 1–8 + 10 · `ROLE_SCRUTINEER` = 1, 2, 10.
Les profils 1–2 ne sont jamais limités par le périmètre ([DROITS_PAR_PROFIL.md](DROITS_PAR_PROFIL.md), bypass).

### 7.1 Méthode

Le rôle minimum de chaque document est déduit **des pages app4 qui l'appellent aujourd'hui** (conditions `v-if`
des liens, entrées de menu de `components/admin/Header.vue`) et confronté à la matrice
[DROITS_PAR_PROFIL.md](DROITS_PAR_PROFIL.md). Quand les pages se contredisent, la proposition retient la règle de
la matrice et le désaccord est listé en §7.6 pour arbitrage. Une fois arbitrée, cette matrice devient la seule
référence : **le contrôle se fait dans api2**, l'affichage des liens dans app4 n'en est que le reflet.

### 7.2 Équipes / présence (données personnelles → admin uniquement)

| `doc` | Remplace | Appelé aujourd'hui depuis (profils qui voient le lien) | Rôle proposé |
|---|---|---|---|
| `teams-groups` | FeuilleGroups | Documents (1–6, 8) ; Équipes, menu PDF global (1–8) | `ROLE_VIEWER` + périmètre |
| `presence` (base : FR/EN, `view=photos`) | FeuillePresence, …EN, …Photo, …PhotoRef (POOL), …Photo2 | Documents (1–6, 8) ; Équipes, menu global (1–8) et menu par équipe (1–8, 10) ; Présence équipe (tous ceux qui l'ouvrent) | `ROLE_PRESENCE_VIEWER` + périmètre ; profil 7 : équipes de ses clubs ; profil 10 : toutes les équipes de ses compétitions |
| `presence` + `view=category` (`category=<catégorie>` pour en garder une) | FeuillePresenceCat | Équipes, menu global (1–8) ; Documents, section Contrôle (1–6) | `ROLE_VIEWER` + périmètre (cf. I3) |
| `presence` + `view=category&category=U21` | FeuillePresenceU21 | Documents, section Contrôle (1–6) | `ROLE_ORGANIZER` + périmètre |
| `presence` + `view=birthyear` | FeuillePresenceNaissance | Documents, section Contrôle (1–2) | `ROLE_ADMIN` |
| `presence` + `visa=1` | FeuillePresenceVisa | Équipes (1–2) ; Documents, section Équipes (1–6, 8) ; Présence équipe (tous) | `ROLE_ORGANIZER` + périmètre (I2) |
| `scrutineering-control` | FeuilleControle, …EN | Équipes, menu global (1–2) et par équipe (1, 2, 10) ; Scrutineering (1, 2, 10) ; Présence équipe (tous) | `ROLE_SCRUTINEER` (+ périmètre pour le profil 10) (cf. I1) |
| `referees-pool` | FeuillePoolArbitres | Pool arbitres (menu 1–2, API `ROLE_ADMIN`) | `ROLE_ADMIN` |
| `instances` | FeuilleInstances (feuille jury) | Journées, fenêtre « Officiels » (1–8) | `ROLE_VIEWER` + périmètre (journée) |

Règle : chaque **option** qui ajoute des données (date de naissance, visa, catégorie U21) a son propre rôle ; une
option demandée sans le rôle correspondant → 403 (pas de document dégradé silencieusement).

Le pool d'arbitres est permanent : `referees-pool` et `presence?competition=POOL` lisent la saison technique `1000`,
quel que soit `season`. `instances` exige `competition` et `gameday` (la journée doit appartenir à la compétition et
à la saison demandées, sinon 404) ; une journée hors du filtre de journées du mandat → 403. Le numéro de licence des
officiels n'est jamais imprimé.

### 7.3 Matchs

| `doc` | Remplace | Public | Appelé aujourd'hui depuis (admin) | Rôle admin proposé |
|---|---|---|---|---|
| `games-list` | FeuilleListeMatchs[EN], PdfListeMatchs[EN] | ✅ matchs publiés | Documents, section Matchs (1–6, 8) et Événement (1–2) ; Matchs, menu Documents (1–9) ; Classements, mode calculé (1–6) | `ROLE_SCORER` + périmètre, **avec la règle de visibilité de la page Matchs** : profil 7 → matchs et journées publiés uniquement, compétitions ATT masquées |
| `games-grid` | PdfListeMatchs4Terrains, …4TerrainsEn, …En2, …En3, …En4, …5TerrainsEn, …5TerrainsEnTeams | ✅ | Matchs, menu Documents, liens **publics** (1–9) | **pas de version admin** : app4 ouvre la version publique |
| `scoresheet` | FeuilleMatchMulti | ❌ (§7.3.2) | Matchs, ligne de match et action groupée (1–6) ; Documents (1–6, 8) ; Matchs, menu Documents (1–9) | `ROLE_SCORER` + périmètre (journées), visibilité de la page Matchs (I4) |

#### 7.3.1 Grille terrains : 2 vues, colonnes dynamiques

Les 7 fichiers legacy se réduisent à **un document, deux vues** :

- `view=teams` : chaque case montre les deux équipes du match (ex-`5TerrainsEnTeams`) ;
- `view=phases` : chaque case montre la phase / poule / libellé du match (ex-`4Terrains…`, `5TerrainsEn`).

Lignes = créneaux (date, heure) ; colonnes = terrains. **Les colonnes sont calculées à partir des matchs réellement
sélectionnés** : l'ensemble distinct des valeurs `Terrain`, triées dans l'ordre naturel (2 avant 10). Un événement
qui n'utilise que les terrains 5 à 8 donne 4 colonnes « 5, 6, 7, 8 », sans colonnes vides ; une journée sur 3 terrains
donne 3 colonnes. Plus de paramètre `pitches` ni de variante par plage de terrains.

Mise en page : A4 paysage ; au-delà de **6 terrains** par page (largeur lisible minimale d'une case), les terrains sont
répartis en groupes équilibrés (8 → 4 + 4, 9 → 5 + 4) imprimés en sections successives, chaque section répétant
l'en-tête des créneaux. Un jour = une section ; une date ou un lieu différent fait sauter une page.

#### 7.3.2 Feuille de marque publique : non développée

**Décision (2026-10-28)** : pas de feuille de marque PDF publique pour l'instant. Le public dispose de la vue match
d'app2 (`/game/{id}`), plus lisible ; le PDF formel reste un document d'officiels (admin uniquement).

- app2 `pages/game/[id].vue` : le lien vers `PdfMatchMulti.php` est retiré.
- app3 `app/utils/page-links.ts` : la cible « feuille de match » pointe vers la fiche match app3 si elle existe,
  sinon vers la vue match d'app2.
- `PdfMatchMulti.php?listMatch=<id>` redirige (301) vers la vue match d'app2 de ce match ; avec plusieurs ids, vers
  la vue du premier match. Une feuille publique pourra être ajoutée plus tard comme option `scoresheet` sur `/pdf`
  sans changer le reste du contrat.

#### 7.3.3 Documents officiels

La **feuille de marque** est un document officiel rempli à la main : format A4, zones d'écriture et rubriques
inchangées, validées par la commission arbitrage avant mise en service (critère PDF-DOC-03).

### 7.4 Classements

| `doc` | Remplace (admin / public) | Public | Appelé aujourd'hui depuis (admin) | Rôle admin proposé |
|---|---|---|---|---|
| `ranking` | FeuilleCltChpt·Niveau·Multi / PdfCltChpt·Niveau·Multi | classement **publié** | Classements, mode calculé (1–6) ; Documents (1–6, 8) | `ROLE_ORGANIZER` + périmètre (classement calculé, cf. I5) |
| `ranking-team-detail` | …CltChptDetail, …CltNiveauDetail | publié | idem | `ROLE_ORGANIZER` + périmètre |
| `ranking-gameday-detail` | …CltNiveauJournee | publié | Documents (1–6, 8) | `ROLE_ORGANIZER` + périmètre |
| `ranking-phases` | …CltNiveauPhase | publié | Classements (1–6) ; Documents (1–6, 8) | `ROLE_ORGANIZER` + périmètre |
| `ranking-levels` | …CltNiveauNiveau | publié | **aucun lien app4 ni app3** (admin legacy seulement) | à ne pas migrer, sauf besoin exprimé (cf. I6) |
| `ranking-tiebreak` | justification départage (api2, mPDF) | — | Classements, si égalités (API `ROLE_VIEWER`) | `ROLE_ORGANIZER` + périmètre (repose sur le calcul, cf. I5) |

Dans app4, les entrées « Classement publié » de la page Classements (profils 1–8) ouvrent la **version publique**.
Quand le classement porte des médailles, elles **remplacent** les mentions « Qualifié » (validé sur la maquette de référence,
2026-10-28). Règle invariante : **un PDF public ne lit jamais les colonnes calculées** ; un PDF admin calculé porte la mention
« Provisoire - non publié » en filigrane.

### 7.5 Statistiques, contrôle, QR codes

| `doc` | Remplace | Public | Appelé aujourd'hui depuis (admin) | Rôle admin proposé |
|---|---|---|---|---|
| `stats` | export PDF api2 (mPDF), FeuilleStats[EN] (admin legacy seulement) | — | Statistiques (1–8) | `ROLE_VIEWER` + périmètre ; types restreints (`CJouees3`, `CJouees3b`, `LicenciesNationaux`, `CoherenceMatchs`) : `ROLE_ORGANIZER` (règle actuelle d'`AdminStatsController`) |
| `cards` | FeuilleCards | — | Documents, section Contrôle (1–6) | `ROLE_ORGANIZER` + périmètre |
| `qrcodes` | PdfQrCodes | ✅ | Documents, section Événement (1–2) | pas de version admin (données publiques) |
| `qrcode-app` | PdfQrCodeApp | ✅ | Documents, section Événement (1–2) | pas de version admin |

Non migrés (aucun appel depuis app4, app3 ou app2, doublons d'une option) : FeuillePresencePhoto2,
FeuilleControleEN (→ `lang=en`), FeuilleStats[EN] (→ `stats`), FeuilleCltNiveauNiveau / PdfCltNiveauNiveau (I6).

### 7.6 Incohérences relevées dans app4 et arbitrages (2026-10-28)

Toutes les propositions sont validées, avec deux ajustements : **I2 → `ROLE_ORGANIZER`**, **I4 → `ROLE_SCORER`**.

| # | Constat | Décision |
|---|---|---|
| I1 | **Feuille de contrôle** : réservée aux profils 1, 2, 10 sur Équipes et Scrutineering, mais proposée **à tout le monde** sur Présence équipe (`pages/presence/team/[teamId].vue`, `pdfLinks` sans condition). | `ROLE_SCRUTINEER`, conforme à la matrice (Scrutineering : 1, 2, 10) ; masquer le lien sur Présence équipe pour les autres profils. |
| I2 | **Feuille visa** : profils 1–2 sur Équipes, 1–6 et 8 sur Documents (section Équipes), tout le monde sur Présence équipe. ✅ `ROLE_ORGANIZER` (1–6) ; lien masqué ailleurs. |
| I3 | **Présence par catégorie** : 1–8 sur Équipes, mais rangée dans la section Contrôle (1–6) de Documents. | `ROLE_VIEWER` (pas plus de données que la feuille de base) ; la déplacer dans la section Équipes de Documents. |
| I4 | **Feuille de marque** : 1–6 sur la page Matchs (ligne de match, action groupée) et dans la matrice (« Feuille de marque standard » 1–6), mais 1–6 et 8 sur Documents et **1–9** via le menu Documents de la page Matchs. ✅ `ROLE_SCORER` (1–9) : la table de marque imprime les feuilles. Le PDF suit la règle de visibilité de la page Matchs (profil 7 : matchs publiés). Matrice mise à jour. |
| I5 | **Classement calculé** : 1–6 sur Classements et dans la matrice, mais le profil 8 l'obtient via Documents (qui pointe vers les `Feuille*` calculées) ; la justification de départage est en `ROLE_VIEWER` (1–8) alors qu'elle repose sur le calcul. | `ROLE_ORGANIZER` pour tout le calculé ; Documents propose au profil 8 les classements publiés (route publique). |
| I6 | `ranking-levels` (CltNiveauNiveau) n'est appelé que par l'admin legacy (GestionClassement, GestionDoc). | Ne pas le migrer ; le supprimer en phase 6 sauf demande d'un utilisateur. |
| I7 | **Profil 10** : la page Documents lui est masquée dans le menu mais pas protégée (middleware `auth` seul). | Sans effet une fois les droits contrôlés par api2 ; ajouter une garde de route par cohérence (hors périmètre PDF). |
| I8 | Profil 9 : la matrice lui donne la consultation des feuilles de présence équipe, mais `ROLE_SCORER` n'hérite pas de `ROLE_PRESENCE_VIEWER` et il n'a pas accès à la page Équipes. | Aucun PDF de présence pour le profil 9 (comportement actuel) ; corriger la matrice ou le rôle, hors périmètre PDF. |

Ces décisions sont reportées dans [DROITS_PAR_PROFIL.md](DROITS_PAR_PROFIL.md) (section « Documents PDF »).

---

## 8. Design : respect des chartes app4 et app3

Un PDF doit être reconnaissable comme sortant de l'application qui l'ouvre : un PDF admin ressemble à app4,
un PDF public ressemble au site public app3. Les deux chartes partagent la même base FFCK. Les PDF en reprennent
**les jetons, pas des copies approximatives**.

### 8.1 Sources de vérité

| Charte | Fichier de référence | Spec |
|---|---|---|
| **Admin (app4)** | `sources/app4/assets/css/admin.css` (variables `--color-*` et `@theme`) | [COMMON_ADMIN_SPECS.md](COMMON_ADMIN_SPECS.md), [DARK_MODE.md](DARK_MODE.md) |
| **Public (app3)** | `sources/kpi-layer/app/assets/css/kpi-theme.css` (`@theme static`, palettes générées par `scripts/generate-palette.py`) | [SITE_LAYOUT.md §3](public/SITE_LAYOUT.md) |

Règle (même règle que SITE_LAYOUT §3) : **aucune couleur hexadécimale dans les gabarits PDF**, uniquement des
variables CSS. Ces variables sont **extraites** des deux fichiers ci-dessus par un script
(`make pdf_tokens_sync`) vers `sources/api2/templates/pdf/tokens/{admin,public}.css`, fichiers générés et commités.
Un test unitaire api2 relit les deux sources et échoue si les fichiers générés ont dérivé (critère PDF-DESIGN-05).
Quand une charte change dans app3 ou app4, la PR relance le script ; les PDF suivent sans retouche des gabarits.

### 8.2 Socle commun aux deux chartes

| Élément | Valeur (jeton) | Usage PDF |
|---|---|---|
| Texte courant | **Raleway** (auto-hébergée, `kpi-layer/public/fonts/raleway/`) | corps, tableaux |
| Titres | **Agency FB** (`agencyfb.ttf`, licence FFCK : incorporation dans les PDF publics admise, cf. R14) | titre du document, titres de section, noms de compétition |
| Chiffres | `font-variant-numeric: tabular-nums` | scores, points, classements, horaires |
| Encre | `ink` / `dark` `#1e1e1c` | texte |
| Filets | `line` / `light` `#c6c7c7` | bordures, séparateurs, zébrage très clair (teinte 50) |
| Bleu nuit | `navy` / `header` `#20265b` | bandeau de titre, pied de page |
| Bleu ciel | `sky` / `secondary` `#69b9e6` | filet d'accent, aplats légers (texte noir uniquement, comme le bandeau beta app3) |

Les polices sont **jointes à la requête Gotenberg** (aucun appel réseau, cf. §9), copiées depuis `kpi-layer/public/fonts`
par le même script que les jetons.

### 8.3 Thème admin (route `/admin/pdf`) - charte app4

- Couleur d'action/titres de section : `primary` `#1e4385` ; bandeau : `header` `#20265b`.
- États : `success` `#209452` (qualifié, présent, contrôle OK), `warning` `#e9b410` (à vérifier, provisoire),
  `danger` `#c94a4c` (non conforme, carton rouge, certificat manquant) — **toujours doublés d'un libellé ou d'une
  icône** (lisible en noir & blanc).
- Logo : `logo_kp.png` (KPI) dans le bandeau ; logos compétition/sponsor issus des visuels de la compétition.
- Pied : « Document interne - ne pas diffuser » pour les documents à données personnelles, nom du profil générateur
  et date/heure de génération.
- Filigrane « Provisoire - non publié » (`warning`, opacité faible) sur les classements calculés (§7.4).

### 8.4 Thème public (route `/pdf`) - charte app3

- Couleur primaire `primary`/`kpi-blue-500` `#357b9c` (titres de section, filets) ; `accent`/`kpi-red-500`
  `#c94a4c` réservé aux mises en avant (vainqueur de match, alerte) ; `gold`/`green` pour médailles et qualifiés,
  comme sur le site.
- En-tête : logo **FFCK sur pastille blanche** (règle de la charte : jamais sur fond coloré) + logo CNA Kayak-Polo
  (`kpi-layer/public/img/brand/`), puis logos de la compétition.
- Pied en bandeau `navy` fin, rappelant le pied du site : URL et **QR code vers la page équivalente sur app3**
  (classements → page compétition, listes et grilles de matchs → calendrier / résultats), date de génération.
  **Dès la beta** : l'URL est construite à partir du paramètre api2 existant `PUBLIC_SITE_URL` (aujourd'hui
  `https://beta.${KPI_DOMAIN_NAME}`, domaine principal après la bascule), jamais codée en dur. Chaque document déclare
  le chemin de sa page app3 (`PdfContent::$publicPath`) une fois celle-ci livrée ; sans chemin, pas de QR code.
  Comme ces QR codes seront imprimés, **`beta.*` devra rediriger (301) vers le domaine principal pendant au moins
  1 saison après la bascule** : exigence reportée dans [PUBLIC_SITE_ROLLOUT_CHECKLIST.md](../developer/in-progress/PUBLIC_SITE_ROLLOUT_CHECKLIST.md).
- Mêmes conventions visuelles que les composants app3 équivalents : un tableau de classement PDF reprend
  colonnes, ordre et codes couleur du classement de [PAGE_COMPETITION.md](public/PAGE_COMPETITION.md) ; une liste
  de matchs reprend la présentation du calendrier / des résultats.

### 8.5 Contraintes propres au papier

- **Thème clair uniquement** : le mode sombre des deux applis ne s'applique pas aux PDF (jetons de `:root`, jamais
  ceux de `.dark`).
- **Économie d'encre** : pas de grands aplats ; le bandeau `navy` est limité à une bande fine d'en-tête et de pied.
  Un aplat portant du texte blanc utilise le ton 900 de sa palette (règle app3).
- **Noir & blanc** : aucune information portée par la seule couleur ; contraste AA vérifié sur les jetons utilisés.
- Marges ≥ 10 mm, tableaux zébrés légers (teinte 50), `break-inside: avoid` sur les blocs équipe/match,
  `thead` répété à chaque page.
- **Documents officiels** (feuille de marque, feuille de présence) : la charte habille l'en-tête et le pied, mais la
  grille et les zones d'écriture restent sobres (encre, filets `line`), dimensionnées pour l'écriture manuscrite.
- **Accessibilité** : PDF balisé (Chromium produit des PDF taggés), langue renseignée, `<title>` significatif.

### 8.6 Validation du design

- Un gabarit est un fichier Twig prévisualisable en HTML via une route **dev-only** `?format=html` (désactivée hors
  `dev`) : on le compare côte à côte avec la page app4/app3 équivalente.
- Phase 1 : **deux maquettes de référence** validées avant tout autre document — `teams-groups` en thème admin et
  `ranking` en thème public. Les documents suivants réutilisent leurs *partials* (en-tête, pied, tableau, ligne de
  match) sans redéfinir de style.

---

## 9. Sécurité, performance, exploitation

| Sujet | Exigence |
|---|---|
| Injection HTML | Twig autoescape partout ; aucun `|raw` sur une donnée de la base (noms d'équipes, clubs, commentaires CMS). |
| SSRF | Chromium sans réseau (`deny-list` totale), ressources jointes uniquement ; Gotenberg non routé par Traefik, aucun port publié. |
| Auth Gotenberg | Basic auth activée (défense en profondeur même sur réseau interne). |
| Déni de service public | Rendu coûteux → `symfony/rate-limiter` sur `/pdf/*` (ex. 30 req/min/IP), cache HTTP + ETag, cache disque des PDF publics invalidé par la date de mise à jour des données. |
| Taille | `games` plafonné (ex. 200 matchs) ; au-delà → 400 avec message ; timeout Gotenberg 60 s. |
| Worker FrankenPHP | Le rendu bloque un thread : timeout client HTTP < timeout Gotenberg, nombre de threads FrankenPHP vérifié en charge. |
| Disponibilité | `restart: unless-stopped` + healthcheck ; ajouté au `health-check.sh` du VPS ; 503 propre si indisponible. |
| Versions | Image épinglée (tag exact), montée via PR → préprod → prod. |
| Données personnelles | Admin uniquement, `no-store`, journalisation de la génération, aucun PDF admin mis en cache disque. |

---

## 10. Enjeux et risques

| # | Risque | Prob. | Impact | Mitigation |
|---|---|---|---|---|
| R1 | **Fuite de données personnelles actuelle** (§1.2) persistant pendant toute la migration | **avérée** | **élevé** (RGPD) | Phase 0 immédiate ; migrer d'abord les documents à données personnelles |
| R2 | Liens existants cassés (QR codes imprimés, WordPress, sites de clubs, `live/`, kptv) | élevée | moyen | Redirections 301 des `Pdf*.php` publics vers `/api2/pdf/*` (traduction des paramètres) conservées **1 saison** après la phase 6 ; inventaire des appelants (§1.1) |
| R3 | Rejet des nouveaux documents officiels (feuille de marque, présence) par arbitres / organisateurs | moyenne | élevé | Rubriques inchangées, validation par la commission avant bascule, période de double lien « ancien / nouveau » |
| R4 | **Saturation CPU** du VPS (2 cœurs) par Chromium en pic de compétition ou pendant un déploiement préprod ; la RAM (4,2 Gio disponibles) n'est pas le goulot | moyenne | élevé (touche la prod) | Plafonds `cpus` (prod 1.0, préprod 0.5), concurrence 1, file bornée (503), lots rendus en **un seul** document HTML, cache public, mesure `docker stats` en préprod sur un lot réel |
| R5 | SSRF / injection via des données saisies | faible | élevé | §9 (deny-list réseau, autoescape, réseau interne) |
| R6 | Abus du endpoint public (rendu coûteux) | moyenne | moyen | Rate limit + cache + plafonds |
| R7 | Régression de droits (un profil voit hors de son périmètre, ou perd l'accès) | moyenne | élevé | `PdfAccessChecker` unique, tests d'intégration par profil × document (matrice §11) |
| R8 | Confusion classement publié / calculé | faible | élevé (publication erronée) | Deux providers distincts, filigrane « Provisoire », tests dédiés |
| R9 | Gotenberg indisponible = plus aucun PDF | faible | moyen | Healthcheck, restart, 503 explicite ; legacy encore en place tant que la phase n'est pas basculée |
| R10 | Coût de migration sous-estimé (≈45 fichiers, logique métier enfouie dans du code FPDF) | élevée | moyen | Consolidation du catalogue (≈18 docs au lieu de 45), migration par famille, chaque doc = 1 PR avec tests |
| R11 | Mise en page Chromium différente de l'existant (sauts de page, fusion de cellules) | moyenne | faible | Prévisualisation HTML, tests de non-régression sur le texte extrait |
| R12 | Admin legacy (Smarty) encore utilisé pendant la transition | certaine | faible | Les `Feuille*.php` restent jusqu'au retrait de l'admin legacy, mais **protégés** (phase 0) |
| R13 | Dérive entre la charte des applis et celle des PDF (couleurs recopiées à la main) | élevée sans outillage | moyen | Jetons extraits par script + test de dérive (§8.1, PDF-DESIGN-05) |
| R14 | Licence **Agency FB** : police commerciale | faible | moyen | **Hypothèse retenue** : la licence FFCK couvre l'incorporation dans les PDF publics. Repli `'Arial Narrow', Arial` conservé dans la pile CSS si elle était remise en cause |
| R15 | QR codes imprimés pointant vers `beta.*`, domaine appelé à disparaître | élevée | moyen | URL issue de `PUBLIC_SITE_URL` ; redirection 301 `beta.*` → domaine principal pendant 1 saison après la bascule (§8.4, PDF-DESIGN-08) |

---

## 11. Critères d'acceptation

Chaque critère donne lieu à au moins un test nommé d'après son identifiant.

**Infrastructure**
- **PDF-INFRA-01** : le service `gotenberg` démarre dans dev, préprod et prod, sur le réseau interne de la stack, sans port publié ni router Traefik.
- **PDF-INFRA-02** : une requête Gotenberg contenant une ressource `http(s)://` externe ne déclenche aucun appel réseau (la ressource est absente du rendu).
- **PDF-INFRA-03** : Gotenberg arrêté → l'endpoint répond 503 avec `Retry-After`, sans trace d'erreur.

**Sans session**
- **PDF-SESS-01** : chaque document se génère sans cookie de session PHP, avec les seuls paramètres de §6.1.
- **PDF-SESS-02** : `season` absent → 400 ; paramètre inconnu ou mal formé → 400 ; aucune donnée → 404.

**Accès**
- **PDF-AUTH-01** : `GET /api2/admin/pdf/*` sans JWT → 401.
- **PDF-AUTH-02** : JWT d'un profil sous le rôle minimum du document → 403.
- **PDF-AUTH-03** : profil dont le mandat actif est limité à la compétition A demande la compétition B → 403 ; même profil après bascule vers un mandat incluant B → 200.
- **PDF-AUTH-04** : profil limité à un club demande `presence` sans `team` → seules les équipes de son club figurent ; avec `team` d'un autre club → 403.
- **PDF-AUTH-05** : profil 10 obtient `presence` et `scrutineering-control` de toute équipe de ses compétitions, et 403 sur tout autre document admin.
- **PDF-AUTH-08** : chaque document admin respecte le rôle minimum de §7, vérifié par un test paramétré profil (1 à 10) × document, attendu 200 ou 403.
- **PDF-AUTH-09** : une option soumise à un rôle plus élevé que le document (`view=birthyear`, `visa=1`, `category=U21`) demandée sans ce rôle → 403.
- **PDF-AUTH-10** : `games-list` admin demandé par un profil 7 ne contient que les matchs et journées publiés, hors compétitions ATT.
- **PDF-AUTH-06** : `GET /api2/pdf/*` (public) fonctionne sans authentification et n'expose ni date de naissance, ni licence, ni certificat, ni photo.
- **PDF-AUTH-07** : lien expiré, signature altérée, ou droits retirés entre émission et usage → 403.

**Données**
- **PDF-DATA-01** : `ranking` public n'affiche que le classement publié ; une compétition non publiée → 404.
- **PDF-DATA-02** : `ranking` admin affiche le classement calculé avec la mention « Provisoire ».
- **PDF-DATA-03** : `games-list` public n'inclut que les matchs publiés ; l'admin inclut ceux du périmètre.
- **PDF-DATA-04** : `lang=en` traduit tous les libellés fixes ; les horaires respectent `tz`.
- **PDF-DATA-05** : `games-grid` a exactement une colonne par terrain utilisé par les matchs sélectionnés, dans l'ordre naturel (matchs sur les terrains 5 à 8 → colonnes 5, 6, 7, 8 ; aucune colonne vide).
- **PDF-DATA-06** : `games-grid` avec plus de 6 terrains répartit les colonnes en groupes équilibrés (8 → 4 + 4) et répète l'en-tête des créneaux dans chaque section.
- **PDF-DATA-07** : `view=teams` affiche les équipes de chaque match, `view=phases` sa phase / poule ; toute autre valeur → 400.

**Documents**
- **PDF-DOC-01** : chaque document de §7 a un équivalent fonctionnel de chaque fichier legacy qu'il remplace (mêmes informations, vérifiées sur un jeu de données de référence).
- **PDF-DOC-02** : en-tête/pied conformes au gabarit (logo, titre, saison, date de génération, page X / Y).
- **PDF-DOC-03** : feuille de marque et feuille de présence validées par écrit par la commission concernée avant bascule.
- **PDF-DOC-04** : aucune ressource externe dans le PDF (polices et images embarquées).

**Design**
- **PDF-DESIGN-01** : un PDF servi par `/admin/pdf` utilise les jetons du thème admin (app4) ; servi par `/pdf`, ceux du thème public (app3). Un même `doc` disponible sur les deux routes change de thème sans changer de gabarit.
- **PDF-DESIGN-02** : aucun gabarit ni partial PDF ne contient de couleur hexadécimale, `rgb()` ou `oklch()` littérale (test qui parcourt `templates/pdf/`, hors `tokens/`).
- **PDF-DESIGN-03** : les polices du document sont Raleway (texte) et Agency FB ou son repli validé (titres), embarquées dans le PDF (`pdffonts` les liste toutes comme *embedded*).
- **PDF-DESIGN-04** : le logo FFCK n'apparaît que sur fond blanc ; les PDF publics portent les logos FFCK et CNA, les PDF admin le logo KPI.
- **PDF-DESIGN-05** : modifier une couleur dans `admin.css` ou `kpi-theme.css` sans relancer `make pdf_tokens_sync` fait échouer la suite de tests api2.
- **PDF-DESIGN-06** : les maquettes de référence (`teams-groups` admin, `ranking` public) sont validées côte à côte avec les pages app4/app3 correspondantes avant la migration des autres documents.
- **PDF-DESIGN-07** : imprimé en noir & blanc, chaque état (qualifié, provisoire, non conforme…) reste identifiable par un libellé ou une icône.
- **PDF-DESIGN-08** : un PDF public dont la page app3 équivalente est livrée porte un QR code vers `PUBLIC_SITE_URL` + chemin de cette page ; si elle n'est pas livrée, aucun QR code.

**Robustesse**
- **PDF-PERF-01** : sur le VPS de prod (2 cœurs, `cpus: 1.0`), `scoresheet` de 100 matchs généré en < 30 s et `ranking` en < 3 s ; mesuré d'abord en préprod (`cpus: 0.5`, cibles × 2).
- **PDF-PERF-04** : pendant un rendu préprod au plafond, le temps de rendu d'un `ranking` en prod n'augmente pas de plus de 20 %.
- **PDF-PERF-02** : au-delà de la limite de matchs → 400 explicite ; au-delà du rate limit public → 429.
- **PDF-PERF-03** : deux requêtes publiques identiques successives → la seconde est servie par le cache (304 ou cache disque).

**Front**
- **PDF-FRONT-01** : app4 n'utilise plus aucun lien vers `legacyBaseUrl/admin/Feuille*.php` ni `Pdf*.php` ; les PDF admin passent par `usePdf()`.
- **PDF-FRONT-02** : app3 (`page-links.ts`) pointe sur `/api2/pdf/*` ; plus aucun lien vers une feuille de marque PDF publique dans app2 ni app3 (§7.3.2).
- **PDF-FRONT-04** : dans app4, un lien PDF n'est affiché qu'aux profils autorisés par §7 (même source que le contrôle api2) ; les incohérences I1 à I5 sont résorbées.
- **PDF-FRONT-03** : ouverture d'un PDF admin dans un nouvel onglet fonctionne sur Chrome, Firefox, Safari macOS et Safari iOS (pas de blocage pop-up).

**Décommissionnement**
- **PDF-END-01** : après la migration finale, `grep -rn "MyPDF\|Mpdf" sources --include=*.php` (hors `vendor`) ne renvoie rien, `mpdf/mpdf` est retiré des deux `composer.json`.
- **PDF-END-02** : les anciennes URL publiques `Pdf*.php` redirigent (301) vers l'équivalent `/api2/pdf/*` (et `PdfMatchMulti.php` vers la vue match d'app2, §7.3.2), pendant **1 saison** ; les anciennes URL admin renvoient 410.

---

## 12. Plan de migration

| Phase | Contenu | Sortie |
|---|---|---|
| **0 - Colmatage (immédiat, sans Gotenberg)** | Protéger les `admin/Feuille*.php` qui exposent des données personnelles. Option recommandée : app4 obtient un **lien signé court** depuis api2 (même mécanisme que §6.3-2, vérifié côté legacy par un helper partageant le secret HMAC) ; l'admin legacy continue avec sa session (`MyPageSecure`). À défaut, désactiver ces liens dans app4 jusqu'à leur migration. | Plus aucun document à données personnelles accessible anonymement |
| **1 - Socle** | Service `gotenberg` (3 compose, plafonds §4), lien signé (§6.3-2), client `GotenbergClient`, `PdfAccessChecker`, `make pdf_tokens_sync` + thèmes admin/public, partials communs, `usePdf()` app4, rate limiter, CI avec Gotenberg en service | PDF-INFRA-*, PDF-AUTH-01/02, PDF-DESIGN-*, deux maquettes de référence : `teams-groups` (admin) et `ranking` (public) |
| **2 - Données personnelles** | `presence` (9 variantes), `scrutineering-control`, `referees-pool`, `instances` | R1 clos ; liens app4 basculés |
| **3 - Classements** | famille `ranking*`, puis migration de `ranking-tiebreak` hors mPDF | app3 + app4 basculés pour les classements |
| **4 - Matchs** | `games-list`, `games-grid` (2 vues, colonnes dynamiques, public seulement), `scoresheet` admin (validation commission) ; retrait du lien PDF de la vue match app2, redirection de `PdfMatchMulti.php` | app2/app3/app4 basculés |
| **5 - Stats & QR** | `stats` (remplace aussi l'export mPDF api2), `cards`, `qrcodes`, `qrcode-app` | `mpdf/mpdf` retiré d'api2 |
| **6 - Migration finale** | À la bascule du site public (app3) et au retrait de l'admin legacy : suppression des `Feuille*.php`/`Pdf*.php`/`MyPDF`, redirections 301 (1 saison) / 410, retrait de `mpdf/mpdf` de `sources/composer.json`, archivage de ce document | PDF-END-* |

Chaque document migré : 1 PR = provider + gabarit + tests (critères ci-dessus) + bascule des liens appelants.
Le fichier legacy reste en place (protégé) jusqu'à la phase 6 pour l'admin legacy.

---

## 13. Stratégie de tests (TDD)

- **Unit (api2)** : providers de données (tri, filtres, publié/calculé), validation des paramètres, `PdfAccessChecker`
  (matrice profil × mandat × document) — sans base ni Gotenberg.
- **Intégration (api2, base de fixtures)** : contrôleurs avec un client Gotenberg **simulé** qui capture le HTML envoyé :
  on asserte sur le HTML (présence/absence des données, mentions « Provisoire », traductions) et sur les codes HTTP.
- **Bout en bout (CI, Gotenberg en conteneur de service)** : quelques documents réellement rendus, texte extrait
  (`pdftotext`) comparé à des attendus ; vérification PDF-INFRA-02 (aucune requête sortante).
- **Front** : tests Vitest de `usePdf()` (ouverture avant `await`, gestion 401/403/503).
- Pas de comparaison pixel à pixel en CI (fragile) ; revue visuelle manuelle via la prévisualisation HTML.

---

## 14. Décisions et questions ouvertes

### Décisions (2026-10-27)

| Sujet | Décision |
|---|---|
| Lien signé (§6.3-2) | Livré dès la phase 1 (sert aussi au colmatage de la phase 0). |
| Grille terrains (§7.3.1) | 2 vues seulement (Équipes, Phases) ; colonnes = terrains réellement utilisés. |
| Redirections des anciennes URL publiques | Conservées **1 saison** après la phase 6. |
| Topologie Gotenberg (§4) | Une instance par environnement, confirmée par les mesures du VPS ; contrainte = CPU (2 cœurs), plafonds par environnement. |
| Licence Agency FB (R14) | On considère que la licence FFCK couvre les PDF publics. |
| QR code vers app3 (§8.4) | Oui, dès la beta, via `PUBLIC_SITE_URL`, avec redirection `beta.*` → domaine principal après la bascule. |
| Feuille de marque publique (§7.3.2, 2026-10-28) | Non développée : le public utilise la vue match d'app2 ; `PdfMatchMulti.php` y redirige. |
| Rôles minimum (§7, 2026-10-28) | Validés ; arbitrages I1 à I8 en §7.6 (visa : `ROLE_ORGANIZER`, feuille de marque : `ROLE_SCORER`, classement calculé : profils 1–6). |

### Questions ouvertes

Aucune à ce stade.

---

## 15. État d'avancement

### Phase 0 — colmatage (livrée)

- Les 28 `admin/Feuille*.php` qui produisent un PDF appellent `MySignedLink::requirePdfAccess()` : session de l'admin
  legacy **ou** lien signé par api2, sinon 403. Les pages publiques `Pdf*.php` et les feuilles de marque en ligne
  (`FeuilleMarque*`) ne changent pas.
- api2 : `POST /admin/pdf/legacy-link` (`App\Pdf\LegacyPdfLinks`) applique le rôle du document équivalent (§ 7, I1 à
  I5) et le périmètre (saison, compétition, équipe et club de l'équipe).
- app4 : tout clic sur un lien vers une `admin/Feuille*.php` passe par ce lien signé (`useLegacyPdfLinks()` dans le
  layout `admin`, `usePdf().openLegacy()` pour les ouvertures par script) ; liens visa et contrôle masqués aux profils
  non autorisés (I1, I2), présence par catégorie rangée dans « Équipes » (I3), classement calculé réservé aux profils
  1–6 sur la page Documents et justification de départage en `ROLE_ORGANIZER` (I5).

### Phase 1 — socle (livrée)

- Service `gotenberg` dans les trois compose (§ 4) ; variables `GOTENBERG_PASSWORD` et `PDF_LINK_SECRET` **obligatoires**
  dans `docker/.env` (compose refuse de démarrer sans elles).
- api2 (`src/Pdf/`, `src/Controller/PdfController.php`) : routes `/pdf/{doc}`, `/admin/pdf/{doc}`,
  `/admin/pdf/{doc}/link`, `/pdf-signed/{doc}` ; catalogue des droits ; rendu Twig + Gotenberg ; cache HTTP et ETag
  des PDF publics ; limite de 30 PDF publics par minute et par IP ; aperçu HTML `?format=html` en dev.
- Charte : `make pdf_tokens_sync` (commande `app:pdf:sync-assets`) copie jetons, polices et logos d'app4 et app3 dans
  `sources/api2/assets/pdf/` ; `ChartTokensTest` échoue en cas de dérive.
- Documents de référence : `teams-groups` (admin, app4 Équipes et Documents) et `ranking` (public, app4 Classements et
  Documents, app3 onglet Classement pour CHPT et MULTI).
- Tests : `tests/Unit/Pdf/*`, `tests/Integration/PdfEndpointsTest.php` (Gotenberg remplacé par
  `FakeGotenbergClient`), `app4/tests/unit/pdf.spec.ts`, `app3/tests/unit/page-links.spec.ts`.

### Phase 2 — données personnelles (livrée)

- api2 : `presence` (vues `team`, `photos`, `category`, `birthyear` ; options `visa`, `category`),
  `scrutineering-control`, `referees-pool`, `instances` ; gabarit générique `sections.html.twig`.
- Profil limité à des clubs : seules les équipes de ses clubs, une équipe d'un autre club → 403 (PDF-AUTH-04).
- app4 : les liens legacy des pages déjà migrées (`utils/pdf.ts`, `migratedLegacyPdf`) ouvrent le document api2
  équivalent ; Pool arbitres et Journées (« Officiels ») appellent directement `usePdf().openDocument()`.

### Phase 3 — classements (livrée)

- api2 : `ranking` admin (classement calculé, filigrane « Provisoire - non publié »), `ranking-team-detail`,
  `ranking-gameday-detail`, `ranking-phases` sur les deux routes (public : classement publié d'une compétition publiée,
  journées et matchs publiés ; admin : calculé, tous les matchs). Source unique `App\Pdf\Ranking\RankingRepository`
  (colonnes `*_publi` ou calculées selon `RankingSource`).
- `ranking-tiebreak` : la justification reste calculée par `GET /admin/rankings/justification` (moteur de départage
  inchangé) ; seul son rendu PDF (`format=pdf`) passe de mPDF à Gotenberg, charte admin.
- app3 : le PDF d'une coupe (CP) est `ranking-phases` d'api2. app4 : les liens legacy des classements (admin et
  publics, `publicPdfHref`) ouvrent les documents api2 ; `ranking-levels` (CltNiveauNiveau) n'est pas migré (I6).

### Phase 4 — matchs (livrée)

- `competition` accepte une liste (`competition=N1H,N2H`, comme `Compet` du legacy) ; le périmètre est vérifié pour
  chaque code.
- `games-list` (public : matchs et journées publiés de compétitions publiées ; admin : périmètre, profil 7 comme sa
  page Matchs), portée `competition`, `event`, `gameday` ou `games`, filtres de la page Matchs en options (`date`,
  `pitch`, `round`, `unlocked=1`, `sort=date|competition|phase|pitch|number`).
- `games-grid` (public seulement) : `view=teams|phases`, une colonne par terrain utilisé, au-delà de 6 terrains des
  groupes équilibrés (`App\Pdf\Games\GamesGrid`).
- `scoresheet` (admin, `ROLE_SCORER`) : une page A4 paysage par match, rubriques de FeuilleMatchMulti.php ; la
  composition du match, sinon celle de l'équipe (lue, plus copiée en base à l'impression comme le faisait le legacy).
  **Validation de la commission arbitrage requise avant mise en service (PDF-DOC-03).**
- Feuille de marque publique retirée (app2 vue match, app3 liste des matchs) ; `PdfMatchMulti.php` redirige (301)
  vers la vue match d'app2 (`URL_APP/game/{id}`), y compris depuis les pages publiques legacy (`kpmatchs`,
  `frame_terrains`).
- app4 : menu Documents de la page Matchs, PDF par match et action groupée sur api2 ; app3 : liste des matchs sur
  api2.

### Reste à faire

- Phases 2 à 6 (§ 12). La redirection des `Pdf*.php` et le retrait du lien PDF de la feuille de marque publique
  (§ 7.3.2) arrivent avec la phase 4.
- Valider les deux maquettes de référence côte à côte avec app4 et app3 (PDF-DESIGN-06), puis mesurer PDF-PERF-01 /
  PDF-PERF-04 en préprod.
