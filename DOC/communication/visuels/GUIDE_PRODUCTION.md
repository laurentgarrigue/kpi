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
└── reels/
    ├── frise-match/             Reel « Le match minute par minute » (captures animées)
    └── installation-app/        Reel d'installation (cartes d'étapes + enregistrement Playwright hors ligne)
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

Procédure **testée** sur le Reel d'installation (`reels/installation-app/`, script `scripts/record-offline.ts`). À copier pour tout Reel de démonstration.

**Ce que Playwright peut et ne peut pas enregistrer**
- Il enregistre **la page web** (l'application bord de terrain, le site public), y compris le **mode hors ligne** (`context.setOffline(true)`).
- Il **ne peut pas** enregistrer l'interface native d'iOS ou d'Android (feuille « Partager » de Safari, menu de Chrome, écran d'accueil du téléphone). Pour ces étapes, on dessine des cartes d'étapes dans Remotion (numéro + icône SVG + texte) : ce ne sont pas des captures réelles. Pour un rendu 100 % réel, il faut un enregistrement d'écran fait à la main sur un vrai téléphone.

**Installation** (dans le projet Remotion du Reel) :

```bash
npm install -D --save-exact playwright tsx
npx playwright install chromium-headless-shell   # la version du navigateur doit correspondre à la version de Playwright
```

Le cache `~/.cache/ms-playwright` peut contenir une autre version : en cas d'erreur « Executable doesn't exist », relancer la commande ci-dessus.

**Écrire le script** : partir de `reels/installation-app/scripts/record-offline.ts`. Contexte testé :

```ts
const context = await browser.newContext({
  viewport: { width: 540, height: 960 },          // 9:16
  deviceScaleFactor: 2,
  locale: "fr-FR",
  recordVideo: { dir: "./recordings", size: { width: 540, height: 960 } },
});
```

Lancer : `npx tsx scripts/record-offline.ts` (le fichier est renommé en `recordings/offline.webm`).

**Pièges rencontrés**
- **Pas de `await` de premier niveau** avec `tsx` (erreur `ERR_REQUIRE_ASYNC_MODULE`) : envelopper dans `(async () => { ... })();`.
- **Écrire la vidéo** : elle n'existe qu'après `context.close()` ; la récupérer avec `page.video().path()` dans un `finally`.
- **Curseur visible** : injecté par `addInitScript` (disque bleu, rouge au clic), mais Playwright clique **instantanément** : la fonction `tap()` du script déplace d'abord la souris (`steps: 18`), pause, puis clique.
- **Filtre des matchs** : mêmes règles qu'au § 4 (entonnoir à `mouse.click(80, 84)`, cases par `getByText(label, { exact: true })`). La bascule « Voir les arbitres » se trouve en remontant de chaque `input[type=checkbox]` jusqu'à un ancêtre dont le texte est exactement « Voir les arbitres » (le panneau contient aussi les cases des catégories et des équipes).
- **Arbitres dans la feuille de match** : la ligne « Arbitres : ... » s'affiche quand même ; le script la **floute** (`filter: blur(7px)` sur la ligne entière, repérée par un `TreeWalker` sur les nœuds texte, juste après le chargement de la feuille). Le script échoue si rien n'est flouté.
- **Garde-fou médailles** : le script lève une erreur si le texte de la page contient « médaille » ; l'enregistrement est alors à jeter.
- **Hors ligne** : après `setOffline(true)`, l'application affiche l'icône de réseau barrée et les toasts « Hors connexion » / « Données en Cache ». Naviguer ensuite **dans l'application** (bouton « Retour »), pas par un `goto`. Seules les pages déjà consultées en ligne sont disponibles.
- **Chronologie** : un enregistrement d'environ 20 s. Le Reel n'en garde que l'extrait utile (`OFFLINE_CLIP_START_SECONDS`) ; vérifier les temps en extrayant des images (ci-dessous).
- **Définition** : la vidéo est en 540×960 (taille CSS), agrandie dans Remotion. Dans un cadre de téléphone de 600 px de large, le rendu reste correct.

**Conversion et contrôle** (pas de `ffmpeg` système : utiliser celui de Remotion, qui inclut libx264) :

```bash
npx remotion ffmpeg -y -i recordings/offline.webm -c:v libx264 -crf 18 -pix_fmt yuv420p -movflags faststart public/demos/offline.mp4
npx remotion ffprobe -v error -show_entries format=duration -of csv=p=0 recordings/offline.webm    # durée
npx remotion ffmpeg -y -ss 11 -i public/demos/offline.mp4 -frames:v 1 out/frames/f11.png           # une image à t = 11 s
```

Le filtre `tile=` de ffmpeg échoue dans cette version : assembler les planches-contact avec Pillow (`PIL.Image`).

**Intégration dans Remotion** : `<OffthreadVideo src={staticFile("demos/offline.mp4")} startFrom={secondes * fps} muted />` dans une `<Sequence>`, cadré dans un cadre de téléphone (bordure bleue, coins arrondis).

## 8. Check-list avant publication

- [ ] Vocabulaire : ni app2/app3/app4, ni « gymnase ».
- [ ] Aucune médaille visible (capture, vidéo, miniature) avant la révélation.
- [ ] Données publiques, arbitres masqués, clubs et joueurs nommés prévenus.
- [ ] Captures refaites sur un événement 2027.
- [ ] Lien avec les paramètres `mtm_*`, QR code lisible.
- [ ] Texte FR relu, texte EN relu (vocabulaire du kayak-polo international).
- [ ] Sous-titres présents sur les Reels (lecture sans son).

### Version anglaise des enregistrements

`npx tsx scripts/record-offline.ts en` enregistre l'application en anglais (cookie `i18n_redirected=en` + locale `en-US`) : libellés « Games », « Show refs », « Timeline », « Back », messages « Offline » / « Using Cached Data », ligne « Referees : » floutée. Convertir ensuite en `public/demos/offline-en.mp4`. Le nom de domaine d'un texte à étapes ne doit jamais être coupé : utiliser `withDomain()` (Composition.tsx).

Capture de la frise en anglais : `cd reels/installation-app && npx tsx scripts/capture-frise.ts en` → `captures/bord-de-terrain-frise-match-en.png` (recadrer le pied « v1.x » à la main). Le Reel `frise-match` choisit la capture selon la langue (`SHOTS` dans `Composition.tsx`).
