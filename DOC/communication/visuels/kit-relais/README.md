# Kit de relais des plénières 2026 (chantier C27)

Recette des visuels remis aux régions et aux entraîneurs ([plan § 7.1](../../../developer/in-progress/PLAN_COMMUNICATION_SAISON_2027.md)).
Textes prêts à publier : [TEXTES.md](TEXTES.md). Livrables : `~/Documents/dev/kpi_exports/kit-relais/`, hors du dépôt.

| Visuel (`visuels.html`) | Capture source |
|---|---|
| `#v1` L'application bord de terrain, 1080×1350 | `captures/bord-de-terrain-frise-match.png`, réduite au graphique des périodes (aucun nom de joueur) |
| `#v2` Le site public, 1080×1350 | `captures/site-public-carte-clubs-cadree.png` |
| `#v3` La saison 2027, 1080×1350 | aucune : dates confirmées du plan § 0 seulement |
| `#s1` Story, 1080×1920 | `captures/bord-de-terrain-liste-matchs.png` |

```bash
npm install
npm run qr          # QR code de PRODUCTION, marqué mtm_source=relais → captures/qr-relais-*.{svg,png}
npm run captures    # carte des clubs sur beta.preprod (bandeau bêta et boutons flottants masqués)
# servir la RACINE du dépôt (les polices viennent de sources/kpi-layer) :
cd ../../../.. && python3 -m http.server 8765 --bind 127.0.0.1 &
cd - && npm run export   # un PNG par <section class="visuel">
npm run pdf              # PDF A4 du kit de relais (print.html) : visuels, textes à publier, un QR code par lien
npm run pdf presentation # PDF A4 des nouveautés (presentation.html) : visuels seuls, sans appel à l'action
pkill -f "[h]ttp.server 8765"
```

**PDF A4** (`print.html` → `kit-relais-saison-2027-A4.pdf`, 4 pages, fond blanc) : version papier du kit, distribuée
ou laissée en libre service aux plénières. Ses textes reprennent [TEXTES.md](TEXTES.md) : modifier les deux ensemble.
Chaque lien marqué y a son QR code, pour l'ouvrir et le copier depuis un téléphone. Lancer `npm run export` avant
`npm run pdf` (le PDF intègre les PNG exportés).

**PDF des nouveautés** (`presentation.html` → `saison-2027-nouveautes-A4.pdf`, 4 pages) : couverture puis un visuel
par page avec une courte description, sans Facebook / Instagram ni consigne de publication.

À refaire si une date change (Corbeil-Essonnes, tournoi de fin février) : ajouter la ligne dans `#v3` **seulement
une fois la date confirmée**. Aucune mention des médailles (plan § 3.4).
