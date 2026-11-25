// Captures haute définition du site public pour le kit de relais (préprod, GUIDE_PRODUCTION.md § 4 et § 9).
// Usage : npm run captures   →   ../captures/site-public-carte-clubs{,-cadree}.png
import { chromium, type Page } from "playwright";

const SITE = "https://beta.preprod.kayak-polo.info";
const OUT = "../captures";

async function guardNoMedal(page: Page) {
  const text = await page.evaluate(() => document.body.innerText);
  if (/m[ée]dail|medal/i.test(text)) throw new Error(`Une médaille est affichée sur ${page.url()} : capture annulée.`);
}

// Bandeau « Version bêta » et bouton flottant « Bas de page » : absents du site final.
async function hideBetaChrome(page: Page) {
  await page.evaluate(() => {
    const banner = [...document.querySelectorAll<HTMLElement>("body *")].find(
      (e) => e.innerText?.startsWith("Version bêta") && e.getBoundingClientRect().top < 5,
    );
    let top = banner;
    while (top?.parentElement && top.parentElement.innerText?.startsWith("Version bêta")) top = top.parentElement;
    if (top) top.style.display = "none";
    document.querySelectorAll<HTMLElement>("body *").forEach((b) => {
      if (getComputedStyle(b).position === "fixed") b.style.display = "none";
    });
    window.scrollTo(0, 0);
  });
}

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({ viewport: { width: 540, height: 960 }, deviceScaleFactor: 3, locale: "fr-FR" });
  const page = await context.newPage();
  try {
    await page.goto(`${SITE}/clubs`, { waitUntil: "networkidle" });
    await guardNoMedal(page);
    await page.getByRole("button", { name: "Afficher la carte" }).click();
    await page.waitForLoadState("networkidle");
    await page.waitForTimeout(3000);
    await hideBetaChrome(page);
    await page.screenshot({ path: `${OUT}/site-public-carte-clubs.png` });
    // Recadrage : du champ de recherche au bas de la carte (visuel 2 du kit).
    const top = await page.getByPlaceholder("Club, code ou équipe").boundingBox();
    const map = await page.locator(".leaflet-container").boundingBox();
    if (!top || !map) throw new Error("Recherche ou carte introuvable.");
    const y = top.y - 8;
    await page.screenshot({
      path: `${OUT}/site-public-carte-clubs-cadree.png`,
      clip: { x: 0, y, width: 540, height: map.y + map.height + 16 - y },
    });
    console.log("Captures : site-public-carte-clubs.png, site-public-carte-clubs-cadree.png");
  } finally {
    await browser.close();
  }
})();
