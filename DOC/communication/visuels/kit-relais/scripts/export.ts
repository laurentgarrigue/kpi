// Exporte chaque <section class="visuel"> de visuels.html en PNG à la taille réelle (1080×1350 ou 1080×1920).
// Prérequis : servir la racine du dépôt (README.md).  Usage : npm run export [dossier]
import { chromium } from "playwright";
import { homedir } from "node:os";
import { mkdirSync } from "node:fs";

const PAGE = "http://127.0.0.1:8765/DOC/communication/visuels/kit-relais/visuels.html";
const OUT = process.argv[2] ?? `${homedir()}/Documents/dev/kpi_exports/kit-relais`;
const NAMES: Record<string, string> = {
  v1: "kit-relais-1-application-bord-de-terrain",
  v2: "kit-relais-2-site-public",
  v3: "kit-relais-3-saison-2027",
  s1: "kit-relais-story-application-bord-de-terrain",
};

(async () => {
  mkdirSync(OUT, { recursive: true });
  const browser = await chromium.launch();
  const page = await browser.newPage({ viewport: { width: 2400, height: 2200 } });
  try {
    await page.goto(PAGE, { waitUntil: "networkidle" });
    await page.evaluate(() => document.fonts.ready);
    for (const [id, name] of Object.entries(NAMES)) {
      await page.locator(`#${id}`).screenshot({ path: `${OUT}/${name}.png` });
      console.log(`${OUT}/${name}.png`);
    }
  } finally {
    await browser.close();
  }
})();
