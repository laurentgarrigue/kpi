// Enregistre un parcours de l'application bord de terrain, puis coupe le réseau (mode hors ligne).
// Usage : npx tsx scripts/record-offline.ts   →   recordings/*.webm + public/demos/offline.mp4 (via ffmpeg Remotion)
import { chromium, type Locator, type Page } from "playwright";
import { mkdirSync, readdirSync, renameSync } from "node:fs";

const BASE = "https://app.preprod.kayak-polo.info";
const SIZE = { width: 540, height: 960 };
const DIR = "./recordings";

// Langue : `npx tsx scripts/record-offline.ts en` → interface anglaise, sortie recordings/offline-en.webm
const LANG = process.argv[2] === "en" ? "en" : "fr";
const L = {
  fr: { games: "Matchs Programme et résultats", showRefs: "Voir les arbitres", timeline: "Chronologie", back: /retour/i, refs: /^\s*Arbitres?\s*:/ },
  en: { games: "Games Schedule and results", showRefs: "Show refs", timeline: "Timeline", back: /back/i, refs: /^\s*Referees?\s*:/ },
}[LANG];
const OUT = LANG === "en" ? "offline-en.webm" : "offline.webm";

const CURSOR_CSS = `
  .pw-cursor{position:fixed;z-index:2147483647;width:34px;height:34px;margin:-17px 0 0 -17px;border-radius:50%;
    background:rgba(105,185,230,.55);border:3px solid #fff;pointer-events:none;transition:transform .12s}
  .pw-cursor.down{transform:scale(.7);background:rgba(201,74,76,.7)}`;

async function installCursor(page: Page) {
  await page.addInitScript((css) => {
    window.addEventListener("DOMContentLoaded", () => {
      const st = document.createElement("style");
      st.textContent = css;
      document.head.appendChild(st);
      const c = document.createElement("div");
      c.className = "pw-cursor";
      c.style.left = "-100px";
      document.body.appendChild(c);
      document.addEventListener("mousemove", (e) => { c.style.left = e.clientX + "px"; c.style.top = e.clientY + "px"; }, true);
      document.addEventListener("mousedown", () => c.classList.add("down"), true);
      document.addEventListener("mouseup", () => c.classList.remove("down"), true);
    });
  }, CURSOR_CSS);
}

// Déplace le curseur visiblement, puis clique
async function tap(page: Page, target: Locator) {
  const box = await target.boundingBox();
  if (!box) throw new Error("élément sans boîte : " + target);
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2, { steps: 18 });
  await page.waitForTimeout(250);
  await page.mouse.down();
  await page.waitForTimeout(120);
  await page.mouse.up();
}

const pause = (page: Page, ms = 1500) => page.waitForTimeout(ms);

(async () => {
  mkdirSync(DIR, { recursive: true });
  const browser = await chromium.launch();
  const context = await browser.newContext({
    viewport: SIZE,
    deviceScaleFactor: 2,
    locale: LANG === "en" ? "en-US" : "fr-FR",
    recordVideo: { dir: DIR, size: SIZE },
  });
  // l'application mémorise la langue choisie dans ce cookie (nuxt-i18n)
  await context.addCookies([{ name: "i18n_redirected", value: LANG, url: BASE }]);
  const page = await context.newPage();
  await installCursor(page);
  try {
    // 1. Ouverture : l'événement est sélectionné par son lien (/event/<id>)
    await page.goto(`${BASE}/event/242`);
    await page.waitForURL((u) => u.pathname === "/", { timeout: 20000 });
    await page.waitForSelector("canvas");
    await pause(page, 2000);

    // 2. Liste des matchs, filtrée sur les catégories « joueurs majeurs »
    await tap(page, page.getByRole("link", { name: L.games }));
    await page.waitForSelector('a[href^="/game/"]', { state: "attached" });
    await pause(page, 1200);
    await page.mouse.click(80, 84); // entonnoir du filtre (pas de libellé)
    await pause(page, 800);
    for (const label of ["N3 Finales", "PlayOff D.", "PlayOff H."]) {
      await tap(page, page.getByText(label, { exact: true }));
      await page.waitForTimeout(400);
    }
    // masque les arbitres : on remonte depuis chaque case jusqu'à un ancêtre dont le texte est exactement « Voir les arbitres »
    const toggled = await page.evaluate((label) => {
      for (const box of document.querySelectorAll<HTMLInputElement>("input[type=checkbox]")) {
        for (let el: HTMLElement | null = box.parentElement, i = 0; el && i < 4; el = el.parentElement, i++) {
          if (el.innerText.trim() === label) {
            if (box.checked) box.click();
            return !box.checked;
          }
        }
      }
      return false;
    }, L.showRefs);
    if (!toggled) throw new Error(`Bascule « ${L.showRefs} » introuvable ou non désactivée.`);
    await page.mouse.click(80, 84);
    await pause(page, 1500);

    // 3. Ouverture d'une feuille de match (mise en cache par l'application)
    await tap(page, page.getByRole("link", { name: "#302" }));
    await page.waitForSelector(`text=${L.timeline}`, { timeout: 20000 });

    // Floute la ligne « Arbitres : ... » de la feuille de match (pas de noms d'arbitres dans les visuels)
    const refsSrc = L.refs.source;
    const blurred = await page.evaluate((src) => {
      const refsRe = new RegExp(src);
      const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
      let n = 0;
      for (let node = walker.nextNode(); node; node = walker.nextNode()) {
        if (refsRe.test(node.textContent ?? "") && node.parentElement) {
          // le libellé peut être dans un <span> : on floute la ligne entière (libellé + noms)
          let line: HTMLElement = node.parentElement;
          while (line.innerText.trim().length < 25 && line.parentElement) line = line.parentElement;
          line.style.filter = "blur(7px)";
          n++;
        }
      }
      return n;
    }, refsSrc);
    if (blurred === 0) throw new Error("Ligne des arbitres introuvable : rien n'a été flouté.");

    await pause(page, 2500);

    // Garde-fou : jamais de médaille avant la révélation
    const text = await page.evaluate(() => document.body.innerText);
    if (/m[ée]dail|medal/i.test(text)) throw new Error("Une médaille est affichée : enregistrement à jeter.");

    // 4. Plus de réseau
    await context.setOffline(true);
    await pause(page, 2500);

    // 5. L'application fonctionne toujours : retour à la liste déjà consultée
    await tap(page, page.getByRole("button", { name: L.back }).first());
    await pause(page, 3000);
  } finally {
    const video = page.video();
    await context.close();
    await browser.close();
    if (video) {
      const path = await video.path();
      renameSync(path, `${DIR}/${OUT}`);
      console.log("Enregistrement :", `${DIR}/${OUT}`, readdirSync(DIR));
    }
  }
})();
