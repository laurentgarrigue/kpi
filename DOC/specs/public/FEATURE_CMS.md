# Module éditorial (CMS natif) — api2 + app4

**Phase** : 4a — **Statut** : ✅ Validée (11/10/2026, décisions Q-P4-1 à 5) — 🚀 Livrée (beta) — **Remplace** : WordPress (articles, pages, menus,
médiathèque, Rank Math, WP Multilang, Sassy Social Share, Enable Media Replace, flux RSS)
**Pages publiques associées** : [PAGE_NEWS.md](PAGE_NEWS.md) (actualités), [PAGE_CONTENT.md](PAGE_CONTENT.md)
(pages éditoriales, menu, accueil) — **Stratégie** : § 5.1, 5.2, 5.4, 7 de
[PUBLIC_SITE_REDESIGN_STRATEGY.md](../../developer/in-progress/PUBLIC_SITE_REDESIGN_STRATEGY.md)

## 1. Objectif

Permettre aux **5 rédacteurs** (comptes KPI existants) de publier articles et pages, gérer médias, menu et blocs
d'accueil depuis l'administration **app4**, sans WordPress. Le contenu est stocké dans la base KPI, servi par
api2, affiché par app3. Les ~50 derniers articles et les pages utiles de WordPress sont repris.

## 2. Périmètre

| Élément | Inclus (MVP) | Exclu |
|---|---|---|
| Articles | titre, chapô, corps riche, image de une, date de publication programmable, brouillon / publié / archivé, signature libre, liens vers compétitions / événements / clubs, **catégories** (une ou plusieurs, liste fixe § 3.1), SEO | commentaires, révisions multiples (une seule version + journal), mots clés libres (étiquettes) |
| Pages | slug, titre, corps riche, page parente, SEO | modèles de page multiples |
| Menu éditorial | entrées vers pages, articles, URL ; une entrée de premier niveau « Le kayak-polo » | édition des entrées « données » (Calendrier, Compétitions… restent dans `MAIN_MENU`) |
| Médias | images (JPEG, PNG, WebP), PDF ; variantes redimensionnées ; texte alternatif FR/EN ; remplacement de fichier à URL constante ; albums (galeries) | vidéo hébergée (on intègre YouTube / Vimeo par lien, chargé au clic) |
| Accueil | blocs : à la une, dernières actualités, bandeau d'alerte, partenaires (+ prochains / récents événements existants) | constructeur de page libre |
| Sorties | RSS 2.0, `sitemap.xml` (contenus éditoriaux), Open Graph / schema.org `NewsArticle` | newsletter |
| Multilingue | champs FR / EN par contenu, repli sur FR si EN vide | autres langues |

## 3. Modèle de données (base KPI, MariaDB)

Création par le script daté `SQL/migrations/2026-10-11_public_site_cms_forms.sql` (convention du dépôt, appliqué par
`make db_migrate file=…`), tables `kp_cms_*` en
InnoDB / `utf8mb4`. Pas de dépendance à la base WordPress après l'import.

| Table | Colonnes principales |
|---|---|
| `kp_cms_article` | `id`, `slug` (unique), `title_fr`, `title_en`, `lead_fr`, `lead_en`, `body_fr`, `body_en` (HTML assaini), `cover_media_id`, `status` (`draft`\|`published`\|`archived`), `published_at` (programmable), `signature` (texte libre affiché, Q-P4-3), `author_code` (→ `kp_user.Code`, interne : jamais exposé), `seo_title_*`, `seo_description_*` (l'image Open Graph est l'image de une), `legacy_wp_id` (import), `created_at`, `updated_at`, `updated_by` |
| `kp_cms_article_link` | `article_id`, `kind` (`competition`\|`event`\|`club`), `ref` (`{saison}/{code}`, id d'événement, code club) |
| `kp_cms_category`, `kp_cms_article_category` | catégories (`slug`, `label_fr`, `label_en`, `position`) ; un article en a **une ou plusieurs** (§ 3.1) |
| `kp_cms_page` | `id`, `slug` (unique, hors segments réservés § 6), `parent_id`, `title_*`, `body_*`, `status`, `seo_*`, `legacy_wp_id`, horodatages |
| `kp_cms_menu_item` | `id`, `parent_id`, `position`, `label_fr`, `label_en`, `target_kind` (`page`\|`article`\|`url`), `target_ref` |
| `kp_cms_media` | `id`, `path` (relatif à `HOST_MEDIA_PATH/content/`), `mime`, `width`, `height`, `size`, `alt_fr`, `alt_en`, `credit`, `album_id`, `uploaded_by`, horodatages |
| `kp_cms_album` | `id`, `slug`, `title_*`, `cover_media_id` |
| `kp_cms_home_block` | `id`, `kind` (`featured`\|`latest_news`\|`alert`\|`partners`), `position`, `enabled`, `config` (JSON : ids d'articles, texte d'alerte FR/EN, niveau, dates d'affichage, partenaires) |
| `kp_cms_redirect` | `from_path` (unique), `to_path`, `code` (301), `hits` — permaliens WordPress et articles non repris (§ 8) |

### 3.1 Catégories

Liste **fixe**, créée par le script SQL (modifiable seulement par script, pas d'écran) ; un article publié en
a au moins une. Ce ne sont **pas** des mots clés libres (les « étiquettes » du brouillon sont abandonnées) :

| `slug` | Libellé FR | Libellé EN |
|---|---|---|
| `equipes-de-france` | Équipes de France | French national teams |
| `competitions-nationales` | Compétitions nationales | National competitions |
| `competitions-regionales-open-internationales` | Compétitions régionales, open et internationales | Regional, open and international competitions |
| `reglements-vie-federale` | Règlements et vie fédérale | Rules and federation life |

Le visiteur filtre les actualités par catégorie (PAGE_NEWS.md § 2.1).

### 3.2 Corps des contenus

Le **corps** est stocké en HTML **assaini côté api2** (liste blanche : titres h2–h4, paragraphes, listes, liens,
gras / italique, citations, tableaux simples, images `kp_cms_media`, intégrations vidéo YouTube / Vimeo
normalisées) par un filtre **par liste blanche écrit sur `ext-dom`** (`App\Cms\HtmlSanitizer`) : aucune dépendance
ajoutée (symfony/html-sanitizer tirait league/uri et n'apportait rien de plus ici). Les liens internes au site sont stockés en
chemins relatifs (`/competitions/…`), jamais en URL absolues.

## 4. Droits

- Nouvelle **capacité « Rédacteur »** (✅ Q-P4-1), indépendante du niveau de profil (un rédacteur peut être niveau 8) :
  table `kp_user_capability (user_code, capability)` avec `capability = 'editor'` ; exposée à app4 dans
  `user.capabilities` (`/auth/login`, `/auth/me`, `/auth/refresh`).
- Profils **1 et 2** : toutes les actions éditoriales sans capacité explicite (cohérent avec le bypass de
  DROITS_PAR_PROFIL.md).
- Matrice reportée dans [DROITS_PAR_PROFIL.md](../DROITS_PAR_PROFIL.md) § Éditorial :

| Action | Rédacteur | Profils 1–2 | Autres |
|---|---|---|---|
| Créer / modifier articles, pages, médias, albums | ✅ | ✅ | ❌ |
| Publier / dépublier, programmer | ✅ | ✅ | ❌ |
| Archiver (corbeille : statut `archived`) | ✅ | ✅ | ❌ |
| Supprimer définitivement | ❌ | ✅ | ❌ |
| Formulaires (FEATURE_FORMS.md) | ✅ | ✅ | ❌ |
| Menu éditorial, blocs d'accueil, redirections | ❌ | ✅ | ❌ |
| Attribuer la capacité Rédacteur (page Utilisateurs) | ❌ | ✅ | ❌ |

Toutes les écritures passent par l'authentification JWT d'app4 et sont journalisées dans le **journal
d'administration existant** (`kp_journal`, page Journal d'app4), comme le reste de l'administration.

## 5. api2

### 5.1 Public (lecture, tag OpenAPI « 7. Site public »)

| Endpoint | Réponse |
|---|---|
| `GET /news?page=&category=&competition=&event=&club=` | articles **publiés** (`published_at` ≤ maintenant), 12 par page, du plus récent au plus ancien : `{ items: [{ slug, title, lead, cover, published_at, categories }], page, pages }` |
| `GET /news/categories` | les 4 catégories `{ slug, label }` dans l'ordre |
| `GET /news/{slug}` | article publié complet : `{ slug, title, lead, body, cover, published_at, updated_at, signature, categories, links: [{ kind, label, path }], seo }` ; brouillon, programmé ou inconnu → 404 |
| `GET /pages/{slug}` | page publiée : `{ slug, title, body, parent, children, seo }` |
| `GET /menu` | arbre du menu éditorial (libellés, chemins) |
| `GET /home` | blocs d'accueil actifs, dans l'ordre, avec leur contenu résolu (articles à la une, alerte en cours) |
| `GET /albums/{slug}` | album : titre, médias (variantes, texte alternatif) |
| `GET /rss.xml` | RSS 2.0 des 20 derniers articles (FR ; `?lang=en` pour l'anglais) |
| `GET /sitemap/content.xml` | URL des articles et pages publiés (agrégé par le sitemap d'app3) |
| `GET /redirect?path=` | redirection connue pour un chemin (`{ to, code }` ou 404), utilisée par le middleware d'app3 |

Langue : paramètre `lang=fr|en` (défaut `fr`), champs EN vides → valeurs FR. **Aucune donnée personnelle** : l'article
porte une **signature libre** saisie par le rédacteur (✅ Q-P4-3, ex. « La CNA Kayak-Polo »), le compte auteur n'est
jamais exposé. `Cache-Control: public, max-age=60`.
Recherche globale (FEATURE_SEARCH.md § 2.5) : nouvelles catégories `news` et `pages` (titre, chapô), 8 résultats.

### 5.2 Administration (app4, authentifié)

CRUD `/admin/cms/articles`, `/admin/cms/pages`, `/admin/cms/menu`, `/admin/cms/media` (envoi multipart, 20 Mo
max, remplacement `PUT …/file`), `/admin/cms/albums`, `/admin/cms/home-blocks`, `/admin/cms/redirects`,
`GET /admin/cms/categories`, `GET|PUT /admin/cms/editors` (capacité Rédacteur). Prévisualisation : `GET /admin/cms/articles/{id}/preview-token` →
jeton signé à durée courte (15 min) accepté par `GET /news/{slug}?preview=…` (non mis en cache).

### 5.3 Médias

- Stockés dans `HOST_MEDIA_PATH/content/{année}/{mois}/{nom-normalisé}` (répertoire déjà réservé, sauvegardé par
  restic : [MEDIA_STORAGE.md](../../developer/infrastructure/MEDIA_STORAGE.md)), servis sous `/media/content/…`.
- Variantes générées à l'envoi avec **GD** (déjà dans `Dockerfile.api2`, à compiler en plus `--with-webp` : seul
  changement Docker de la phase) : 320, 768, 1280, 1920 px de large en WebP + original ; métadonnées EXIF supprimées (vie privée : géolocalisation).
- Types et taille vérifiés côté serveur (type MIME réel, pas l'extension) ; SVG refusé (risque XSS).
- **Albums « Articles » et « Pages »** (13/10/2026) : les médias utilisés par un article (image de une, corps) ou une
  page (corps) y sont rangés à l'enregistrement, qu'ils viennent de la reprise WordPress ou de l'éditeur d'app4 ; un
  média déjà rangé dans un autre album (galerie) ne bouge pas. Les deux albums sont créés au premier besoin.
- Remplacement : même `id`, même URL, nouvelles variantes ; cache-busting par `?v={updated_at}`.

### 5.4 Invalidation du cache d'app3

À chaque publication / modification / dépublication, api2 appelle `POST {app3}/_internal/purge` (réseau Docker
interne, secret partagé `APP3_PURGE_TOKEN`) avec les chemins touchés (`/`, `/news`, `/news/{slug}`, pages liées).
Sans réponse, le cache expire seul (`maxAge` 60 s) : la publication n'échoue jamais à cause de la purge.

## 6. app4 — écrans d'édition

Nouvelle rubrique **« Éditorial »** dans le menu **Admin** d'app4 (à côté de Référentiels, Opérations, Live, Droits),
visible avec la capacité Rédacteur ou les profils 1–2 ; écrans détaillés dans
[PAGE_CMS.md](../PAGE_CMS.md) (specs app4) :

- **Articles** : liste filtrable (statut, catégorie), éditeur **TipTap** (✅ Q-P4-2) (Vue 3, sans service tiers) avec
  barre réduite (titres, gras, italique, listes, lien, citation, image depuis la médiathèque, tableau simple,
  vidéo par URL), onglets FR / EN, panneau latéral (statut, date, image de une, liens compétition / événement /
  club par autocomplétion, catégories (cases à cocher, au moins une), signature, SEO avec aperçu Google / Open Graph), bouton **Prévisualiser** (onglet
  `beta.*` avec jeton) ; sauvegarde automatique du brouillon local (navigateur) toutes les 30 s.
- **Pages** : idem sans date ni liens ; choix de la page parente ; slug vérifié (unique, non réservé :
  `news`, `competitions`, `calendar`, `events`, `groups`, `history`, `teams`, `clubs`, `search`, `forms`,
  `media`, `img`, `api2`, `admin2`, `en`, `healthz`).
- **Médiathèque** : grille, envoi par glisser-déposer, texte alternatif obligatoire pour les images insérées,
  albums, « Remplacer le fichier ».
- **Menu**, **Accueil**, **Redirections** : listes ordonnables (glisser-déposer + boutons monter / descendre
  accessibles au clavier).

## 7. Sécurité

- HTML assaini à l'écriture **et** rendu par app3 via `v-html` uniquement sur ce HTML assaini (test dédié).
- CSRF sans objet (JWT en en-tête) ; limitation de débit sur l'envoi de médias (60 / heure / utilisateur).
- Les intégrations vidéo ne chargent rien avant le clic (pas d'appel tiers, comme la carte des clubs).

## 8. Reprise WordPress

Commande `bin/console app:import-wordpress --articles=50 --pages=<ids> [--dry-run]` lisant la base `dbwp`
(connexion Doctrine dédiée, lecture seule) :

1. articles `publish` les plus récents (`post_date` décroissant) **avec leurs catégories** (13/10/2026) : chaque
   catégorie WordPress est rattachée à la catégorie du site de même nom ou de même slug (accents et casse ignorés),
   ou à celle indiquée par `--category-map=slugwp=categorie,…` ; « Non classé » et les étiquettes sont ignorées ; une
   catégorie sans équivalent est signalée dans le rapport (l'article reste à classer) ; pages listées
   (✅ Q-P4-4) : `2849, 2711, 2286, 2270, 2267, 1881, 1723, 1591, 1581, 1480, 1049, 987` importées **publiées**,
   `313` (à rafraîchir, moderniser) et `1579` (à réactualiser) importées en **brouillon** avec une note dans le
   rapport ; champs WP Multilang éclatés en FR / EN ; Rank Math → champs SEO ;
2. HTML converti (blocs « classic » → liste blanche § 3.2), images, documents et galeries **des contenus repris
   seulement** rapatriés dans la médiathèque, albums **« Articles »** et **« Pages »** (§ 5.3) — Q-P4-5, sans réponse :
   valeur par défaut retenue ; liens internes réécrits vers les nouvelles URL. L'adresse des médias vient des réglages
   WordPress (`siteurl`, `upload_url_path`) : sur kayak-polo.info, WordPress est installé sous `/wordpress`, les
   médias sont donc sous `/wordpress/wp-content/uploads/`. Les fichiers sont lus sur disque : `--uploads-dir`, sinon
   `WORDPRESS_UPLOADS_DIR` (`HOST_WORDPRESS_PATH/wp-content/uploads` monté en lecture seule dans api2 par les compose),
   sinon téléchargés ; un média introuvable garde son ancienne adresse et est signalé ;
3. **idempotente** (`legacy_wp_id`) : relancée, elle met à jour sans dupliquer ;
4. **rapport** (fichier + sortie) : shortcodes non convertis (`[ninja_form]`, `[table]`…), liens cassés, médias
   introuvables ;
5. table `kp_cms_redirect` : permaliens repris (`/?p=123`, `/{année}/{mois}/{slug}/`) → `/news/{slug}` ;
   articles non repris → `/news` (301). Utilisée à la bascule (phase 5, `SITE_REDIRECTS.md`).

## 9. Critères d'acceptation

- **CMS-01** — Un article `published` dont `published_at` est passé apparaît dans `GET /news` ; brouillon,
  programmé dans le futur ou archivé → absent de la liste et 404 sur `GET /news/{slug}`.
- **CMS-02** — Le HTML enregistré est assaini (script, `on*=`, `style`, iframe hors YouTube / Vimeo, `javascript:`
  supprimés) ; un test couvre chaque cas.
- **CMS-03** — Sans capacité Rédacteur ni profil 1–2, toute écriture `/admin/cms/*` → 403 ; avec, elle est
  journalisée dans `kp_journal`.
- **CMS-04** — Un média envoyé est vérifié (type réel, taille), privé de ses métadonnées EXIF, décliné en
  variantes WebP ; un SVG est refusé ; « Remplacer » garde l'URL.
- **CMS-05** — Les champs EN vides sont remplacés par les champs FR dans les réponses `lang=en`.
- **CMS-06** — Un slug de page réservé ou déjà pris est refusé (400 avec message).
- **CMS-07** — La publication déclenche la purge du cache d'app3 ; une purge en échec n'empêche pas la publication.
- **CMS-08** — `GET /rss.xml` est un RSS 2.0 valide des 20 derniers articles publiés.
- **CMS-09** — `app:import-wordpress --dry-run` produit le rapport sans écrire ; l'import réel est idempotent et
  crée les redirections des permaliens.
- **CMS-10** — Chaque DTO public a un test listant ses champs ; aucun compte utilisateur n'est exposé (signature libre).
- **CMS-13** — Un article a une ou plusieurs des 4 catégories ; `GET /news?category=` ne renvoie que les articles
  de la catégorie ; catégorie inconnue → 400.
- **CMS-11** — La recherche globale renvoie les articles et pages publiés (catégories `news`, `pages`).
- **CMS-14** — Les médias d'un article ou d'une page (repris de WordPress ou insérés dans app4) sont rangés dans
  l'album « Articles » ou « Pages » s'ils n'ont pas d'album ; la reprise conserve les catégories des articles.
- **CMS-12** — Un jeton de prévisualisation expiré ou falsifié est refusé ; une prévisualisation n'est jamais mise
  en cache.

## 10. Décisions (11/10/2026)

- **Q-P4-1 — Droit Rédacteur.** ✅ Capacité séparée `editor` (`kp_user_capability`), attribuée par les profils 1–2.
- **Q-P4-2 — Éditeur riche.** ✅ TipTap.
- **Q-P4-3 — Auteur affiché.** ✅ Signature libre par article.
- **Q-P4-4 — Pages à reprendre.** ✅ 14 pages (§ 8), dont 313 et 1579 en brouillon à retravailler.
- **Q-P4-5 — Galeries existantes.** Sans réponse : seuls les médias des contenus repris sont rapatriés (défaut).
- Catégories : liste fixe de 4 (§ 3.1) à la place des étiquettes libres.
