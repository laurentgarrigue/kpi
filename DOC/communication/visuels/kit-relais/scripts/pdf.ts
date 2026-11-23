// PDF A4 imprimables, avec les visuels exportés et un QR code par lien.
//   npm run pdf                → print.html        : kit de relais (textes à publier, liens)
//   npm run pdf presentation   → presentation.html : les nouveautés, sans appel à l'action
// Prérequis : npm run export, et la racine du dépôt servie (README.md).
import { chromium } from "playwright";
import QRCode from "qrcode";
import { homedir } from "node:os";
import { readFileSync } from "node:fs";

const SOURCES: Record<string, string> = {
  print: "kit-relais-saison-2027-A4.pdf",
  presentation: "saison-2027-nouveautes-A4.pdf",
};
const SOURCE = process.argv[2] ?? "print";
if (!SOURCES[SOURCE]) throw new Error(`Page inconnue : ${SOURCE} (${Object.keys(SOURCES).join(", ")})`);
const PAGE = `http://127.0.0.1:8765/DOC/communication/visuels/kit-relais/${SOURCE}.html`;
const DIR = `${homedir()}/Documents/dev/kpi_exports/kit-relais`;
const OUT = `${DIR}/${SOURCES[SOURCE]}`;
const QR_OPTIONS = { errorCorrectionLevel: "M" as const, margin: 1, color: { dark: "#1e1e1c", light: "#ffffff" } };

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  try {
    await page.goto(PAGE, { waitUntil: "networkidle" });

    const urls = await page.$$eval("[data-qr]", (els) => els.map((e) => e.getAttribute("data-qr")!));
    const svgs: Record<string, string> = {};
    for (const url of urls) svgs[url] = await QRCode.toString(url, { ...QR_OPTIONS, type: "svg" });

    const names = await page.$$eval("img[data-visuel]", (els) => els.map((e) => e.getAttribute("data-visuel")!));
    const images: Record<string, string> = {};
    for (const name of names) images[name] = `data:image/png;base64,${readFileSync(`${DIR}/${name}.png`).toString("base64")}`;

    await page.evaluate(
      ({ svgs, images }) => {
        document.querySelectorAll<HTMLElement>("[data-qr]").forEach((e) => (e.innerHTML = svgs[e.dataset.qr!]));
        document.querySelectorAll<HTMLImageElement>("img[data-visuel]").forEach((e) => (e.src = images[e.dataset.visuel!]));
      },
      { svgs, images },
    );
    await page.evaluate(async () => {
      await document.fonts.ready;
      await Promise.all([...document.images].map((i) => i.decode()));
    });
    if (/m[ée]dail|medal/i.test(await page.evaluate(() => document.body.innerText))) throw new Error("Le kit mentionne les médailles.");
    await page.pdf({ path: OUT, format: "A4", printBackground: true, preferCSSPageSize: true });
    console.log(OUT);
  } finally {
    await browser.close();
  }
})();
