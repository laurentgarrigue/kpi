# Template général du site public

**Phase** : 1 — **Statut** : ✅ Validée (08/10/2026, retours intégrés) — **S'applique à** : toutes les pages d'app3 (layout `default` et page d'erreur)

## 1. Objectif

Définir l'enveloppe commune à toutes les pages : en-tête, bandeau beta, navigation, zone de contenu, pied
de page, identité visuelle, accessibilité et métadonnées par défaut. Les pages ne décrivent ensuite que leur
contenu.

## 2. Structure

```
┌──────────────────────────────────────────────────────────────┐
│ [lien d'évitement « Aller au contenu »] (visible au focus)    │
├──────────────────────────────────────────────────────────────┤
│ Bandeau beta (si NUXT_PUBLIC_BETA)                            │  bleu clair #69b9e6, texte #1e1e1c
├──────────────────────────────────────────────────────────────┤
│ EN-TÊTE  [logo FFCK] kayak-polo.info  [⚙ Administration] [FR | EN] │  fond blanc
│          Commission Nationale d'Activité Kayak-Polo           │
├──────────────────────────────────────────────────────────────┤
│ NAVIGATION PRINCIPALE (cf. SITE_NAVIGATION.md)        [☰]     │  fond marine #20265b, texte blanc
├──────────────────────────────────────────────────────────────┤
│ <main id="content">                                           │  fond blanc, largeur max 80rem
│   contenu de la page                                          │
├──────────────────────────────────────────────────────────────┤
│ PIED DE PAGE  CNA Kayak-Polo · Suivre · Partenaire · Liens    │  fond marine #20265b, texte blanc
│               © FFCK — version                                │
└──────────────────────────────────────────────────────────────┘
```

### 2.1 Bandeau beta
- Affiché quand `NUXT_PUBLIC_BETA` vaut `true`, sur toutes les pages, non masquable.
- Texte : « Version bêta du nouveau site kayak-polo.info. Le site actuel reste disponible : *[accéder au site actuel]* »
  (FR/EN). Le lien pointe vers `NUXT_PUBLIC_LEGACY_BASE_URL`.
- Rôle `region` avec un libellé accessible.

### 2.2 En-tête
- **Logo FFCK** couleur (fichier existant `LOGO_FFCK.png`, jamais modifié), sur fond blanc conformément à la
  charte, hauteur 48 px (≥ 25 px : version complète autorisée), avec son texte alternatif. Lien vers l'accueil.
- **Nom du site** « kayak-polo.info » en Agency FB, couleur primaire `#357b9c` ; **sous-titre**
  « Commission Nationale d'Activité Kayak-Polo » en Raleway (masqué sous 640 px).
- **Lien « Administration »** à droite, avant le sélecteur de langue : icône engrenage + libellé (libellé
  masqué sous 640 px, conservé pour les lecteurs d'écran), vers l'administration **`{legacy}/admin2/`** (app4).
  Style discret (texte secondaire) mais visible sans défiler. Jamais `AdminChoice.php`.
- **Sélecteur de langue** FR / EN à droite (cf. SITE_NAVIGATION.md § 5).

### 2.3 Zone de contenu
- `<main id="content">`, conteneur centré, largeur maximale 80 rem, marges latérales 1 rem (mobile) à 2 rem.
- Chaque page fournit **un seul `<h1>`**, en Agency FB.
- **En-têtes de colonnes figés** : sur toutes les pages, les cellules `thead th` des tableaux restent visibles en
  haut de la fenêtre pendant le défilement (`position: sticky`, comme les `.sticky-thead` d'app4). Les conteneurs à
  défilement horizontal des tableaux larges sont limités aux petits écrans (`lg:overflow-visible`), sinon le
  figement ne fonctionne pas.
- **Flèches de défilement** (comme app4) : deux boutons flottants en bas à droite, « Retour en haut » (affiché
  après 300 px de défilement) et « Aller en bas » (masqué à moins de 10 px du bas) ; libellés accessibles,
  défilement doux sauf `prefers-reduced-motion`, masqués à l'impression.
- **Favicon** : celui d'app4 (`/favicon.png`, logo KPI), servi par app3.

### 2.4 Pied de page
Quatre colonnes (empilées sur mobile) sur fond marine `#20265b`, texte blanc :

| Colonne | Contenu |
|---|---|
| **Kayak-polo** | Logo CNA Kayak-Polo (fichier existant, sur pastille blanche), « Commission Nationale d'Activité Kayak-Polo », lien vers le site de la FFCK (`https://www.ffck.org`) |
| **Suivre** | Page Facebook officielle (`https://www.facebook.com/ffckkp/`) ; « Suivre un événement en direct » → app2 (`NUXT_PUBLIC_APP2_BASE_URL`) |
| **Partenaire** | KIP Sport (logo existant sur pastille blanche) → `https://www.facebook.com/KIPsport` |
| **Liens** | Site actuel (legacy), Administration (`{legacy}/admin2/`) |

Ligne inférieure : « © {année} FFCK — Commission Nationale d'Activité Kayak-Polo » et numéro de version d'app3.
### 2.6 Ouverture des liens externes
- **Tous les liens vers app2** (menu « En direct », pied de page, accueil, feuilles de match, pages « Suivre en
  direct ») s'ouvrent **dans un nouvel onglet** (`target="_blank"`, `rel="noopener"`), avec la mention
  accessible « (nouvel onglet) ». Un composant unique (`SiteExternalLink`) porte cette règle.
- Facebook et KIP Sport : nouvel onglet également.
- Site actuel (legacy) et administration : même onglet.

### 2.5 Page d'erreur
Même enveloppe. Pour une 404 : titre « Page introuvable », lien vers l'accueil et lien vers le site actuel.
Pour une autre erreur : « Une erreur est survenue », même liens. Aucune trace technique affichée.

## 3. Identité visuelle

Jetons définis dans `kpi-layer` d'après la charte FFCK, univers Compétition
(cf. stratégie § 10). **Aucune couleur hexadécimale dans les composants** : uniquement les jetons.

| Jeton | Valeur | Usage |
|---|---|---|
| `primary` | `#357b9c` | liens, boutons, titres de section |
| `primary-light` | `#69b9e6` | aplats, bandeau beta (texte noir uniquement) |
| `accent` | `#c94a4c` (survol `#882831`) | live, alertes, appels à l'action |
| `ink` | `#1e1e1c` | texte courant |
| `line` | `#c6c7c7` | bordures, séparateurs |
| `gold` / `green` | `#e9b410`·`#9a7208` / `#209452`·`#186a32` | médailles, victoires, qualifiés (phase 2) |
| `navy` | `#20265b` | barre de navigation, pied de page |

- **Typographie** : titres en **Agency FB** (licence FFCK, fichier d'app4 repris dans le layer), texte en
  **Raleway** auto-hébergée (aucun appel à un service tiers). Chiffres tabulaires pour scores et classements.
- **Mode sombre** : hors phase 1 (le site est en thème clair). Les jetons sont nommés pour l'accueillir plus tard.
- **Responsive** : mobile d'abord ; navigation repliée sous `lg` (1024 px).

## 4. Accessibilité

- Repères : `header`, `nav` (libellé « Navigation principale »), `main`, `footer`.
- Lien d'évitement vers `#content`, premier élément focusable.
- Contraste AA pour tout texte (jetons choisis en conséquence ; `primary-light` jamais en texte sur blanc).
- Focus visible sur tous les éléments interactifs.
- Attribut `lang` du document égal à la langue courante (`fr-FR`, `en-GB`).

## 5. Métadonnées par défaut (SEO)

- Titre : `{titre de la page} — kayak-polo.info` pour toutes les pages, accueil compris (« Le kayak-polo, en France et à l'international — kayak-polo.info »).
- Description par défaut (FR/EN) : « Résultats, classements, calendriers et actualités du kayak-polo, en France
  et à l'international. »
- `og:site_name` = `kayak-polo.info`, `og:locale`, `og:type=website`, `og:image` = logo CNA Kayak-Polo par défaut.
- URL canonique = `NUXT_PUBLIC_I18N_BASE_URL` + chemin, et liens `hreflang` `fr`, `en`, `x-default`.
- Balise `robots` selon SITE_PLATFORM.md § 4.2.

## 6. Critères d'acceptation

- **LAY-01** — Le layout rend, dans l'ordre : lien d'évitement, (bandeau beta), `header`, `nav`, `main#content`, `footer`.
- **LAY-02** — Le bandeau beta est présent si et seulement si `NUXT_PUBLIC_BETA` vaut `true`, et son lien pointe vers l'URL legacy.
- **LAY-03** — L'en-tête affiche le logo FFCK avec un texte alternatif et un lien vers l'accueil de la langue courante, ainsi que le lien « Administration » vers `{legacy}/admin2/`.
- **LAY-04** — Le pied de page contient les liens Facebook FFCK, app2, KIP Sport, site actuel et Administration (`{legacy}/admin2/`), et l'année courante.
- **LAY-09** — Tout lien vers app2 porte `target="_blank"`, `rel="noopener"` et la mention accessible « (nouvel onglet) ».
- **LAY-05** — Le titre de document suit le gabarit `{titre} — kayak-polo.info`.
- **LAY-06** — Le document porte `lang="fr-FR"` sur les pages françaises et `lang="en-GB"` sous `/en`, ainsi que les liens `hreflang` et l'URL canonique.
- **LAY-07** — Une URL inconnue renvoie un statut 404 avec la page d'erreur dans l'enveloppe du site.
- **LAY-10** — Les pages déclarent le favicon d'app4 (`<link rel="icon" href="/favicon.png">`), servi par app3.
- **LAY-11** — Le layout affiche les flèches « Retour en haut » / « Aller en bas » selon la position de défilement ; elles font défiler la page.
- **LAY-12** — Les en-têtes de colonnes des tableaux restent visibles au défilement ; aucun tableau n'est dans un conteneur à défilement horizontal sur grand écran.
- **LAY-08** — Aucun composant d'app3 ne contient de couleur hexadécimale (contrôle en revue / lint).

## 7. Hors périmètre / questions ouvertes

- **Mentions légales / confidentialité** : lien à ajouter au pied de page dès que la page existe (module
  éditorial, phase 4a). D'ici là, aucune page n'est inventée.
- Recherche globale dans l'en-tête : phase 3 (FEATURE_SEARCH.md).
- Fil d'Ariane : introduit avec les pages de la phase 2.
