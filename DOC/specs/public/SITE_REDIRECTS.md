# Redirections 301 de la bascule de `www`

**Phase** : 5 — **Statut** : 📝 Brouillon — **Périmètre** : `www.kayak-polo.info` et `kayak-polo.info` —
**Remplace** : les URL publiques du legacy (`kp*.php`) et de WordPress

## 1. Objectif

Au moment où `www` passe de Apache (legacy + WordPress) à app3, **aucune ancienne URL publique encore utilisée
ne doit casser** : liens partagés, favoris, QR codes, signets de clubs, référencement. Chaque ancienne URL
reçoit soit une **301** vers son équivalent app3, soit une décision explicite (410, page la plus proche, ou
maintien sur Apache).

Sources : plan d'URL ([stratégie § 6](../../developer/in-progress/PUBLIC_SITE_REDESIGN_STRATEGY.md)), tables
« Correspondance des anciennes URL » de chaque spec de page (consolidées ici, qui fait foi en cas d'écart),
[FEATURE_CMS.md § 8](FEATURE_CMS.md), routage Traefik (`docker/compose.prod.yaml`), checklist de la
[phase 5](../../developer/in-progress/PUBLIC_SITE_ROLLOUT_CHECKLIST.md).

## 2. Où vivent les redirections

| Famille | Mécanisme | Existant |
|---|---|---|
| Legacy `kp*.php` | **Middleware serveur Nitro d'app3** `server/middleware/legacy-paths.ts`, logique pure dans `server/utils/legacy-paths.ts` (table de correspondance, testée) | à créer |
| WordPress (permaliens, `?p=`, `?page_id=`, articles non repris) | Middleware `server/middleware/old-paths.ts` → api2 `GET /redirect` → table `kp_cms_redirect` | ✅ en place |
| WordPress « structurel » (flux, catégories, recherche, sitemaps) | Même middleware que le legacy (règles fixes, § 6.2) | à créer |
| `kayak-polo.info` → `www` | Traefik (`redirectregex` permanent, chemin et requête conservés) ; déjà en place, à vérifier après l'inversion du routage (§ 7) | existant |

Le middleware ne répond qu'à `GET` et `HEAD`. Une URL qui ne correspond à aucune règle continue vers la
page 404 d'app3. Les redirections sont émises **avant** tout appel de données de page (aucun rendu SSR gaspillé).

## 3. Routage Traefik à la bascule

Apache ne reçoit plus qu'une **liste explicite** de préfixes ; tout le reste va à app3
([stratégie § 4.2](../../developer/in-progress/PUBLIC_SITE_REDESIGN_STRATEGY.md)). Les `kp*.php` **ne figurent pas**
dans cette liste : c'est ce qui les fait atterrir sur le middleware.

| Reste sur Apache (liste explicite) | Raison |
|---|---|
| `/admin/`, `/Login.php`, `/AdminChoice.php`, `/scenario.php` | Administration legacy et pages non encore inventoriées (Q-R8) |
| `/Pdf*.php`, `/kpqr.php` | PDF publics (QR codes imprimés) et générateur de QR : liens externes et documents imprimés ; hors phase 5 (PDF → api2/Gotenberg) |
| `/frame_*.php` | Écrans de gymnase et iframes tiers, hors phase 5 → **phase 7** (§ 5.3) |
| `/live/`, `/api/`, `/img/`, `/media/`, `/css/`, `/fonts/`, `/js/` et les scripts de données (`json-clubs.php`, `json-events.php`, `searchClubs.php`, `searchEquipes.php`, `ajax_search_equipe.php`, `api_players.php`, `api_stats.php`, `upload_ics.php`) | Ressources et appels de données du legacy encore utilisés par l'admin et les écrans |
| `/wordpress/wp-content/uploads/`, `/wp-login.php`, `/wp-admin/`, `/wp-json/` | Médias de contenus non repris, connexion des rédacteurs, jusqu'à la phase 6 (§ 6.4) |

Cette liste est **la checklist de décommissionnement** : chaque entrée qui disparaît en phase 6 ou 7 est
d'abord remplacée par une réponse 301 ou 410 décidée ici (§ 7).

## 4. Règles communes pour les paramètres de requête

1. **Langue** — `lang=en` → préfixe `/en` ; `lang=fr`, absent ou toute autre valeur (`cn`…) → pas de préfixe.
   Le legacy mémorisait la langue en session : sans paramètre on ne peut pas la deviner, le français est retenu.
2. **Saison** — `Saison=S` : `S` est conservé s'il vaut quatre chiffres, sinon (absent ou invalide) c'est la
   **saison active**, lue dans api2 (même source que `/competitions`) ; si api2 ne répond pas, repli sur la
   page de liste sans saison (`/competitions`).
3. **Valeur « tout »** — `Compet=*`, `J=*`, `Round=*`, `Group=*` sont équivalents à l'absence du paramètre.
4. **Paramètres de navigation retenus** (convertis, jamais copiés tels quels) :

| Legacy | App3 | Remarque |
|---|---|---|
| `event=E` (entier) | `?event=E` sur une compétition ; chemin `/events/E/…` si aucune compétition n'est désignée | valeur non numérique → ignorée |
| `J=id` (entier) | `?gameday=id` (onglet `games` seulement) | |
| `filtreJour=AAAA-MM-JJ` | `?day=AAAA-MM-JJ` (onglets `games`, `pitches`) | date invalide → ignorée |
| `next=next` | `?upcoming=1` (onglet `games`) | |
| `Group=G` | `?group=G` ou segment `/groups/S/G/…` | selon la route (§ 5.1) |
| `Equipe=N`, `Compet=C`, `Saison=S` (fiche équipe) | `/teams/N?competition=C&season=S` | |
| `clubId=C` | `?club=C` | |

5. **Paramètres abandonnés** (retirés sans effet, jamais transmis) : `Round`, `private`, `nbLignes`, `Compets`,
   `terrains`, `arbitres`, `intervalle`, `voie`, `navGroup`, `mirror`, `Pg`, `len`, `start`. Ce sont des réglages
   d'écran de gymnase ou de session, sans équivalent sur un site consultatif. **Exception** : `Css`, § 5.3.
6. **Paramètres inconnus** : retirés. La redirection ne produit jamais de paramètre que l'ancien lien ne
   contenait pas (hors saison active, règle 2). Pas de chaîne de redirections : la cible est **directement**
   l'URL finale d'app3 (une 301 suivie d'une autre 301 est un défaut).
7. **Sécurité** — la cible est toujours un chemin relatif construit par la table ; aucune valeur de requête n'est
   réinjectée sans validation (codes de compétition `[A-Za-z0-9_-]{1,20}`, saisons `\d{4}`, entiers `\d{1,10}`).
   Une valeur invalide fait ignorer le paramètre, jamais échouer la requête (pas de 500, pas de redirection
   ouverte vers un autre domaine).
8. **Fragment, `HEAD`, méthodes** — `HEAD` répond comme `GET` ; `POST` n'est jamais redirigé.

## 5. Table de correspondance du legacy

### 5.1 Pages de résultats (`kp*.php`)

`S` = saison, `C` = code de compétition, `G` = groupe, `E` = événement, `N` = numéro d'équipe. Le préfixe `/en` s'ajoute
selon la règle 1. Les paramètres entre crochets sont ceux du § 4.

| Ancienne URL | Nouvelle URL |
|---|---|
| `/kpcalendrier.php` | `/calendar` |
| `/kpclassements.php[?Saison=S][&Group=G]` | `/competitions/S[?group=G]` ; sans saison : saison active |
| `/kpclassements.php?Saison=S&Compet=C` (compétition précise) | `/competitions/S/C/ranking` |
| `/kpmatchs.php?Saison=S&Compet=C` | `/competitions/S/C/games[?event][&gameday][&day][&upcoming]` |
| `/kpmatchs.php?event=E` (sans compétition précise) | `/events/E/games[?day][&upcoming]` |
| `/kpmatchs.php?Saison=S&Group=G` (`Compet=*` ou absent) | `/groups/S/G/games[?day][&upcoming]` |
| `/kpterrains.php?Saison=S&Compet=C` | `/competitions/S/C/pitches[?event][&day]` |
| `/kpterrains.php?event=E` | `/events/E/pitches[?day]` |
| `/kpterrains.php?Saison=S&Group=G` (`Compet=*`) | `/groups/S/G/pitches[?day]` |
| `/kpdetails.php?Saison=S&Compet=C` | `/competitions/S/C/info[?event]` |
| `/kpdetails.php?event=E` (sans compétition) | `/events/E/info` |
| `/kpclassement.php?Saison=S&Compet=C` | `/competitions/S/C/ranking[?event]` |
| `/kpclassement.php?event=E` | `/events/E/ranking` |
| `/kpchart.php?Saison=S&Compet=C` | `/competitions/S/C/progress[?event]` |
| `/kpphases.php?Saison=S&Compet=C` | `/competitions/S/C/progress?view=vertical[&event]` |
| `/kpchart.php?event=E`, `/kpphases.php?event=E` | `/events/E/progress[?view=vertical]` |
| `/kpstats.php?Saison=S&Compet=C` | `/competitions/S/C/stats[?event]` |
| `/kpstats.php?event=E` | `/events/E/stats` |
| `/kphistorique.php?Group=G` | `/history/G` ; sans `Group` : `/history` |
| `/kpequipes.php?Equipe=N[&Compet=C&Saison=S]` | `/teams/N[?competition=C&season=S]` ; sans `Equipe` : `/teams` |
| `/kpclubs.php?clubId=C` | `/clubs?club=C` |
| `/kpclubs.php`, `/kplogos.php` | `/clubs` |

Cas particuliers :
- **Compétition sans saison** (`Compet=C` seul) : saison active (règle 2) ; si `C` n'existe pas dans cette saison,
  la 301 est tout de même émise (la page cible rend son propre 404) — le middleware ne valide pas l'existence.
- **`Compet=*` sans `Group`** : le legacy retombait sur le groupe de la session (`N1H` par défaut). Sans moyen de
  le connaître : `/competitions/S` (liste de la saison).
- Les pages internes d'app3 qui redirigent déjà (`/teams` → `/clubs`, `/clubs/{code}` → `/clubs?club={code}`,
  PAGE_TEAM § 2.1, PAGE_CLUBS CLB-08) ne sont **pas** doublées : la cible de la table est déjà la page finale
  quand elle existe (`/teams/N`, `/clubs?club=C`).

### 5.2 Autres URL du legacy

| Ancienne URL | Décision |
|---|---|
| `/index.php`, `/fr/`, `/fr/index.php` | 301 → `/` |
| `/en/`, `/en/index.php` | 301 → `/en` |
| `/kpqr.php`, `/Pdf*.php` | Maintenus sur Apache (§ 3) — aucune redirection |
| `/kpadmin.php` (non fonctionnel), `/kptv.php` + `/kptvscenario.php` (panneau de contrôle TV legacy, remplacé par `/tv` d'app4), `/tv.php` (incrustations legacy), `/feuillemarque.php` et `/gamereport.php` (accès à la feuille de marque en ligne V2 legacy, en français / en anglais) | **410** par le middleware, **sans redirection** : plus rien à garder après la bascule, **à condition que le scoring soit publié aux profils adéquats avant** (prérequis P1, § 11) |
| `/json-*.php`, `/search*.php`, `/ajax_search_equipe.php`, `/api_*.php`, `/upload_ics.php` | Maintenus sur Apache jusqu'à la phase 6, puis **410** (§ 7) |
| Match isolé (feuille publique) | `/games/{id}` n'est pas livré : tant qu'il ne l'est pas, les liens existants vers app2 (`/game/{id}`) restent valables, aucune redirection |

### 5.3 `frame_*.php` et paramètre `Css`

Les écrans de gymnase et les iframes de partenaires utilisent `frame_*.php` et les paramètres de rotation
(`voie`, `intervalle`, `Css`…). Leur remplacement (`/embed/...` + `?theme=`) est la **phase 7** : **à la bascule, aucun
`frame_*.php` n'est redirigé, tous restent servis par Apache** (§ 3). Ils ne dépendent pas de WordPress ni des pages
`kp*.php`.

Les adresses `frame_*.php?…` enregistrées en base (`kp_tv`, [PAGE_TV_CONTROL_PANEL.md](../PAGE_TV_CONTROL_PANEL.md)) **ne sont pas migrées** : aucun écran ne tourne (décision du 20/11/2026), les liens seront recréés dans le nouveau système après la bascule. Le chantier scoring ([PAGE_INCRUSTATION.md](../PAGE_INCRUSTATION.md)) ne reprend pas les `frame_*.php` : ils restent sur Apache pour les iframes tierces jusqu'à la phase 7.

Pour un `kp*.php` appelé avec **`Css=`** (intégré dans une iframe par un tiers, thème `simply`, `ckca`,
`saintomer2022`…) : une page complète d'app3 (en-tête, menu) dans une iframe serait un contresens. Règle :
**redirection 301 vers le `frame_*.php` équivalent, même requête conservée** (les `frame_*` acceptent les mêmes
paramètres) :

| `kp*.php?Css=…` | → |
|---|---|
| `kpmatchs.php` | `frame_matchs.php` |
| `kpterrains.php` | `frame_terrains.php` |
| `kpdetails.php` | `frame_details.php` |
| `kpclassement.php` | `frame_classement.php` |
| `kpchart.php` | `frame_chart.php` |
| `kpphases.php` | `frame_phases.php` |
| `kpstats.php` | `frame_stats.php` |
| `kpequipes.php` | `frame_equipes.php` |

Ces redirections se retirent avec les `frame_*.php` en phase 7. Vérifier avant la bascule, dans les journaux Apache,
le volume réel de `kp*.php?Css=` (question Q-R3).

## 6. WordPress

### 6.1 Contenus (table `kp_cms_redirect`, existant)

| Ancienne URL | Nouvelle URL |
|---|---|
| `/?p=ID`, permalien `/{année}/{mois}/{slug}/` d'un article repris | `/news/{slug}` (301) |
| Article **non repris** (au-delà des 50 plus récents), `/?p=ID` ou permalien | `/news` (301) |
| `/?page_id=ID`, permalien `/{slug}/` ou `/{parent}/{slug}/` d'une page reprise | `/{slug}` de la page app3 (301) |
| Page **non reprise** (29 pages sur 43 : 12 publiées et 2 brouillons sont repris) | **Page la plus proche** : `/` par défaut ; la table de la mission de reprise peut désigner une page ou une section plus pertinente (Q-R2). Jamais 410 : ces pages ont pu être référencées |
| Permalien de langue anglaise (`/en/{…}/`) | Même table, résultat préfixé `/en` (Q-R4) |

La table est générée par `app:import-wordpress` (idempotent) **et complétée à la main** dans app4 (Admin ›
Éditorial) ; une ligne porte `from_path`, `to_path`, `code` et un compteur `hits` qui sert au suivi (§ 9).
Un `to_path` doit être un chemin interne existant au moment de la bascule (critère RDR-12).

### 6.2 URL structurelles de WordPress (règles fixes)

| Ancienne URL | Décision |
|---|---|
| `/feed/`, `/?feed=rss2`, `/?feed=rss`, `/feed/rss2/`, `/rss/` | 301 → `/rss.xml` |
| `/comments/feed/`, `/{article}/feed/` | **410** (pas de commentaires sur le nouveau site) |
| `/category/{slug}/` (+ `/page/N/`) | 301 → `/news?category={slug}` si la catégorie existe (CMS-13) ; sinon `/news` |
| `/tag/{slug}/`, `/author/{nom}/`, `/page/N/`, `/{année}/`, `/{année}/{mois}/` (archives) | 301 → `/news` |
| `/?s=terme` | 301 → `/search?q=terme` |
| `/sitemap_index.xml`, `/post-sitemap.xml`, `/page-sitemap.xml`, `/category-sitemap.xml`, `/wp-sitemap.xml` | 301 → `/sitemap.xml` |
| `/wp-json/`, `/xmlrpc.php`, `/wp-cron.php` | Maintenus sur Apache jusqu'à la phase 6 (§ 3), puis **410** |
| `/wordpress/wp-content/uploads/…` | Maintenu sur Apache (§ 3) jusqu'à la phase 6 ; **410** ensuite si le fichier n'a pas été rapatrié |
| `/wp-login.php`, `/wp-admin/` | Maintenus sur Apache jusqu'à la phase 6 |

### 6.3 Pourquoi pas de 410 par défaut

Une 410 (« supprimée définitivement ») retire l'URL de l'index plus vite qu'une 404, mais **fait perdre le trafic**
vers un lien encore suivi. Règle de décision, dans l'ordre :

1. un équivalent exact existe → **301** vers lui ;
2. sinon une page ou une section clairement plus proche existe → **301** vers elle (jamais vers `/` pour du contenu
   qui avait une section : `/news`, `/clubs`, `/competitions`…) ;
3. sinon, si l'URL est **technique** (flux de commentaires, endpoint d'API, script de données, média non rapatrié) →
   **410** ;
4. sinon le contenu est inconnu : **404** de la page d'app3 (rendue avec suivi, § 9), et l'URL est ajoutée à la
   table si elle revient souvent.

### 6.4 Fin de vie de WordPress

Pendant la période de sécurité (stratégie § 4.2), WordPress reste joignable **uniquement** par les chemins de
la liste explicite (§ 3). À la phase 6 : arrêt de `dbwp`, la liste Apache perd ses entrées WordPress, et
`/wp-login.php`, `/wp-admin/`, `/wp-json/`, `/xmlrpc.php`, `/wp-cron.php` répondent **410** par règle du middleware.

## 7. `beta.*` et domaine nu

| Cas | Décision |
|---|---|
| `https://beta.kayak-polo.info/{chemin}` | **Pas de redirection, pas de maintien** (décision du 20/11/2026) : le routeur `beta.*` est retiré à la bascule ; les QR codes déjà imprimés vers `beta.*` ne seront plus servis |
| `https://kayak-polo.info/{chemin}` | **301** → `https://www.kayak-polo.info/{chemin}` (forme canonique : `www`, § 8). **Redirigé aujourd'hui** : à vérifier après la bascule, car cette redirection est portée par le routeur par défaut actuel (Q-R5). Hors `/api2` et `/admin2` qui ont déjà leurs routeurs |
| `http://…` | Redirection HTTP → HTTPS par Traefik (déjà en place), **un seul saut** vers l'URL finale |

`PUBLIC_SITE_URL` d'api2 passe au domaine principal **le même jour** que la bascule (les nouveaux PDF n'émettent plus d'URL `beta.*`). `NUXT_PUBLIC_BETA=false` (non-indexation levée, [SITE_PLATFORM.md § 4.2](SITE_PLATFORM.md)). Cette décision remplace la redirection `beta.*` d'une saison prévue par la checklist de la phase 5 et par [PDF_GOTENBERG.md § 8.4](../PDF_GOTENBERG.md) (R15, PDF-DESIGN-08) : à y reporter.
d'URL `beta.*`). `NUXT_PUBLIC_BETA=false` (non-indexation levée, [SITE_PLATFORM.md § 4.2](SITE_PLATFORM.md)).

## 8. Sitemap, canonique et Search Console

- **Sitemap** : `/sitemap.xml` (app3) liste, en français et en anglais, les pages applicatives et les contenus
  publiés ([PAGE_CONTENT.md § 5](PAGE_CONTENT.md), CNT-07). Au moment de la bascule il doit aussi lister les pages
  de données **stables** (`/competitions/{saison}/{code}` des saisons en cours et les 3 dernières, `/history/{groupe}`,
  `/teams/{n}` des équipes actives, `/clubs`) — aujourd'hui `APPLICATION_PATHS` ne contient que les listes. Il ne
  contient **aucune URL redirigée** (jamais une ancienne URL), et au plus 50 000 URL par fichier (index de sitemaps
  au-delà).
- **robots.txt** : autorise l'exploration, référence `/sitemap.xml`, **ne bloque aucune ancienne URL** (une URL
  redirigée doit rester explorable pour que Google voie la 301).
- **Canonique** : `https://www.kayak-polo.info/…` partout (`i18n.baseUrl`) ; `hreflang` FR/EN conservés.
- **Search Console** (propriété `https://www.kayak-polo.info/` + propriété de domaine `kayak-polo.info`) :
  1. avant la bascule, vérifier la propriété de domaine (DNS) et exporter les **pages les plus visitées et les
     requêtes** des 12 derniers mois comme référence ;
  2. soumettre `https://www.kayak-polo.info/sitemap.xml` le jour de la bascule ;
  3. conserver l'ancien sitemap WordPress soumis (`sitemap_index.xml`, qui répond 301) pendant 4 semaines, pour que
     Google découvre les redirections ;
  4. **pas d'outil « Changement d'adresse »** (même domaine, même hôte `www`) ; en revanche, inspecter 10 anciennes
     URL représentatives (`kpclassement.php`, `kpmatchs.php`, un permalien d'article, `/?p=ID`…) et demander
     l'indexation de leur cible ;
  5. suivre pendant 8 semaines les rapports « Pages » (erreurs 404 et « Page avec redirection ») et « Plan du site ».
- **Matomo** (site 8) : annotation « Bascule www → nouveau site » ([checklist M2](../../developer/in-progress/PUBLIC_SITE_ROLLOUT_CHECKLIST.md)).

## 9. Suivi des 404 après la bascule

La page 404 d'app3 envoie à Matomo un titre `404/URL = {chemin}/From = {référent}` (ANA-10,
[FEATURE_ANALYTICS.md](FEATURE_ANALYTICS.md)). Procédure :

| Quand | Action | Seuil |
|---|---|---|
| J (bascule) | Annotations Matomo ; relever en direct le journal Traefik : proportion 301 / 404 / 5xx sur `www` | un pic de 5xx = rollback (runbook) |
| J+1, puis chaque jour pendant 7 jours | Matomo › Comportement › Titres de pages › `404` : **top 20** des chemins | toute URL > 5 visites/jour, ou venant d'un référent externe, reçoit une règle ou une ligne de table |
| Chaque semaine pendant 8 semaines | Search Console › Pages › « Introuvable (404) » et compteur `hits` de `kp_cms_redirect` (lignes jamais touchées = candidates au retrait) | cible : 404 d'anciennes URL < 1 % des pages vues à S+4 |
| S+8 | Bilan : liste des règles ajoutées, URL volontairement en 410, décision de conserver les redirections (au moins **1 an**, usage minimal constaté) | |

Une URL ajoutée au suivi est traitée par la règle de décision du § 6.3 et fait l'objet d'un test (critère RDR-14).

## 10. Critères d'acceptation

Chaque critère est couvert par un test nommé d'après lui (`RDR-nn`), unitaire sur `legacy-paths.ts` sauf mention.

- **RDR-01** — Pour chaque ligne du tableau § 5.1, une requête `GET` sur l'ancienne URL (avec `Saison`, `Compet`, `event`, `Group`, `Equipe`, `clubId` valides) répond **301** avec la `Location` du tableau. Un test paramétré compte autant de cas que de lignes.
- **RDR-02** — `lang=en` ajoute le préfixe `/en` à la cible ; `lang=fr`, absent ou `cn` n'en ajoute pas.
- **RDR-03** — Les paramètres abandonnés (§ 4 règle 5) et inconnus n'apparaissent **pas** dans la `Location` ; `J`, `filtreJour`, `next`, `event`, `Group`, `Equipe` sont convertis selon le tableau § 4.
- **RDR-04** — `Compet=*`, `J=*`, `Round=*` équivalent à l'absence du paramètre ; `Compet=*` avec `Group` mène à `/groups/S/G/…`.
- **RDR-05** — Sans `Saison`, la cible utilise la saison active d'api2 ; api2 en panne → repli `/competitions` (statut 301, pas de 5xx).
- **RDR-06** — Une valeur invalide (code `Compet` hors `[A-Za-z0-9_-]{1,20}`, `Saison` non numérique, `event=abc`, `filtreJour=x`) est ignorée et la requête redirige quand même ; aucune `Location` ne contient `//`, un schéma ou un autre hôte (`Group=//evil.test` reste sur `www`).
- **RDR-07** — Une redirection ne passe jamais par deux sauts : suivre la `Location` d'un cas du § 5.1 donne directement un `200` (test sur les fixtures de page), y compris pour `/teams/N` et `/clubs?club=C`.
- **RDR-08** — `POST`, et les chemins qui ne sont pas dans la table (`/competitions/…`, `/news`), ne sont **pas** redirigés par ce middleware et ne déclenchent aucun appel api2 supplémentaire.
- **RDR-09** — Un `kp*.php` avec `Css=` répond **301** vers le `frame_*.php` du tableau § 5.3 en conservant la requête ; sans `Css`, vers app3.
- **RDR-10** — `/index.php`, `/fr/`, `/en/` → `/` ou `/en` ; `HEAD` répond comme `GET`.
- **RDR-11** — WordPress : `/?p=ID`, `/?page_id=ID` et un permalien connu → 301 vers la cible de `kp_cms_redirect` ; un article non repris → `/news` ; une page non reprise → la cible désignée ou `/` ; `hits` augmente d'un à chaque redirection.
- **RDR-12** — Test de cohérence (CI) : toute ligne de `kp_cms_redirect` pointe un chemin interne existant (article, page publiés ou route d'app3) ; sinon le test échoue et nomme la ligne.
- **RDR-13** — Règles § 6.2 : `/feed/` et `/?feed=rss2` → `/rss.xml` ; `/category/{slug}/` → `/news?category={slug}` ; `/?s=x` → `/search?q=x` ; `/sitemap_index.xml` → `/sitemap.xml` ; `/comments/feed/` → 410 ; `/wp-json/` → 410 **après** la phase 6 seulement (avant : servi par Apache).
- **RDR-14** — Toute règle ajoutée après la bascule (§ 9) arrive avec un test ; la table des cas ne diminue jamais.
- **RDR-15** — Domaine : `kayak-polo.info` → `www` en **301**, chemin et requête conservés, un seul saut depuis HTTP ; `/api2` et `/admin2` ne sont pas affectés ; `beta.*` ne répond plus (aucun routeur). Vérifié en préprod avant la production.
- **RDR-16** — Parité de bascule (préprod, avant la prod) : un script rejoue **la liste des 200 URL legacy et WordPress les plus fréquentes** extraite des journaux Apache des 12 derniers mois ; chacune finit en `200` (ou 410 / 404 listés et validés), **aucune en 5xx ni en boucle**. Le rapport est joint à la PR de la bascule.
- **RDR-17** — Les chemins du § 3 (`/frame_*.php`, `/Pdf*.php`, `/kpqr.php`, `/admin/`…) répondent toujours depuis Apache après l'inversion du routage Traefik, et **aucun `kp*.php` n'y est routé**.
- **RDR-20** — `kptv.php`, `kptvscenario.php`, `kpadmin.php`, `tv.php`, `feuillemarque.php` et `gamereport.php` répondent **410** sur `www` après la bascule, et ne figurent plus dans la liste Apache du § 3.
- **RDR-18** — `/sitemap.xml` ne contient aucune ancienne URL ni aucune URL en 3xx/4xx (test sur un échantillon rendu par l'api2 de fixtures) ; `robots.txt` référence le sitemap et n'interdit aucune ancienne URL.
- **RDR-19** — Suivi : la page 404 d'app3 émet le titre `404/URL = …/From = …` (ANA-10) ; le tableau de bord Matomo « 404 » existe avant la bascule (checklist M3).

## 11. Rollout

À reporter dans la checklist de la phase 5 (`PUBLIC_SITE_ROLLOUT_CHECKLIST.md`) au moment de son écriture :

0. **P1 — prérequis** : le scoring (console app4, [PAGE_SCORING.md](../PAGE_SCORING.md)) est publié aux profils adéquats et le panneau TV d'app4 (`/tv`) est utilisable ; sans cela les 410 de § 5.2 privent les opérateurs de leurs outils.
1. 📊 Extraire des journaux Apache (12 mois) les 200 URL les plus fréquentes et le volume de `kp*.php?Css=` (RDR-16, Q-R3).
2. Livrer `legacy-paths.ts` et ses tests, **sur `beta.*` d'abord** : `beta.*/kpmatchs.php?…` redirige vers les pages d'app3 de `beta.*`, ce qui permet de rejouer la table sans toucher à `www` (les liens de repli du menu ciblent le legacy en URL absolue et ne passent pas par ce middleware).
3. Préprod : inverser le routage Traefik, rejouer le script RDR-16, valider les domaines (RDR-15).
4. Prod, **le jour de la bascule** : routage Traefik, `NUXT_PUBLIC_BETA=false`, `PUBLIC_SITE_URL`, sitemap, Search Console, annotations Matomo.
5. Suivi des 404 (§ 9) ; rollback = restaurer le routage Traefik précédent (aucun changement de données).

## 12. Hors périmètre

- Remplacement des `frame_*.php` (`/embed/…`, thèmes) : phase 7.
- Remplacement des PDF publics et de leurs QR codes : [PDF_GOTENBERG.md](../PDF_GOTENBERG.md).
- Retrait des scripts de données du legacy et de WordPress : phase 6 (le comportement final est décidé ici, § 5.2 et § 6).
- Redirection des liens vers app2 (`app.kayak-polo.info`), qui ne change pas.

## 13. Questions ouvertes

| # | Question | Proposition par défaut |
|---|---|---|
| Q-R1 | La **saison active** (règle 2) : l'API publique l'expose-t-elle déjà de façon stable, ou faut-il l'ajouter à api2 ? | Réutiliser la source de `/competitions` ; sinon ajouter un `GET /seasons/active` |
| Q-R2 | ✅ Pages WordPress non reprises (29 sur 43) : `/` (20/11/2026), sans table de pages proches à valider | — |
| Q-R3 | Des tiers intègrent-ils `kp*.php?Css=` en iframe ? Combien de requêtes ? | Rediriger vers `frame_*.php` (§ 5.3) ; à confirmer par les journaux |
| Q-R4 | Schéma d'URL anglais de WordPress (WP Multilang) : `/en/…` ou `?lang=en` ? | `/en/` + table de redirection existante, à vérifier sur la base `dbwp` |
| Q-R5 | ✅ `kayak-polo.info` est aujourd'hui redirigé vers `www` (20/11/2026). **À vérifier après la bascule** : la redirection survit-elle à l'inversion du routage Traefik ? | Test RDR-15 en préprod, puis contrôle en production le jour J |
| Q-R6 | ✅ Durée de maintien : `beta.*` **non maintenu** dès la bascule (QR codes déjà imprimés perdus, accepté). Autres redirections : 1 an, puis revue sur les `hits` | — |
| Q-R7 | ✅ Aucun consommateur externe des scripts de données (`json-events.php`, `searchClubs.php`…) | Ils ne restent sur Apache que pour l'admin et les écrans, puis 410 en phase 6 |
| Q-R8 | Il ne devrait plus rester d'outil interne réellement utile sur Apache (§ 3, § 5.2) : à vérifier **un par un** (`kpadmin.php`, `kptv.php`, `kptvscenario.php`, `tv.php`, `scenario.php`, `feuillemarque.php`, `gamereport.php`…). Ceux qui sont inutiles sont retirés de la liste et reçoivent un 410 ; les autres reçoivent `X-Robots-Tag: noindex` | Inventaire à faire avant la rédaction de la checklist de phase 5 |
