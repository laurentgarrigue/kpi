# Reel « Installe l'application » (FR / EN)

Projet Remotion, 1080×1920, 30 i/s, 27 s : accroche (3 s), étapes iPhone (6 s), étapes Android (5 s),
démonstration hors ligne (9 s, extrait d'un enregistrement Playwright), QR code (4 s).

```bash
npm install
npx playwright install chromium-headless-shell
npx tsx scripts/record-offline.ts                       # enregistre recordings/offline.webm (préprod, événement 242)
npx tsx scripts/record-offline.ts en                    # version anglaise (interface en, cookie i18n_redirected) → recordings/offline-en.webm ; convertir en public/demos/offline-en.mp4
npx remotion ffmpeg -y -i recordings/offline.webm -c:v libx264 -crf 18 -pix_fmt yuv420p -movflags faststart public/demos/offline.mp4
npm run dev                                             # Remotion Studio
npx remotion render InstallFR out/installation-app-fr.mp4
npx remotion render InstallEN out/installation-app-en.mp4
cp out/*.mp4 ../../exports/
```

- Les cartes d'étapes iPhone/Android sont **dessinées** (icônes SVG) : l'interface native des téléphones n'est pas enregistrable.
- Libellés Android à vérifier sur un vrai téléphone (« Ajouter à l'écran d'accueil » selon la doc, Chrome propose parfois « Installer l'application »).
- Procédure complète : `../../GUIDE_PRODUCTION.md` (§ 7).
