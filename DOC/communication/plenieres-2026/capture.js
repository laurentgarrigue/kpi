// Captures réelles des sites KPI pour le diaporama des Plénières 2026.
// Usage : node DOC/communication/plenieres-2026/capture.js
// Sortie : DOC/communication/plenieres-2026/captures/*.png (pleine résolution, avant recadrage).
const path = require('path');
const { chromium, devices } = require('playwright');

const OUTPUT_DIR = path.join(__dirname, 'captures');
const APP2 = 'https://app.kayak-polo.info';
const APP3 = 'https://beta.kayak-polo.info';
const APP2_GROUP = `${APP2}/group/2026/N1H`;
const APP2_GAME_ID = '79292713'; // Finale N1H 2026, Avranches I – Corbeil-Essonnes I
const APP2_TEAM = 'Avranches I';
const SETTLE_MS = 3000;
const DESKTOP = { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 2 };

async function captureApp2Game(browser) {
  const context = await browser.newContext({ ...devices['iPhone 13'], locale: 'fr-FR', colorScheme: 'light' });
  const page = await context.newPage();
  // app2 is a static SPA: open the group first, then navigate inside the app.
  await page.goto(APP2_GROUP, { waitUntil: 'networkidle' });
  await page.click('text=Programme et résultats');
  await page.waitForTimeout(SETTLE_MS);
  await page.evaluate((id) => document.querySelector(`a[href="/game/${id}"]`).click(), APP2_GAME_ID);
  await page.waitForTimeout(SETTLE_MS);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'app2-match.png'), fullPage: true });
  await context.close();
}

async function captureApp2Gallery(browser) {
  const context = await browser.newContext({ ...devices['iPhone 13'], locale: 'fr-FR', colorScheme: 'light' });
  const page = await context.newPage();
  const shots = [
    ['Programme et résultats', 'app-matchs'],
    ['Classements et tableaux', 'app-classement'],
  ];
  for (const [menu, name] of shots) {
    await page.goto(APP2_GROUP, { waitUntil: 'networkidle' });
    await page.click(`text=${menu}`);
    await page.waitForTimeout(SETTLE_MS);
    await page.screenshot({ path: path.join(OUTPUT_DIR, `${name}.png`) });
  }
  await page.goto(APP2_GROUP, { waitUntil: 'networkidle' });
  await page.click("text=Parcours d'une équipe");
  await page.waitForTimeout(SETTLE_MS);
  await page.locator('select').last().selectOption({ label: APP2_TEAM });
  await page.waitForTimeout(SETTLE_MS);
  await page.screenshot({ path: path.join(OUTPUT_DIR, 'app-equipe.png') });
  await context.close();
}

async function captureApp3(browser, url, name) {
  const context = await browser.newContext({ ...DESKTOP, locale: 'fr-FR', colorScheme: 'light' });
  const page = await context.newPage();
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(SETTLE_MS);
  await page.screenshot({ path: path.join(OUTPUT_DIR, `${name}.png`) });
  await context.close();
}

(async () => {
  const browser = await chromium.launch();
  await captureApp2Game(browser);
  await captureApp2Gallery(browser);
  await captureApp3(browser, `${APP3}/`, 'site-accueil');
  await captureApp3(browser, `${APP3}/groups/2026/REG03`, 'site-reg03');
  await captureApp3(browser, `${APP3}/competitions/2026/N1H/ranking`, 'site-classement');
  await captureApp3(browser, `${APP3}/history`, 'site-palmares');
  await browser.close();
})();
