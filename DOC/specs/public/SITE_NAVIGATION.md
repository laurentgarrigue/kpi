# Navigation : menus et sous-menus

**Phase** : 1 (structure complète, entrées activées au fil des phases) — **Statut** : 📝 Proposée
**Remplace** : menu public de `commun/MyPage.php` (`kpmain_menu.tpl`) et menu WordPress

## 1. Objectif

Donner au site une navigation **stable dès la phase 1** : la structure cible est en place, et chaque entrée
pointe soit vers la nouvelle page (si elle est livrée), soit vers la page équivalente du site actuel. Le beta
est ainsi utilisable à chaque incrément, et livrer une page revient à **changer un indicateur dans la
configuration** (principe ouvert/fermé), sans toucher aux composants.

## 2. Structure

Navigation principale (ordre d'affichage) :

| # | Entrée | Sous-menu | Route cible | Repli legacy (tant que non livrée) | Livrée en |
|---|---|---|---|---|---|
| 1 | **Accueil** | — | `/` | — | phase 1 |
| 2 | **Actualités** | — | `/news` | `/` (accueil WordPress) | phase 4a |
| 3 | **Calendrier** | — | `/calendar` | `/kpcalendrier.php` | phase 3 |
| 4 | **Compétitions** | Compétitions et résultats | `/competitions` | `/kpclassements.php` | phase 2 |
| | | Historique et palmarès | `/history` | `/kphistorique.php` | phase 3 |
| 5 | **Équipes et clubs** | Équipes | `/teams` | `/kpequipes.php` | phase 3 |
| | | Clubs | `/clubs` | `/kpclubs.php` | phase 3 |
| 6 | **En direct** | — | app2 (`NUXT_PUBLIC_APP2_BASE_URL`) | — | phase 1 (lien externe) |

Choix assumés par rapport au menu actuel :
- **« Matchs » et « Classements »** ne sont plus des entrées de premier niveau. Dans le legacy, ces pages
  exigent déjà de choisir une compétition. Elles deviennent les onglets de la page compétition
  (spec `PAGE_COMPETITION.md`, phase 2), atteinte par « Compétitions et résultats ».
- **« Administration »** passe dans le pied de page (public non concerné), cf. SITE_LAYOUT.md § 2.4.
- **Pages éditoriales** (règlements, présentation…) : une entrée « Le kayak-polo » sera ajoutée en phase 4a,
  alimentée par le module éditorial. Aucune entrée n'est inventée d'ici là.
- **`/teams`** (recherche d'équipe) est ajoutée au plan d'URL, en complément de `/teams/{id}`.

La navigation **contextuelle** d'une compétition (onglets games, pitches, info, progress, phases, ranking,
stats) relève de `PAGE_COMPETITION.md`.

## 3. Modèle de configuration

Une seule source : la constante typée `MAIN_MENU` d'app3. Les composants ne contiennent aucune entrée en dur.

```ts
interface MenuLink {
  id: string            // identifiant stable, sert aussi de clé i18n : nav.<id>
  to?: string           // route app3 (sans préfixe de langue)
  legacyPath?: string   // page équivalente du site actuel
  app2?: true           // lien vers l'application de suivi (app2)
  ready: boolean        // la page app3 est livrée
}
interface MenuGroup { id: string; children: MenuLink[] }
type MenuItem = MenuLink | MenuGroup
```

### Résolution d'un lien (fonction pure `resolveMenuLink`)

| Cas | Résultat |
|---|---|
| `ready` et `to` | lien **interne**, localisé (`/news`, `/en/news`) |
| sinon, `legacyPath` | lien **externe** vers `{NUXT_PUBLIC_LEGACY_BASE_URL}{legacyPath}?lang={fr\|en}` (le legacy lit `lang`) |
| `app2` | lien **externe** vers `NUXT_PUBLIC_APP2_BASE_URL` |
| aucun des cas | entrée **masquée** |

Un groupe dont toutes les entrées sont masquées est masqué.

### Entrée active (fonction pure `isActiveLink`)
- Un lien interne est actif si le chemin courant (sans préfixe de langue) est égal à `to`, ou commence par
  `to + '/'`. L'accueil `/` n'est actif que sur `/` exactement.
- Un groupe est actif si l'une de ses entrées l'est.
- Les liens externes ne sont jamais actifs.

## 4. Comportement

### Bureau (≥ 1024 px)
- Barre horizontale sur fond marine ; entrée active soulignée et portant `aria-current="page"`.
- Un groupe s'ouvre **au clic** (pas seulement au survol), via un bouton `aria-expanded` / `aria-controls`.
- Clavier : Entrée/Espace ouvre, Échap ferme et rend le focus au bouton ; un clic à l'extérieur ferme.
- Un seul sous-menu ouvert à la fois.

### Mobile (< 1024 px)
- Bouton « Menu » (icône burger, `aria-expanded`, `aria-controls`) qui ouvre un panneau vertical.
- Les groupes sont des sections dépliables.
- Le panneau se ferme au changement de page et à la touche Échap.

### Liens externes
- Liens vers le site actuel : même onglet, icône « lien externe » et mention accessible « (site actuel) ».
- Lien app2 : même onglet, icône « lien externe ».

## 5. Langue

- Français par défaut sans préfixe, anglais sous `/en` (`@nuxtjs/i18n`, stratégie `prefix_except_default`).
- Le sélecteur FR | EN de l'en-tête mène à **la même page** dans l'autre langue (`switchLocalePath`) ; la langue
  courante porte `aria-current="true"`.
- Les libellés de menu sont des clés i18n `nav.<id>` (FR/EN).

## 6. Critères d'acceptation

- **NAV-01** — `MAIN_MENU` contient, dans l'ordre : home, news, calendar, competitions (competitions-list, history), teams-clubs (teams, clubs), live.
- **NAV-02** — `resolveMenuLink` renvoie un lien interne localisé pour une entrée `ready` (`/news` en FR, `/en/news` en EN).
- **NAV-03** — `resolveMenuLink` renvoie l'URL legacy absolue avec `?lang=fr|en` pour une entrée non livrée ayant un `legacyPath`.
- **NAV-04** — `resolveMenuLink` renvoie l'URL d'app2 pour l'entrée « En direct ».
- **NAV-05** — Une entrée sans cible est masquée, et un groupe sans entrée visible aussi.
- **NAV-06** — `isActiveLink` : `/competitions/2026/N1` active « Compétitions et résultats » et son groupe ; `/` n'active que l'accueil.
- **NAV-07** — Le bouton d'un groupe bascule `aria-expanded` et affiche ses entrées ; Échap le referme.
- **NAV-08** — Sur mobile, le bouton « Menu » ouvre et ferme le panneau (`aria-expanded`).
- **NAV-09** — Le sélecteur de langue pointe vers la même page dans l'autre langue.
- **NAV-10** — En phase 1, seule l'entrée « Accueil » est interne ; les autres pointent vers le legacy ou app2.

## 7. Hors périmètre / questions ouvertes

- Menu éditorial administrable (entrée « Le kayak-polo » et ses pages) : phase 4a, `FEATURE_CMS.md`.
- Recherche dans l'en-tête : phase 3.
