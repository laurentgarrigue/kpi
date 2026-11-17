// Capture haute définition du bloc « Chronologie / Timeline » du match #302 (événement 242, préprod).
// Usage : npx tsx scripts/capture-frise.ts [en]   →   ../../captures/bord-de-terrain-frise-match[-en].png
import { chromium } from "playwright";

const BASE = "https://app.preprod.kayak-polo.info";
const LANG = process.argv[2] === "en" ? "en" : "fr";
const L = {
  fr: { games: "Matchs Programme et résultats", timeline: "Chronologie" },
  en: { games: "Games Schedule and results", timeline: "Timeline" },
}[LANG];
const OUT = `../../captures/bord-de-terrain-frise-match${LANG === "en" ? "-en" : ""}.png`;

(async () => {
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: { width: 540, height: 960 },
    deviceScaleFactor: 3,
    locale: LANG === "en" ? "en-US" : "fr-FR",
  });
  await context.addCookies([{ name: "i18n_redirected", value: LANG, url: BASE }]);
  const page = await context.newPage();
  try {
    await page.goto(`${BASE}/event/242`);
    await page.waitForURL((u) => u.pathname === "/", { timeout: 20000 });
    await page.waitForSelector("canvas");
    await page.getByRole("link", { name: L.games }).click();
    await page.waitForSelector('a[href^="/game/"]', { state: "attached" });
    await page.getByRole("link", { name: "#302" }).click();
    await page.waitForSelector(`text=${L.timeline}`, { timeout: 20000 });
    await page.waitForTimeout(1500);

    const text = await page.evaluate(() => document.body.innerText);
    if (/m[ée]dail|medal/i.test(text)) throw new Error("Une médaille est affichée : capture annulée.");

    // plus proche ancêtre du titre de plus de 300 px de haut
    const found = await page.evaluate((label) => {
      const el = [...document.querySelectorAll<HTMLElement>("*")].find((e) => e.children.length === 0 && e.innerText?.trim() === label);
      let box: HTMLElement | null = el ?? null;
      while (box && box.getBoundingClientRect().height < 300) box = box.parentElement;
      if (!box) return false;
      box.id = "cap-frise";
      return true;
    }, L.timeline);
    if (!found) throw new Error("Bloc chronologie introuvable.");
    await page.locator("#cap-frise").screenshot({ path: OUT });
    console.log("Capture :", OUT);
  } finally {
    await browser.close();
  }
})();
