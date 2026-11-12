# Reel « Le match minute par minute » (FR / EN)

Projet Remotion, 1080×1920, 30 i/s, 13 s : accroche (3 s), frise du match dévoilée (6 s), QR code (4 s).

```bash
npm install
npm run dev                                   # Remotion Studio
npx remotion render FriseFR out/frise-match-fr.mp4
npx remotion render FriseEN out/frise-match-en.mp4
```

- Captures : `public/bord-de-terrain-frise-match.png` et `public/qr-application-bord-de-terrain.png`
  (copies de `../../captures/`, à remplacer par une capture de la saison 2027).
- Police : Raleway (variable), copiée depuis `sources/kpi-layer/public/fonts/raleway/`.
- Textes FR / EN : objet `TEXTS` dans `src/Composition.tsx`.
- Les MP4 rendus sont copiés dans `../../exports/`.
