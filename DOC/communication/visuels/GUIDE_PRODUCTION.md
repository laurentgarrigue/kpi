# Produire les visuels, carrousels et Reels de la communication KPI

Guide de production pour le [plan de communication saison 2027](../../developer/in-progress/PLAN_COMMUNICATION_SAISON_2027.md).
Tout ce qui a été appris en produisant le premier carrousel et le premier Reel (« Le match minute par minute »).

## 1. Règles éditoriales à respecter

| Règle | Détail |
|---|---|
| **Vocabulaire grand public** | Jamais « app2 / app3 / app4 ». On dit **l'application bord de terrain**, **le site public**, **admin2 / gestion sportive**. |
| **Pas de gymnase** | Le kayak-polo se joue en extérieur : « plan d'eau », éventuellement « stade de kayak-polo ». |
| **Médailles de match = secret** jusqu'à la révélation (semaine du 15 mars 2027, plan § 3.4) | Le calcul des médailles 2026 a été **supprimé sur la préprod** (vérifié le 12/11/2026 sur le match #302 : plus de bloc « Médailles »). Garder le réflexe : **contrôler à chaque session** (`document.body.innerText` ne contient pas « médaille ») et ne capturer que des blocs ciblés (ex. « Chronologie »). Si le calcul est relancé sur la préprod, ne plus capturer de page entière. |
| **Joueurs majeurs** | Filtrer sur les catégories **N3 Finales, PlayOff H., PlayOff D.** (et non U15/U18). |
| **Données personnelles** | Captures sur données publiques uniquement. Masquer les noms d'arbitres (bascule « Voir les arbitres »). Prévenir les clubs dont des joueurs sont nommés. Captures d'admin2 : compétition de démonstration ou noms floutés. |
| **Charte** | FFCK univers Compétition : bleus `#69b9e6` / `#357b9c`, rouges `#c94a4c` / `#882831`, noir `#1e1e1c`, gris `#c6c7c7`, Raleway. |
| **Formats** | Carrousel 1080×1350, Reel/story 1080×1920, lien 1200×630. Marges : 80 px sur les côtés, 100 px en haut et en bas pour les Reels. |
| **Mesure** | Les liens portent `?mtm_campaign=saison2027&mtm_source=instagram\|facebook\|relais&mtm_content=<sujet>`. |
| **Date des captures** | Les finales 2026 (événement 242) servent de matière. Avant publication, refaire les captures sur un événement 2027. |

## 2. Arborescence

```
DOC/communication/visuels/
├── GUIDE_PRODUCTION.md          ce fichier
├── captures/                    captures haute définition (PNG)
├── gabarits/carrousel-feature.html   gabarit de carrousel 1080×1350
├── exports/                     livrables : PNG des slides, MP4 des Reels
└── reels/frise-match/           projet Remotion (1 projet = 1 Reel, FR + EN)
```

## 3. Outils et pièges de l'environnement

| Sujet | À savoir |
|---|---|
| **Skills** | `remotion-create` et `playwright-recording` sont installés (`.agents/skills/`). `npx skills add` peut être refusé par le classificateur de permissions : l'installer soi-même dans un terminal, ou ajouter une règle Bash. Le skill `remotion-create` cite un skill `remotion-markup` qui n'est **pas** installé. |
| **Playwright MCP** | Il écrit **uniquement dans le dépôt** (racine du dépôt ou `.playwright-mcp/`), pas dans le dossier temporaire de la session. Chemin relatif = relatif à la racine du dépôt. |
| **`file:` bloqué** | Pour rendre un HTML local : `cd DOC/communication/visuels && python3 -m http.server 8765 --bind 127.0.0.1 &` puis `http://127.0.0.1:8765/gabarits/...`. L'arrêter avec `pkill -f "[h]ttp.server 8765"` (sans les crochets, `pkill` tue son propre shell, code 144). |
| **Résolution** | `browser_take_screenshot` sort en 1×. Pour du net, passer par `browser_run_code_unsafe` avec un contexte neuf : `page.context().browser().newContext({ viewport: {width: 540, height: 960}, deviceScaleFactor: 3 })`, puis fermer le contexte dans un `finally`. |
| **Dev vs préprod** | Le dev (`app.kpi.localhost`) n'a pas les finales 2026 : utiliser la **préprod** `https://app.preprod.kayak-polo.info` et `https://beta.preprod.kayak-polo.info`. |
| **ffmpeg / Playwright** | Ni `ffmpeg` ni la bibliothèque `playwright` ne sont installés sur la machine. `npx remotion ffmpeg ...` fournit un ffmpeg ; la bibliothèque s'installe dans le projet Remotion (§ 6). |

## 4. Capturer l'application bord de terrain (procédure testée)

Base : préprod, événement **242** (Finales championnats de France 2026, Combourg).

1. **Contexte neuf** en 540×960 à `deviceScaleFactor: 3` (§ 3).
2. `goto('https://app.preprod.kayak-polo.info/event/242')` puis `waitForURL(u => u.pathname === '/')`.
   `/event/<id>` sélectionne l'événement et **redirige vers l'accueil** ; un `goto('/games')` direct, avant cette sélection, renvoie sur l'accueil.
3. **QR code** : `locator('canvas').first().screenshot(...)`, dans la section « Partager l'événement » de l'accueil (543 px : suffisant pour un visuel, à régénérer en vectoriel pour une affiche A4).
4. **Liste des matchs** : `getByRole('link', { name: 'Matchs Programme et résultats' }).click()`, attendre `a[href^="/game/"]` en `state: 'attached'` (le premier lien trouvé n'est pas toujours visible).
5. **Filtre** : le bouton entonnoir n'a pas de libellé. Le cliquer aux coordonnées `mouse.click(80, 84)` (viewport 540 de large). Le panneau montre les cases « N3 Finales », « PlayOff D. », « PlayOff H. », « U15 Finales », « U18 Finales » : `getByText('N3 Finales', { exact: true }).click()` (ne **pas** chercher `input[type=checkbox]` : ce sont les bascules « Voir les arbitres / shotclock / logos », cachées en `sr-only`). Les filtres ne persistent pas d'un contexte à l'autre.
6. **Masquer les arbitres** : `locator('input[type=checkbox]').first().evaluate(e => e.click())` (un `click()` Playwright sur le libellé ne bascule pas). Contrôle : `document.body.innerText` ne contient plus aucun nom d'arbitre.
7. **Refermer le panneau** (même clic sur l'entonnoir), masquer les boutons flottants « Haut de page / Bas de page » (`display = 'none'`), `scrollTo(0, 0)`, puis `page.screenshot`.
8. **Frise d'un match** : depuis la liste, `getByRole('link', { name: '#302' }).click()`, attendre `text=Chronologie`, repérer le conteneur du titre « Chronologie » (le plus proche ancêtre de plus de 300 px de haut), lui donner un `id`, puis `locator('#cap-frise').screenshot(...)`. Match utilisé : **#302** PlayOff H., Corbeil-Essonnes 4–3 Saint-Grégoire (`/game/79292704`).

Autres catégories de l'événement 242 : `#3xx` = PlayOff H., `#4xx` = PlayOff D., `#5xx` = N3 Finales, `#1xx` = U15, `#2xx` = U18.

Nommage des captures : `bord-de-terrain-<sujet>.png`, `site-public-<sujet>.png`, `admin2-<sujet>.png`.

## 5. Carrousel : le gabarit HTML

`gabarits/carrousel-feature.html` contient deux `<section class="slide">` de 1080×1350 : un kicker, un titre (`<em>` = mot en bleu), un sous-titre, la capture (`.shot img`), un pied de page (URL + pastille `#KPIsaison2027` ou QR code).

- Dupliquer une `<section>` par slide, changer le texte et l'image.
- La capture est cadrée en `object-fit: cover` avec un fondu en bas (`mask-image`). La slide 2 la réduit à 640 px (`#slide-2 .shot img`) car la capture est verticale.
- Titres courts : au-delà de deux lignes, la capture rétrécit.
- **Export** : servir le dossier (§ 3), ouvrir la page, puis `browser_take_screenshot` sur `#slide-N` en `scale: 'css'` avec `filename: 'DOC/communication/visuels/exports/<nom>-slide-N.png'`.

## 6. Reel monté avec Remotion (modèle `reels/frise-match`)

Projet : Remotion 4.0.543, 1080×1920, 30 i/s, trois scènes enchaînées par `<Sequence>` : **accroche** (3 s), **capture dévoilée** (6 s), **appel à l'action** avec QR code (4 s).

```bash
cd DOC/communication/visuels/reels/frise-match
npm install
npm run dev                                          # Remotion Studio
npx remotion still FriseFR out/test.png --frame=120  # contrôler une image
npx remotion render FriseFR out/frise-match-fr.mp4
npx remotion render FriseEN out/frise-match-en.mp4
cp out/*.mp4 ../../exports/                          # `out/` est ignoré par git
```

**Fabriquer un nouveau Reel** : copier le dossier `frise-match` (sans `node_modules` ni `out`), le renommer, puis :

1. Remplacer les captures dans `public/` (PNG haute définition, § 4).
2. Dans `src/Composition.tsx` : adapter l'objet `TEXTS` (fr / en), `SCENE_SECONDS`, et les noms de fichiers passés à `staticFile`. `SHOT_WIDTH` et la hauteur doivent respecter le ratio de la capture.
3. Dans `src/Root.tsx` : un `<Composition>` par langue, `lang` passé dans `defaultProps`.
4. Contrôler 3 images (une par scène, `--frame` < durée totale en images, soit secondes × 30), puis rendre.

**Pièges** :
- **Police** : Raleway est chargée depuis `public/fonts/raleway-latin-wght-normal.woff2` (copie de `sources/kpi-layer/public/fonts/raleway/`) avec `FontFace` + `delayRender`. Google Fonts ne convient pas. Mettre `fontFamily` sur le conteneur **racine** de la composition, pas sur un frère (sinon rendu en serif).
- **Tailles** (guide Remotion) : titre ≥ 84 px et texte important ≥ 44 px pour 1080 px de large.
- **Dévoilement** : `clipPath: inset(0 0 X% 0)` animé avec `interpolate`. La part `TIMELINE_SHARE` (0,38) est la hauteur du graphique des périodes dans la capture : à recaler si la capture change.
- **Anglais** : écrire des formules naturelles (« matchday app », « follow every score from the waterside »), pas une traduction mot à mot.
- Aucune piste audio pour l'instant (à décider pour Instagram).

## 7. Reel en enregistrement d'écran (skill `playwright-recording`)

Pour les Reels de démonstration (installation de l'application sur iPhone/Android, navigation, mode hors ligne). Procédure d'après le skill ; **non testée ici** à la date de rédaction, à valider sur le premier Reel concerné.

**Installation** (dans un projet Remotion, par exemple `reels/<nom>/`) :

```bash
npm install -D playwright tsx
npx playwright install chromium
```

**Script `scripts/record.ts`** (squelette adapté à KPI, format téléphone) :

```ts
import { chromium } from "playwright";

const browser = await chromium.launch({ slowMo: 80 });
const context = await browser.newContext({
  viewport: { width: 540, height: 960 },        // ratio 9:16
  deviceScaleFactor: 2,
  locale: "fr-FR",
  recordVideo: { dir: "./recordings", size: { width: 540, height: 960 } },
});
const page = await context.newPage();

await page.goto("https://app.preprod.kayak-polo.info/event/242");
await page.waitForURL((u) => u.pathname === "/");
await page.waitForTimeout(1500);
await page.getByRole("link", { name: "Matchs Programme et résultats" }).click();
await page.waitForTimeout(2000);
// ... scénario : filtre (§ 4), ouverture d'une feuille de match, défilement de la frise

await context.close();   // le fichier .webm n'est écrit qu'à la fermeture
await browser.close();
```

Lancer : `npx tsx scripts/record.ts`.

**Règles** :
- Mêmes précautions qu'au § 4 : données publiques, arbitres masqués, jamais le bloc « Médailles » avant la révélation. Pour une **vidéo**, l'écran entier est enregistré : contrôler en début de script qu'aucune médaille n'est affichée (voir § 1) et arrêter l'enregistrement si c'est le cas.
- `recordVideo.size` = taille du viewport, sinon la vidéo est redimensionnée.
- Pauses (`waitForTimeout`) de 1 à 2 s entre les gestes : ce sont les temps de lecture.
- Le curseur n'apparaît pas par défaut : injecter un curseur et un effet de clic (voir le skill, section « Cursor Highlighting »). Tout élément injecté est enregistré dans la vidéo.
- Fermer la bannière de cookies et les fenêtres éventuelles avant de lancer l'enregistrement.
- La vidéo se nomme au hasard dans `recordings/` : la récupérer par `page.video().path()` et la renommer.

**Intégration dans Remotion** :

```bash
ffmpeg -i recordings/xxx.webm -c:v libx264 -crf 20 -movflags faststart public/demos/<nom>.mp4
# sans ffmpeg installé : npx remotion ffmpeg -i ... (ffmpeg embarqué dans Remotion)
```

Puis, dans la composition : `<OffthreadVideo src={staticFile("demos/<nom>.mp4")} />` dans une `<Sequence>`, avec les sous-titres incrustés FR ou EN (texte `TEXTS`) et la fin « URL + QR code » du modèle (plan § 8). Durée de la vidéo : `ffprobe -v error -show_entries format=duration -of csv=p=0 fichier.mp4`, × 30 pour les images.

## 8. Check-list avant publication

- [ ] Vocabulaire : ni app2/app3/app4, ni « gymnase ».
- [ ] Aucune médaille visible (capture, vidéo, miniature) avant la révélation.
- [ ] Données publiques, arbitres masqués, clubs et joueurs nommés prévenus.
- [ ] Captures refaites sur un événement 2027.
- [ ] Lien avec les paramètres `mtm_*`, QR code lisible.
- [ ] Texte FR relu, texte EN relu (vocabulaire du kayak-polo international).
- [ ] Sous-titres présents sur les Reels (lecture sans son).
