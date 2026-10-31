# PDF Gotenberg — checklist de validation et de déploiement par phase

**Créée le** : 28 octobre 2026 · **Spec** : [PDF_GOTENBERG.md](../../specs/PDF_GOTENBERG.md) (§ 11 critères, § 12 phases,
§ 15 avancement) · **PR** : #361 (phases 0–1, fusionnée), #363 (phases 2–5)

> **Convention** (comme le [runbook](../infrastructure/DEPLOYMENT_RUNBOOK.md)) : ⌨️ poste de dev · 🖥 VPS (SSH) ·
> 🌐 interface GitHub. Cocher ☐ → ☑ au fil de l'eau ; une ligne qui échoue = on s'arrête et on corrige avant la suite.

**Règle générale** : chaque phase se valide d'abord en local, puis en préprod, puis en production. Les pages legacy
(`admin/Feuille*.php`, `Pdf*.php`) restent en place jusqu'à la phase 6 : un document mal rendu se contourne en
rouvrant temporairement l'ancien lien depuis l'admin legacy.

---

## 0. Préparation (une fois, avant le premier déploiement)

### 0.1 🖥 Secrets dans `docker/.env` de **chaque** environnement (préprod puis prod)

Sans eux, `docker compose` refuse de démarrer (c'est voulu). Une valeur **différente** par environnement.

```bash
cd /data/kpi            # (préprod : le répertoire de la préprod)
grep -c '^GOTENBERG_PASSWORD=\|^PDF_LINK_SECRET=' docker/.env     # attendu : 2 ; si 0 :
printf 'GOTENBERG_PASSWORD=%s\nPDF_LINK_SECRET=%s\n' "$(openssl rand -hex 32)" "$(openssl rand -hex 32)" >> docker/.env
grep -c '^GOTENBERG_PASSWORD=\|^PDF_LINK_SECRET=' docker/.env     # attendu : 2
```

| # | Vérification | Attendu | ☐ |
|---|---|---|---|
| 1 | Les deux variables existent en préprod | `2` | ☐ |
| 2 | Les deux variables existent en prod, **valeurs différentes** de la préprod | `2` | ☐ |

### 0.2 Variables utilisées dans les commandes ci-dessous

À renseigner avec des données **réelles** de l'environnement testé (une compétition en cours, ses équipes…).

```bash
# ⌨️ dev : B=https://kpi.localhost/api2   L=https://kpi.localhost
# préprod : B=https://preprod.kayak-polo.info/api2   L=https://preprod.kayak-polo.info
# prod    : B=https://kayak-polo.info/api2   L=https://kayak-polo.info
B=https://kpi.localhost/api2; L=https://kpi.localhost
S=2026            # saison
C=N1H             # compétition CHPT publiée (classement publié, matchs publiés)
CP=CFH            # compétition CP (coupe) publiée
E=123             # événement publié
G=4567            # journée de C
TEAM=890          # équipe de C
M=12345           # match de C
login() { curl -sk -X POST "$B/auth/login" -H 'Content-Type: application/json' \
  -d "{\"username\":\"$1\",\"password\":\"$2\"}" | jq -r .token; }
pdf() { curl -sk -o /tmp/doc.pdf -w '%{http_code} %{content_type}\n' "$@"; }   # code HTTP + type
ADMIN=$(login <code_profil_1_ou_2> '<mot_de_passe>')
ORGA=$(login <code_profil_3_a_6> '<mot_de_passe>')
TEAM7=$(login <code_profil_7> '<mot_de_passe>')
SCRUT=$(login <code_profil_10> '<mot_de_passe>')
```

`pdf …` affiche par exemple `200 application/pdf` ; ouvrir `/tmp/doc.pdf` pour le contrôle visuel.

---

## Phases 0 et 1 — Colmatage legacy + socle (PR #361, fusionnée)

### 1.1 ⌨️ Local

```bash
git checkout main && git pull
make docker_dev_up && make dev_status          # ligne gotenberg absente : vérifier ci-dessous
docker ps --filter name=gotenberg --format '{{.Names}} {{.Status}}'
make api2_composer_install && make api2_restart
make api2_test                                  # unit + integration
```

| # | Commande / action | Attendu | ☐ |
|---|---|---|---|
| 1 | `docker ps --filter name=gotenberg` | `kpi_gotenberg Up … (healthy)` | ☐ |
| 2 | `make api2_test` | les deux suites vertes | ☐ |
| 3 | `curl -sk -o /dev/null -w '%{http_code}\n' "$L/admin/FeuillePresence.php?S=$S&Compet=$C"` (sans session) | `403` (PDF-AUTH, phase 0) | ☐ |
| 4 | `pdf "$B/admin/pdf/teams-groups?season=$S&competition=$C"` (sans JWT) | `401` | ☐ |
| 5 | `pdf -H "Authorization: Bearer $ADMIN" "$B/admin/pdf/teams-groups?season=$S&competition=$C"` | `200 application/pdf`, charte app4 (bandeau, logo KPI) | ☐ |
| 6 | `pdf "$B/pdf/ranking?season=$S&competition=$C"` | `200 application/pdf`, charte app3, logos FFCK + CNA, QR code | ☐ |
| 7 | Même requête qu'en 6 avec `-H 'If-None-Match: "<etag>"'` (etag lu par `curl -skI`) | `304` | ☐ |
| 8 | Classement public avec médailles (compétition terminée du tour final) | médailles affichées, **aucune** mention « Qualifié » | ☐ |
| 9 | app4, page Équipes → menu PDF → « Poules » | nouvel onglet, PDF api2 | ☐ |
| 10 | app4, page Documents → une feuille de présence (legacy encore) | s'ouvre (lien signé) | ☐ |
| 11 | `docker stop kpi_gotenberg` puis requête 5 | `503`, en-tête `Retry-After` ; puis `docker start kpi_gotenberg` | ☐ |

### 1.2 🖥 Préprod (automatique après merge sur `main`)

```bash
ssh … && cd <répertoire préprod>
docker ps --filter name=gotenberg --format '{{.Names}} {{.Status}}'
# si absent (le déploiement ne crée pas toujours un nouveau service) :
make docker_preprod_up && make api2_restart
make gotenberg_logs lines=20
```

| # | Vérification | Attendu | ☐ |
|---|---|---|---|
| 1 | `kpi_preprod_gotenberg` up, healthy | oui | ☐ |
| 2 | Lignes 3 à 9 du § 1.1 avec `B`/`L` de préprod | mêmes résultats | ☐ |
| 3 | `docker stats --no-stream kpi_preprod_gotenberg` | mémoire < 512 Mio | ☐ |

### 1.3 🌐🖥 Production

🌐 tag `vX.Y.Z` sur `main` → Actions « Deploy production » (`ref` = le tag) → approuver. Puis 🖥 les mêmes contrôles
qu'au § 1.2 (`make docker_prod_up` si le conteneur `…_gotenberg` manque), lignes 3 à 9 du § 1.1 avec `B`/`L` de prod.

**Rollback** : 🌐 PR de revert du merge → CI → merge (préprod) → release. En urgence préprod : `make last_merge_sha`
puis `make preprod_rollback sha=…` ([runbook § 4](../infrastructure/DEPLOYMENT_RUNBOOK.md)).

---

## Phase 2 — Données personnelles (PR #363)

### 2.1 ⌨️ Local

```bash
git fetch origin && git checkout claude/gotemberg-pdf-migration-qt37jx && git pull
make api2_composer_install && make api2_restart && make api2_test
```

| # | Commande / action | Attendu | ☐ |
|---|---|---|---|
| 1 | `pdf -H "Authorization: Bearer $ADMIN" "$B/admin/pdf/presence?season=$S&competition=$C"` | `200`, une page par équipe, joueurs puis encadrement, mention « Document interne » en pied | ☐ |
| 2 | idem `&team=$TEAM&lang=en` | `200`, une seule équipe, libellés anglais | ☐ |
| 3 | idem `&view=photos` | `200`, photos des joueurs, dates de naissance | ☐ |
| 4 | idem `&view=category` puis `&view=category&category=U21` | `200` ; U21 : seuls les moins de 21 ans, avec date de naissance | ☐ |
| 5 | `…&visa=1` avec `$TEAM7` | `403` (I2) ; avec `$ORGA` : `200`, colonne Visa | ☐ |
| 6 | `…&view=birthyear` avec `$ORGA` | `403` ; avec `$ADMIN` : `200`, par année puis mois | ☐ |
| 7 | `presence` avec `$TEAM7` sans `team` | `200`, **seulement** les équipes de son club (PDF-AUTH-04) | ☐ |
| 8 | `presence&team=<équipe d'un autre club>` avec `$TEAM7` | `403` | ☐ |
| 9 | `pdf -H "Authorization: Bearer $SCRUT" "$B/admin/pdf/scrutineering-control?season=$S&competition=$C"` | `200`, Kayak / Gilet / Casque / Nb pagaies (PDF-AUTH-05) | ☐ |
| 10 | `scrutineering-control` avec `$ORGA` | `403` (I1) | ☐ |
| 11 | `pdf -H "Authorization: Bearer $ADMIN" "$B/admin/pdf/referees-pool?season=$S"` | `200`, arbitres actifs par nation | ☐ |
| 12 | `pdf -H "Authorization: Bearer $ORGA" "$B/admin/pdf/instances?season=$S&competition=$C&gameday=$G"` | `200`, comité et jury, **aucun numéro de licence** | ☐ |
| 13 | app4 Équipes : menus PDF global et par équipe (FR, EN, photos, visa, contrôle, catégories) | chaque entrée ouvre le PDF api2 (URL `/api2/pdf-signed/…`) | ☐ |
| 14 | app4 Présence équipe, Scrutineering équipe, Pool arbitres, Journées → « Officiels » → imprimer | idem | ☐ |
| 15 | app4 Documents → section Équipes et Contrôle (présence, U21, naissance) | idem | ☐ |
| 16 | **Comparer** chaque document avec son équivalent legacy (admin legacy, même compétition) | mêmes informations (PDF-DOC-01) | ☐ |
| 17 | **Feuille de présence** : validation écrite de la commission concernée (PDF-DOC-03) | accord reçu | ☐ |

### 2.2 🖥 Préprod puis production

Après merge de #363 : lignes 1 à 15 avec `B` de préprod ; puis release et mêmes lignes en production.
`make api2_logs_errors lines=100` ne doit montrer aucune erreur `App\Pdf`.

---

## Phase 3 — Classements (PR #363)

| # | Commande / action | Attendu | ☐ |
|---|---|---|---|
| 1 | `pdf -H "Authorization: Bearer $ORGA" "$B/admin/pdf/ranking?season=$S&competition=$C"` | `200`, classement **calculé**, filigrane « Provisoire - non publié » (PDF-DATA-02) | ☐ |
| 2 | même requête avec `$TEAM7` | `403` (I5) | ☐ |
| 3 | `pdf "$B/pdf/ranking-team-detail?season=$S&competition=$C"` | `200`, chaque équipe classée avec ses matchs **publiés** par journée | ☐ |
| 4 | `pdf "$B/pdf/ranking-gameday-detail?season=$S&competition=$C"` | `200`, un tableau par journée publiée | ☐ |
| 5 | `pdf "$B/pdf/ranking-phases?season=$S&competition=$CP"` | `200`, poules d'abord (classement), puis phases finales (matchs) | ☐ |
| 6 | Requêtes 3 à 5 en admin (`/admin/pdf/…` avec `$ORGA`) | `200`, valeurs calculées, filigrane | ☐ |
| 7 | `pdf "$B/pdf/ranking-team-detail?season=$S&competition=<compétition non publiée>"` | `404` (PDF-DATA-01) | ☐ |
| 8 | app4 Classements : bouton « Justification du départage » (compétition avec égalités) | PDF Gotenberg, charte admin, cascade des critères | ☐ |
| 9 | app4 Classements et Documents : tous les liens PDF de classement (admin et publiés) | PDF api2 | ☐ |
| 10 | app3 `beta.*` : onglet Classement d'une CHPT puis d'une coupe → lien PDF | `/api2/pdf/ranking…` puis `/api2/pdf/ranking-phases…` | ☐ |
| 11 | Comparaison avec `PdfClt*.php` / `FeuilleClt*.php` sur la même compétition | mêmes rangs, points, détails | ☐ |

Puis préprod / production comme au § 2.2.

---

## Phase 4 — Matchs (PR #363)

| # | Commande / action | Attendu | ☐ |
|---|---|---|---|
| 1 | `pdf "$B/pdf/games-list?season=$S&competition=$C"` | `200`, matchs publiés par jour et lieu, arbitres sans parenthèses | ☐ |
| 2 | `pdf "$B/pdf/games-list?season=$S&event=$E&lang=en"` | `200`, tout l'événement, en anglais | ☐ |
| 3 | `pdf "$B/pdf/games-list?season=$S&competition=$C,$CP"` | `200`, deux compétitions | ☐ |
| 4 | `pdf -H "Authorization: Bearer $TEAM7" "$B/admin/pdf/games-list?season=$S&competition=<sa compétition>"` | `200`, **matchs publiés seulement** (PDF-AUTH-10) | ☐ |
| 5 | `pdf "$B/pdf/games-grid?season=$S&event=$E"` | `200`, une colonne par terrain **utilisé**, équipes dans les cases (PDF-DATA-05) | ☐ |
| 6 | idem `&view=phases` ; puis un événement de plus de 6 terrains | phases dans les cases ; colonnes en groupes équilibrés (PDF-DATA-06) | ☐ |
| 7 | idem `&view=autre` | `400` (PDF-DATA-07) | ☐ |
| 8 | `pdf -H "Authorization: Bearer <profil 9>" "$B/admin/pdf/scoresheet?season=$S&games=$M"` | `200`, une page A4 paysage, toutes les rubriques de la feuille legacy | ☐ |
| 9 | Feuille de marque d'un match d'une compétition internationale | anglais, 12 lignes par équipe, sans licence | ☐ |
| 10 | `curl -skI "$L/PdfMatchMulti.php?listMatch=$M" \| grep -i '^location\|^HTTP'` | `301` vers `https://app.<domaine>/game/$M` | ☐ |
| 11 | app2 `/game/$M` | plus de bouton « Télécharger le PDF » | ☐ |
| 12 | app3 onglet Matchs : plus de lien « PDF » par match ; lien « Liste des matchs (PDF) » | `/api2/pdf/games-list?…` | ☐ |
| 13 | app4 Matchs : menu Documents (listes FR/EN, feuilles de marque, listes publiques, grilles équipes/phases), PDF d'un match, action groupée | PDF api2, filtres de la page respectés | ☐ |
| 14 | `make api2_logs lines=50` pendant une feuille de 100 matchs (préprod) | rendu < 60 s en préprod (PDF-PERF-01, cible × 2) | ☐ |
| 15 | **Feuille de marque** : validation écrite de la commission arbitrage (PDF-DOC-03) **avant** de la présenter comme officielle | accord reçu | ☐ |

Puis préprod / production comme au § 2.2.

---

## Phase 5 — Statistiques, cartons, QR codes, retrait de mPDF d'api2 (PR #363)

```bash
make api2_composer_install && make api2_restart     # mpdf/mpdf retiré du lock d'api2
docker exec kpi_api2 composer show mpdf/mpdf        # attendu : « Package mpdf/mpdf not found »
```

| # | Commande / action | Attendu | ☐ |
|---|---|---|---|
| 1 | app4 Statistiques → Export PDF (plusieurs types, dont « Irrégularités » avec un profil 1–6) | PDF Gotenberg, charte admin, mêmes colonnes qu'avant | ☐ |
| 2 | `pdf -H "Authorization: Bearer $ORGA" "$B/admin/pdf/cards?season=$S&competition=$C"` | `200`, cumul V/J/R/RD par joueur, colonnes à remplir | ☐ |
| 3 | même requête avec `$TEAM7` | `403` | ☐ |
| 4 | `pdf "$B/pdf/qrcodes?season=$S&event=$E"` | `200`, deux QR codes ; les scanner : pages Matchs et Progression de l'événement sur `beta.*` | ☐ |
| 5 | `pdf "$B/pdf/qrcode-app?season=$S&event=$E"` | `200`, QR code → `https://app.<domaine>/event/$E` | ☐ |
| 6 | QR codes d'un événement **non publié** | `404` | ☐ |
| 7 | app4 Documents → Cartons, QR codes, QR code application | PDF api2 | ☐ |
| 8 | `docker exec kpi_api2 env \| grep APP2_URL` | `APP2_URL=https://app.<domaine>` | ☐ |
| 9 | CI : job `audit-composer` | vert (symfony/mime 7.4.22) | ☐ |

Puis préprod / production comme au § 2.2 (`make api2_restart` après déploiement en prod : le worker FrankenPHP
garde le noyau en mémoire).

---

## Visuels de la compétition et langue (décisions du 2026-10-29, PR suivante)

Spec : [PDF_GOTENBERG.md § 8.4.1](../../specs/PDF_GOTENBERG.md) et § 14. Choisir dans app4 (Compétitions → Visuels)
une compétition **A** avec bandeau coché, une **B** avec logo coché sans bandeau, une **C** sans visuel, et cocher le
sponsor de **A**.

| # | Commande / action | Attendu | ☐ |
|---|---|---|---|
| 1 | `pdf "$B/pdf/ranking?season=$S&competition=<A>"` | bandeau en tête **à la place** des logos FFCK/CNA, titre dessous ; sponsor au-dessus du pied de chaque page | ☐ |
| 2 | même document pour **B** | logos FFCK + CNA à gauche (si « KPI/FFCK » coché, sinon aucun), logo de B à droite | ☐ |
| 3 | même document pour **C** | FFCK + CNA, même si « KPI/FFCK » est décoché (PDF-DESIGN-04) | ☐ |
| 4 | `pdf -H "Authorization: Bearer $ADMIN" "$B/admin/pdf/teams-groups?season=$S&competition=<C>"` après avoir décoché « KPI/FFCK » | titre seul, sans logo KPI | ☐ |
| 5 | `pdf "$B/pdf/games-list?season=$S&event=$E"` | visuels de la **première** compétition de l'événement | ☐ |
| 6 | Feuille de marque d'un match de **A** | bandeau réduit en haut à gauche, sponsor en bas à droite, **une page par match** | ☐ |
| 7 | app4 en anglais : Équipes → feuille de présence ; Matchs → liste des matchs | un seul lien par document, PDF en anglais ; en français : PDF en français | ☐ |
| 8 | Feuille de marque d'une compétition internationale, app4 en français | en français (plus d'anglais forcé) | ☐ |

**Compétitions dont un visuel coché est une URL externe** (ignoré dans les PDF, à téléverser dans app4 en attendant
l'import automatique, § 8.4.1) — 🖥 sur chaque environnement :

```bash
. docker/.env && docker exec -i ${APPLICATION_NAME}_db mariadb -uroot -p"$DB_ROOT_PASSWORD" "$DB_NAME" -e "
SELECT Code_saison, Code, Libelle,
       IF(Bandeau_actif = 'O' AND BandeauLink LIKE 'http%', BandeauLink, '') AS bandeau,
       IF(Logo_actif = 'O' AND LogoLink LIKE 'http%', LogoLink, '') AS logo,
       IF(Sponsor_actif = 'O' AND SponsorLink LIKE 'http%', SponsorLink, '') AS sponsor
FROM kp_competition
WHERE (Bandeau_actif = 'O' AND BandeauLink LIKE 'http%') OR (Logo_actif = 'O' AND LogoLink LIKE 'http%')
   OR (Sponsor_actif = 'O' AND SponsorLink LIKE 'http%')
ORDER BY Code_saison DESC, Code;"
```

---

## Ajustements du 2026-10-30 (même PR)

Spec : [PDF_GOTENBERG.md](../../specs/PDF_GOTENBERG.md) § 6.4, § 7.2 à § 7.4, § 8.4.1 et § 14. Choisir une compétition
**N** d'une section Nationale ou Régionale, une **I** d'une autre section (internationale, tournoi…) et une coupe
**K** (type CP) ; `$E` un événement à plusieurs compétitions.

| # | Commande / action | Attendu | ☐ |
|---|---|---|---|
| 1 | `pdf -H "Authorization: Bearer $ADMIN" "$B/admin/pdf/teams-groups?season=$S&competition=<N>"` | saison en haut à droite, à la hauteur de « Équipes » ; ni « Saison » ni code de compétition | ☐ |
| 2 | Journal : 🖥 `. docker/.env && docker exec -i ${APPLICATION_NAME}_db mariadb -uroot -p"$DB_ROOT_PASSWORD" "$DB_NAME" -e "SELECT Dates, Users, Actions, Saisons, Competitions, Journal FROM kp_journal WHERE Actions LIKE 'PDF %' ORDER BY Id DESC LIMIT 5"` | une ligne « PDF teams-groups » de l'utilisateur pour la ligne 1 ; aucune ligne pour un PDF public ; app4 Journal les affiche | ☐ |
| 3 | `pdf -H "Authorization: Bearer $ADMIN" "$B/admin/pdf/presence?season=$S&competition=<N>"` | colonnes Pagaie et Certif. ; statuts « Capitaine », « Staff »… en toutes lettres | ☐ |
| 4 | même document pour **I** | sans Pagaie ni Certif. | ☐ |
| 5 | `pdf "$B/pdf/games-list?season=$S&event=$E"` (au moins 30 matchs) | la liste commence en **page 1** ; colonne Compét. avec les libellés ; Phase \| Match sur 2 lignes ; noms d'équipes sur une ligne, centrés ; une colonne Arbitres (principal au-dessus) | ☐ |
| 6 | `pdf "$B/pdf/games-list?season=$S&competition=<N>"` | pas de colonne Compét. | ☐ |
| 7 | Feuille de marque d'un match d'une compétition sans bandeau ni logo | logos FFCK et KPI en haut à gauche, une page par match | ☐ |
| 8 | `pdf "$B/pdf/ranking?season=$S&competition=<K>"` et app3 `/competitions/$S/<K>/ranking` | classement final seul (rang, équipe, médailles), sans Pts ni J | ☐ |
| 9 | `pdf "$B/pdf/ranking-team-detail?season=$S&competition=<K>"` | matchs de chaque équipe sous le **nom de la phase**, pas sous la date et le lieu | ☐ |
| 10 | app4 Statistiques → export PDF de plusieurs compétitions | titre et colonne Compétition en libellés, pas en codes | ☐ |

---

## Ajustements du 2026-10-31 (même PR)

Spec : [PDF_GOTENBERG.md](../../specs/PDF_GOTENBERG.md) § 7.2, § 7.3 et § 14. Mêmes compétitions **N** (championnat),
**K** (coupe) qu'à la section précédente, et une compétition **M** de type MULTI.

| # | Commande / action | Attendu | ☐ |
|---|---|---|---|
| 1 | `pdf -H "Authorization: Bearer $ADMIN" "$B/admin/pdf/teams-groups?season=$S&competition=<N>"`, puis **M** | une seule liste, pleine largeur, sans « Sans poule » | ☐ |
| 2 | même document pour **K** | poules côte à côte | ☐ |
| 3 | `pdf -H "Authorization: Bearer $ADMIN" "$B/admin/pdf/presence?season=$S&competition=<N>"` | arbitres et inactifs sous l'intertitre « Non joueurs », après joueurs et staff | ☐ |
| 4 | `pdf "$B/pdf/games-list?season=$S&competition=N3"` et l'admin équivalent (lien app4) | les matchs commencent en **page 1** | ☐ |
| 5 | `games-list` d'une compétition dont des matchs ne sont pas encore attribués | « (1er Poule A) », « (Vainqueur match 12) »… à la place des équipes et arbitres ; Phase / Match sans `[…]` | ☐ |
| 6 | `games-list`, `ranking-team-detail`, `ranking-phases`, justification de départage | équipe vainqueur en gras et **son score seul** (« **3** - 1 »), pas celui du perdant | ☐ |
| 7 | Feuille de marque | colonne RD après V, J, R de chaque équipe ; un carton rouge définitif saisi y est coché ; colonnes des cartons sur fond pastel ; « (Capitaine) » après le nom du capitaine, « Staff » à la place du numéro ; une page par match, même avec une grille d'événements pleine | ☐ |
| 8 | app4 Journées → Schéma de **K** → bouton PDF (cases Matchs / Horaires cochées puis décochées) | PDF Gotenberg en colonnes par étape, vainqueur en premier ; nombre de matchs et horaires selon les cases | ☐ |
| 9 | même chose pour **N** | une carte par journée, matchs sur 3 colonnes | ☐ |
| 9b | `pdf "$B/pdf/ranking-phases?season=$S&competition=<K>"` | chaque phase sous son seul titre, sans date ni lieu | ☐ |
| 10 | app3 `/competitions/$S/<K>/ranking` → « Classement (PDF) » | `…/api2/pdf/ranking?season=$S&competition=<K>&lang=fr` | ☐ |
| 11 | Feuille de marque ou instances d'une journée avec département | lieu « Lacville (33) », parenthèse fermante comprise | ☐ |

---

## Non-régression (à chaque phase, chaque environnement)

| # | Vérification | Attendu | ☐ |
|---|---|---|---|
| 1 | `make dev_status` (local) / smoke tests du déploiement | legacy, api2, app2, app4, app3 inchangés | ☐ |
| 2 | Admin legacy : ouvrir un `Feuille*.php` avec une session legacy | toujours servi (jusqu'à la phase 6) | ☐ |
| 3 | `make gotenberg_logs lines=50` | aucune requête refusée inattendue, aucun accès réseau sortant | ☐ |
| 4 | `make api2_logs_errors lines=100` | aucune erreur `App\Pdf` | ☐ |

## Phase 6 — Migration finale (non commencée)

Hors de cette PR : suppression des `Feuille*.php` / `Pdf*.php` / `MyPDF`, redirections 301 (1 saison) et 410,
retrait de `mpdf/mpdf` de `sources/composer.json`, archivage de la spec (`git mv` vers `archive/pdf-exports/`).
