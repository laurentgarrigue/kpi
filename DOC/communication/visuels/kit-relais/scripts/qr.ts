// QR code de l'application bord de terrain pour le kit de relais (lien de production, marqué Matomo).
// Usage : npm run qr   →   ../captures/qr-relais-application-bord-de-terrain.{svg,png}
import QRCode from "qrcode";

const URL =
  "https://app.kayak-polo.info/?mtm_campaign=saison2027&mtm_source=relais&mtm_content=kit-qr";
const OUT = "../captures/qr-relais-application-bord-de-terrain";
const options = { errorCorrectionLevel: "M" as const, margin: 2, color: { dark: "#1e1e1c", light: "#ffffff" } };

(async () => {
  await QRCode.toFile(`${OUT}.svg`, URL, { ...options, type: "svg" });
  await QRCode.toFile(`${OUT}.png`, URL, { ...options, type: "png", width: 1200 });
  console.log("QR :", URL);
})();
