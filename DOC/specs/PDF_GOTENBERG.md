# Spécification - Génération PDF modernisée (Gotenberg)

> **Statut** : 📝 Spec - à valider avant tout code (spec first).
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
2. **Design moderne** : HTML/CSS (flex, grid, polices web, SVG), charte FFCK partagée avec app3/kpi-layer,
   lisible à l'impression noir & blanc.
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
| **Gotenberg 8** (conteneur Docker, API HTTP, Chromium + LibreOffice) | Rendu Chromium moderne, **isolé** dans son propre conteneur, sans état, API simple (`POST /forms/chromium/convert/html`), en-têtes/pieds de page avec numéros de page, bundle Symfony officiel (`sensiolabs/gotenberg-bundle`) avec intégration Twig. ✅ |

**Conclusion : Gotenberg est pertinent.** Il sépare proprement « préparer les données » (api2, testable en PHPUnit)
de « rendre » (Chromium, isolé), et permet des gabarits HTML/CSS que n'importe quel développeur front sait maintenir.

Limites connues à intégrer dans les gabarits : pas de CSS Paged Media complet (pas de `@page :left` avancé,
pas de *running elements*) ; en-tête/pied passés en documents HTML séparés (`header.html`/`footer.html`,
classes `pageNumber`/`totalPages`) ; `thead` répété par Chromium sur chaque page (suffisant pour nos tableaux).

---

## 4. Topologie : une instance partagée préprod + prod ?

Contexte : préprod et prod tournent **sur le même VPS**, en deux stacks Compose distinctes
(`APPLICATION_NAME` différent, réseaux `network_${APPLICATION_NAME}` séparés).

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

B ne devient défendable que si la RAM du VPS est réellement contrainte (à mesurer : `docker stats` sur une semaine) ;
il faudrait alors au minimum des limites `cpus`/`mem_limit`, une file bornée et la même version épinglée testée
ailleurs (dev). À réévaluer si un troisième environnement apparaît, pas avant.

En dev, le service est ajouté à `compose.dev.yaml` de la même manière.

### Configuration de référence (à confirmer sur la version épinglée)

```yaml
gotenberg:
    image: gotenberg/gotenberg:8.x.y          # version exacte épinglée, montée via PR
    container_name: ${APPLICATION_NAME}_gotenberg
    restart: unless-stopped
    networks: [network_${APPLICATION_NAME}]   # jamais traefiknetwork, aucun port publié
    mem_limit: 1g
    cpus: 1.5
    command:
        - gotenberg
        - --api-timeout=60s
        - --api-enable-basic-auth            # défense en profondeur (identifiants dans docker/.env)
        - --chromium-auto-start=false        # Chromium lancé à la première requête
        - --chromium-max-queue-size=10       # au-delà : 429 immédiat plutôt qu'une file infinie
        - --chromium-deny-list=^.*$          # aucun accès réseau depuis le HTML (anti-SSRF)
        - --chromium-allow-list=^file:///tmp/.*  # seulement les fichiers joints à la requête
        - --libreoffice-disable-routes=true  # module inutilisé
        - --webhook-disable=true
        - --prometheus-disable=true
    healthcheck:
        test: ["CMD", "curl", "-fsS", "http://localhost:3000/health"]
```

Côté api2 : `GOTENBERG_DSN=http://gotenberg:3000` (+ identifiants basic auth) dans `sources/api2/.env`.

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
                    GotenbergPdf (sensiolabs/gotenberg-bundle) ──HTTP interne──▶ gotenberg:3000
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
| options du doc | voir §7 | | ex. `photos=1`, `visa=1`, `pitches=4` |

Paramètre inconnu, mal formé ou incohérent → **400** avec message explicite (pas de PDF vide, pas de `die()`).
Aucune donnée vide → **404** (pas un PDF blanc).

### 6.2 Réponse

- `200 application/pdf`, `Content-Disposition: inline; filename="<doc>_<competition>_<season>[_<lang>].pdf"`.
- Public : `Cache-Control: public, max-age=300`, `ETag` calculé sur les données (pas sur le PDF), `304` possible.
- Admin : `Cache-Control: private, no-store`.
- Gotenberg indisponible / file pleine → **503** avec `Retry-After` ; timeout → **504**. Jamais de stack trace.

### 6.3 Ouverture depuis app4 (admin, sans session)

Un lien `<a target="_blank">` ne peut pas porter l'en-tête `Authorization`. Deux mécanismes :

1. **Par défaut : fetch + blob** (pattern déjà utilisé par `stats/index.vue`) : composable `usePdf().open(doc, params)`
   qui ouvre l'onglet **avant** l'`await` (contourne les bloqueurs de pop-up, y compris Safari iOS), récupère le PDF
   via `useApi().getBlob()`, puis y charge l'URL objet ; `download()` pour le téléchargement direct.
2. **Lien signé court** (cas où une vraie URL est indispensable : impression depuis une tablette de table de marque,
   partage à un autre poste) : `POST /api2/admin/pdf/{doc}/link` → URL `GET /api2/pdf-signed/{doc}?…&exp=…&sig=…`
   signée par `UriSigner` (HMAC, secret api2), **TTL 5 min**, liée au code utilisateur et au mandat ; les droits sont
   **re-vérifiés** au moment du téléchargement (révocation ou changement de mandat pris en compte). Pas de JWT dans l'URL.

Le mécanisme 2 est optionnel pour la première livraison (phase 1) ; à trancher en §12.

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

## 7. Catalogue cible

Rôles exprimés en profils KPI (1 = super admin … 9) ; à aligner avec [DROITS_PAR_PROFIL.md](DROITS_PAR_PROFIL.md)
lors de la validation. « Public » = route `/pdf`, « Admin » = route `/admin/pdf`.

### 7.1 Équipes / présence (données personnelles → admin uniquement)

| `doc` | Remplace | Options | Accès admin |
|---|---|---|---|
| `teams-groups` | FeuilleGroups | — | `ROLE_VIEWER` + périmètre |
| `presence` | FeuillePresence, …EN, …Photo, …Photo2, …PhotoRef, …Visa, …Cat, …U21, …Naissance | `lang`, `photos`, `visa`, `birthdate`, `category=U21\|…`, `team` | `ROLE_PRESENCE_VIEWER` + périmètre (clubs limités → leurs équipes) ; scrutineer : toutes |
| `scrutineering-control` | FeuilleControle, …EN | `lang`, `team` | `ROLE_SCRUTINEER` ou `ROLE_ORGANIZER` + périmètre |
| `referees-pool` | FeuillePoolArbitres | `lang` | à confirmer (profils de la page Pool arbitres) |
| `instances` | FeuilleInstances | — | `ROLE_ORGANIZER` + périmètre |

### 7.2 Matchs

| `doc` | Remplace | Public | Admin | Options |
|---|---|---|---|---|
| `games-list` | FeuilleListeMatchs[EN], PdfListeMatchs[EN] | ✅ matchs publiés | ✅ tous les matchs du périmètre | `lang`, `tz`, `gameday`, `event` |
| `games-grid` | PdfListeMatchs4Terrains[En,En2,En3,En4], 5TerrainsEn[Teams] | ✅ | ✅ | `pitches=1..n` (auto par défaut), `layout=…`, `teams=1` — les 6 variantes deviennent des options, à valider écran par écran |
| `scoresheet` | FeuilleMatchMulti, PdfMatchMulti | ✅ (matchs publiés, sans données réservées) | ✅ (`ROLE_SCORER` + périmètre) | `games`, `lang`, `tz` |

La **feuille de marque** est un document officiel rempli à la main : format A4, zones d'écriture et rubriques
inchangées, validées par la commission arbitrage avant mise en service (critère PDF-DOC-03).

### 7.3 Classements

| `doc` | Remplace (admin / public) | Type | Public | Admin |
|---|---|---|---|---|
| `ranking` | FeuilleCltChpt·Niveau·Multi / PdfCltChpt·Niveau·Multi | CHPT / CP / MULTI (déduit de la compétition) | classement **publié** | classement **calculé** (provisoire, marqué comme tel) |
| `ranking-team-detail` | …CltChptDetail, …CltNiveauDetail | CHPT / CP | publié | calculé |
| `ranking-gameday-detail` | …CltNiveauJournee | CHPT | publié | calculé |
| `ranking-phases` | …CltNiveauPhase | CP | publié | calculé |
| `ranking-levels` | …CltNiveauNiveau | CP | publié | calculé |
| `ranking-tiebreak` | justification départage (api2, mPDF) | CHPT / CP | — | existant (`ROLE_COMPETITION`…) |

Règle invariante : **un PDF public ne lit jamais les colonnes calculées** ; un PDF admin calculé porte la mention
« Provisoire - non publié » en filigrane.

### 7.4 Statistiques, QR codes

| `doc` | Remplace | Public | Admin |
|---|---|---|---|
| `stats` | FeuilleStats[EN], export PDF api2 (mPDF) | — | droits de la page Statistiques |
| `cards` | FeuilleCards | — | `ROLE_VIEWER` + périmètre |
| `qrcodes` | PdfQrCodes | ✅ | ✅ |
| `qrcode-app` | PdfQrCodeApp | ✅ | — |

---

## 8. Design

- **Charte** : tokens de couleur et typographie de `kpi-layer` (FFCK), déclinés en CSS d'impression dans
  `api2/templates/pdf/_base.css.twig`. Polices embarquées en WOFF2, pas de Google Fonts.
- **Gabarit commun** : en-tête (logo KPI, logo compétition/sponsor via `utyGetVisuels` équivalent, titre, saison),
  pied (date/heure de génération en `tz`, « page X / Y », URL publique ou mention « Document interne »).
- **Impression** : contraste suffisant en noir & blanc (pas d'information portée par la seule couleur),
  marges ≥ 10 mm, tableaux zébrés légers, `break-inside: avoid` sur les blocs équipe/match.
- **Accessibilité** : PDF balisé (Chromium produit des PDF taggés), langue du document renseignée, titre PDF (`<title>`) significatif.
- Maquettes HTML validées (navigateur, mode impression) **avant** câblage : un gabarit est un fichier Twig qu'on peut
  prévisualiser en HTML via une route dev-only `?format=html` (désactivée hors `dev`).

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
| R2 | Liens existants cassés (QR codes imprimés, WordPress, sites de clubs, `live/`, kptv) | élevée | moyen | Redirections 301 des `Pdf*.php` publics vers `/api2/pdf/*` (traduction des paramètres) conservées ≥ 2 saisons ; inventaire des appelants (§1.1) |
| R3 | Rejet des nouveaux documents officiels (feuille de marque, présence) par arbitres / organisateurs | moyenne | élevé | Rubriques inchangées, validation par la commission avant bascule, période de double lien « ancien / nouveau » |
| R4 | Saturation mémoire du VPS (Chromium) en pic de compétition | moyenne | élevé (touche la prod) | `mem_limit`, file bornée (429/503), instances séparées préprod/prod, mesure `docker stats` en préprod sur un lot réel |
| R5 | SSRF / injection via des données saisies | faible | élevé | §9 (deny-list réseau, autoescape, réseau interne) |
| R6 | Abus du endpoint public (rendu coûteux) | moyenne | moyen | Rate limit + cache + plafonds |
| R7 | Régression de droits (un profil voit hors de son périmètre, ou perd l'accès) | moyenne | élevé | `PdfAccessChecker` unique, tests d'intégration par profil × document (matrice §11) |
| R8 | Confusion classement publié / calculé | faible | élevé (publication erronée) | Deux providers distincts, filigrane « Provisoire », tests dédiés |
| R9 | Gotenberg indisponible = plus aucun PDF | faible | moyen | Healthcheck, restart, 503 explicite ; legacy encore en place tant que la phase n'est pas basculée |
| R10 | Coût de migration sous-estimé (≈45 fichiers, logique métier enfouie dans du code FPDF) | élevée | moyen | Consolidation du catalogue (≈18 docs au lieu de 45), migration par famille, chaque doc = 1 PR avec tests |
| R11 | Mise en page Chromium différente de l'existant (sauts de page, fusion de cellules) | moyenne | faible | Prévisualisation HTML, tests de non-régression sur le texte extrait |
| R12 | Admin legacy (Smarty) encore utilisé pendant la transition | certaine | faible | Les `Feuille*.php` restent jusqu'au retrait de l'admin legacy, mais **protégés** (phase 0) |

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
- **PDF-AUTH-05** : profil 10 obtient `presence` et `scrutineering-control` de toute équipe, et 403 sur tout autre document admin.
- **PDF-AUTH-06** : `GET /api2/pdf/*` (public) fonctionne sans authentification et n'expose ni date de naissance, ni licence, ni certificat, ni photo.
- **PDF-AUTH-07** (si lien signé retenu) : lien expiré, signature altérée, ou droits retirés entre émission et usage → 403.

**Données**
- **PDF-DATA-01** : `ranking` public n'affiche que le classement publié ; une compétition non publiée → 404.
- **PDF-DATA-02** : `ranking` admin affiche le classement calculé avec la mention « Provisoire ».
- **PDF-DATA-03** : `games-list` public n'inclut que les matchs publiés ; l'admin inclut ceux du périmètre.
- **PDF-DATA-04** : `lang=en` traduit tous les libellés fixes ; les horaires respectent `tz`.

**Documents**
- **PDF-DOC-01** : chaque document de §7 a un équivalent fonctionnel de chaque fichier legacy qu'il remplace (mêmes informations, vérifiées sur un jeu de données de référence).
- **PDF-DOC-02** : en-tête/pied conformes au gabarit (logo, titre, saison, date de génération, page X / Y).
- **PDF-DOC-03** : feuille de marque et feuille de présence validées par écrit par la commission concernée avant bascule.
- **PDF-DOC-04** : aucune ressource externe dans le PDF (polices et images embarquées).

**Robustesse**
- **PDF-PERF-01** : `scoresheet` de 100 matchs généré en < 30 s en préprod ; `ranking` en < 3 s.
- **PDF-PERF-02** : au-delà de la limite de matchs → 400 explicite ; au-delà du rate limit public → 429.
- **PDF-PERF-03** : deux requêtes publiques identiques successives → la seconde est servie par le cache (304 ou cache disque).

**Front**
- **PDF-FRONT-01** : app4 n'utilise plus aucun lien vers `legacyBaseUrl/admin/Feuille*.php` ni `Pdf*.php` ; les PDF admin passent par `usePdf()`.
- **PDF-FRONT-02** : app3 (`page-links.ts`) et app2 (`game/[id].vue`) pointent sur `/api2/pdf/*`.
- **PDF-FRONT-03** : ouverture d'un PDF admin dans un nouvel onglet fonctionne sur Chrome, Firefox, Safari macOS et Safari iOS (pas de blocage pop-up).

**Décommissionnement**
- **PDF-END-01** : après la migration finale, `grep -rn "MyPDF\|Mpdf" sources --include=*.php` (hors `vendor`) ne renvoie rien, `mpdf/mpdf` est retiré des deux `composer.json`.
- **PDF-END-02** : les anciennes URL publiques `Pdf*.php` redirigent (301) vers l'équivalent `/api2/pdf/*` ; les anciennes URL admin renvoient 410.

---

## 12. Plan de migration

| Phase | Contenu | Sortie |
|---|---|---|
| **0 - Colmatage (immédiat, sans Gotenberg)** | Protéger les `admin/Feuille*.php` qui exposent des données personnelles. Option recommandée : app4 obtient un **lien signé court** depuis api2 (même mécanisme que §6.3-2, vérifié côté legacy par un helper partageant le secret HMAC) ; l'admin legacy continue avec sa session (`MyPageSecure`). À défaut, désactiver ces liens dans app4 jusqu'à leur migration. | Plus aucun document à données personnelles accessible anonymement |
| **1 - Socle** | Service `gotenberg` (3 compose), `sensiolabs/gotenberg-bundle`, `PdfAccessChecker`, gabarit de base, `usePdf()` app4, rate limiter, CI avec Gotenberg en service | PDF-INFRA-*, PDF-AUTH-01/02, un document pilote : `teams-groups` |
| **2 - Données personnelles** | `presence` (9 variantes), `scrutineering-control`, `referees-pool`, `instances` | R1 clos ; liens app4 basculés |
| **3 - Classements** | famille `ranking*`, puis migration de `ranking-tiebreak` hors mPDF | app3 + app4 basculés pour les classements |
| **4 - Matchs** | `games-list`, `games-grid`, `scoresheet` (validation commission) | app2/app3/app4 basculés |
| **5 - Stats & QR** | `stats` (remplace aussi l'export mPDF api2), `cards`, `qrcodes`, `qrcode-app` | `mpdf/mpdf` retiré d'api2 |
| **6 - Migration finale** | À la bascule du site public (app3) et au retrait de l'admin legacy : suppression des `Feuille*.php`/`Pdf*.php`/`MyPDF`, redirections 301/410, retrait de `mpdf/mpdf` de `sources/composer.json`, archivage de ce document | PDF-END-* |

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

## 14. Questions ouvertes

1. Lien signé (§6.3-2) : livré dès la phase 1 (nécessaire pour la phase 0) ou plus tard ? *Recommandation : phase 0/1, il sert aux deux.*
2. Rôles minimum exacts par document (§7) à valider avec la matrice [DROITS_PAR_PROFIL.md](DROITS_PAR_PROFIL.md).
3. Les 6 variantes « grille terrains » ont-elles toutes un usage réel ? (stats d'accès Apache à consulter avant de les re-coder.)
4. Feuille de marque publique : doit-elle rester publique (aujourd'hui `PdfMatchMulti.php` l'est) ou devenir admin-only ?
5. Durée de conservation des redirections 301 des anciennes URL publiques (QR codes imprimés) : 2 saisons proposées.
6. RAM disponible sur le VPS : mesure à faire avant phase 1 pour confirmer l'option A (§4).
