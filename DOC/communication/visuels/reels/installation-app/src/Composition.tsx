import {
  AbsoluteFill,
  cancelRender,
  continueRender,
  delayRender,
  Img,
  interpolate,
  OffthreadVideo,
  Sequence,
  spring,
  staticFile,
  useCurrentFrame,
  useVideoConfig,
} from "remotion";

// Raleway (charte FFCK) : police variable embarquée, copiée depuis kpi-layer
const fontFamily = "Raleway";
const fontHandle = delayRender("Chargement de Raleway");
new FontFace(
  fontFamily,
  `url(${staticFile("fonts/raleway-latin-wght-normal.woff2")}) format("woff2")`,
  { weight: "100 900" },
)
  .load()
  .then((face) => {
    document.fonts.add(face);
    continueRender(fontHandle);
  })
  .catch((err) => cancelRender(err));

// Charte FFCK univers Compétition
const BLEU = "#69b9e6";
const BLEU_FONCE = "#357b9c";
const ROUGE = "#c94a4c";
const NOIR = "#1e1e1c";
const GRIS = "#c6c7c7";

export type InstallProps = { lang: "fr" | "en" };

const TEXTS = {
  fr: {
    kicker: "L'application bord de terrain",
    hook1: "Pas de réseau",
    hook2: "au bord du bassin ?",
    hookSub: "Installe l'application : elle marche quand même.",
    iphone: "Sur iPhone",
    iphoneSteps: [
      "Dans Safari, ouvre app.kayak-polo.info",
      "Touche « Partager »",
      "Choisis « Sur l'écran d'accueil »",
      "Touche « Ajouter »",
    ],
    android: "Sur Android",
    androidSteps: [
      "Dans Chrome, ouvre app.kayak-polo.info",
      "Touche les trois points",
      "Choisis « Ajouter à l'écran d'accueil »",
      "Touche « Ajouter »",
    ],
    offlineTitle: "Même sans réseau",
    offlineSub: "Les matchs déjà consultés restent disponibles.",
    ctaTitle: "Installe l'application",
    ctaSub: "Un scan, et les scores sont dans ta poche",
    hashtag: "#KPIsaison2027",
  },
  en: {
    kicker: "The matchday app",
    hook1: "No signal",
    hook2: "at the waterside?",
    hookSub: "Install the app: it still works.",
    iphone: "On iPhone",
    iphoneSteps: [
      "In Safari, open app.kayak-polo.info",
      "Tap “Share”",
      "Choose “Add to Home Screen”",
      "Tap “Add”",
    ],
    android: "On Android",
    androidSteps: [
      "In Chrome, open app.kayak-polo.info",
      "Tap the three dots",
      "Choose “Add to Home screen”",
      "Tap “Add”",
    ],
    offlineTitle: "Even offline",
    offlineSub: "Games you already opened stay available.",
    ctaTitle: "Install the app",
    ctaSub: "One scan, and the scores are in your pocket",
    hashtag: "#canoepolo",
  },
} as const;

const SAFE_X = 80;

// Durées (en secondes) des scènes
export const SCENE_SECONDS = {
  hook: 3,
  iphone: 6,
  android: 5,
  offline: 9,
  cta: 4,
} as const;
export const TOTAL_SECONDS = Object.values(SCENE_SECONDS).reduce((a, b) => a + b, 0);

// Extrait utilisé de l'enregistrement (secondes) : feuille de match → coupure du réseau → retour à la liste
const OFFLINE_CLIP_START_SECONDS = 10;

// Le nom de domaine ne doit jamais être coupé sur deux lignes
const DOMAIN = "app.kayak-polo.info";
const withDomain = (text: string) => {
  const [before, after] = text.split(DOMAIN);
  if (after === undefined) return text;
  return (
    <>
      {before}
      <span style={{ whiteSpace: "nowrap" }}>{DOMAIN}</span>
      {after}
    </>
  );
};

const useEnter = (delayFrames = 0) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return spring({ frame: frame - delayFrames, fps, config: { damping: 200 } });
};

const Background: React.FC = () => (
  <AbsoluteFill style={{ backgroundColor: NOIR }}>
    <div
      style={{
        position: "absolute",
        top: 0,
        left: 0,
        right: 0,
        height: 24,
        background: `linear-gradient(90deg, ${BLEU_FONCE}, ${BLEU})`,
      }}
    />
  </AbsoluteFill>
);

const Hook: React.FC<InstallProps> = ({ lang }) => {
  const t = TEXTS[lang];
  const k = useEnter(0);
  const l1 = useEnter(6);
  const l2 = useEnter(14);
  const sub = useEnter(26);
  const rise = (p: number) => interpolate(p, [0, 1], [60, 0]);

  return (
    <AbsoluteFill style={{ justifyContent: "center", padding: `0 ${SAFE_X}px`, color: "white" }}>
      <div
        style={{
          color: BLEU,
          fontSize: 42,
          fontWeight: 800,
          letterSpacing: "0.06em",
          textTransform: "uppercase",
          opacity: k,
          transform: `translateY(${rise(k)}px)`,
        }}
      >
        {t.kicker}
      </div>
      <div
        style={{
          fontSize: 124,
          fontWeight: 800,
          lineHeight: 1.05,
          marginTop: 40,
          opacity: l1,
          transform: `translateY(${rise(l1)}px)`,
        }}
      >
        {t.hook1}
      </div>
      <div
        style={{
          fontSize: 124,
          fontWeight: 800,
          lineHeight: 1.05,
          color: BLEU,
          opacity: l2,
          transform: `translateY(${rise(l2)}px)`,
        }}
      >
        {t.hook2}
      </div>
      <div style={{ fontSize: 56, fontWeight: 500, color: GRIS, marginTop: 50, lineHeight: 1.3, opacity: sub }}>
        {t.hookSub}
      </div>
    </AbsoluteFill>
  );
};

// ----- Icônes dessinées (les interfaces de Safari et Chrome ne sont pas des captures) -----
const ICON = 104;

const ShareIcon: React.FC = () => (
  <svg width={ICON} height={ICON} viewBox="0 0 24 24" fill="none" stroke={BLEU} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
    <path d="M12 15V3" />
    <path d="M8 7l4-4 4 4" />
    <path d="M7 10H6a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-7a2 2 0 0 0-2-2h-1" />
  </svg>
);
const PlusSquareIcon: React.FC = () => (
  <svg width={ICON} height={ICON} viewBox="0 0 24 24" fill="none" stroke={BLEU} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
    <rect x="3" y="3" width="18" height="18" rx="4" />
    <path d="M12 8v8M8 12h8" />
  </svg>
);
const DotsIcon: React.FC = () => (
  <svg width={ICON} height={ICON} viewBox="0 0 24 24" fill={BLEU}>
    <circle cx="12" cy="5" r="2" />
    <circle cx="12" cy="12" r="2" />
    <circle cx="12" cy="19" r="2" />
  </svg>
);
const GlobeIcon: React.FC = () => (
  <svg width={ICON} height={ICON} viewBox="0 0 24 24" fill="none" stroke={BLEU} strokeWidth={1.8} strokeLinecap="round" strokeLinejoin="round">
    <circle cx="12" cy="12" r="9" />
    <path d="M3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18" />
  </svg>
);
const CheckIcon: React.FC = () => (
  <svg width={ICON} height={ICON} viewBox="0 0 24 24" fill="none" stroke={BLEU} strokeWidth={2.2} strokeLinecap="round" strokeLinejoin="round">
    <circle cx="12" cy="12" r="9" />
    <path d="M8 12.5l3 3 5-6" />
  </svg>
);

const IPHONE_ICONS = [GlobeIcon, ShareIcon, PlusSquareIcon, CheckIcon];
const ANDROID_ICONS = [GlobeIcon, DotsIcon, PlusSquareIcon, CheckIcon];

const Steps: React.FC<{ title: string; steps: readonly string[]; icons: React.FC[] }> = ({
  title,
  steps,
  icons,
}) => {
  const head = useEnter(0);
  const frame = useCurrentFrame();
  const { fps, durationInFrames } = useVideoConfig();
  // les étapes arrivent à intervalle régulier dans la durée de la scène
  const interval = Math.floor((durationInFrames - 1.2 * fps) / steps.length);

  return (
    <AbsoluteFill style={{ padding: `330px ${SAFE_X}px 0`, color: "white" }}>
      <div
        style={{
          fontSize: 110,
          fontWeight: 800,
          color: BLEU,
          opacity: head,
          transform: `translateY(${interpolate(head, [0, 1], [50, 0])}px)`,
        }}
      >
        {title}
      </div>
      <div style={{ marginTop: 110, display: "flex", flexDirection: "column", gap: 80 }}>
        {steps.map((text, i) => {
          const p = spring({ frame: frame - (0.6 * fps + i * interval), fps, config: { damping: 200 } });
          const Icon = icons[i];
          return (
            <div
              key={text}
              style={{
                display: "flex",
                alignItems: "center",
                gap: 36,
                opacity: p,
                transform: `translateX(${interpolate(p, [0, 1], [80, 0])}px)`,
              }}
            >
              <div
                style={{
                  flex: "0 0 auto",
                  width: 140,
                  height: 140,
                  borderRadius: 70,
                  backgroundColor: ROUGE,
                  fontSize: 76,
                  fontWeight: 800,
                  display: "flex",
                  alignItems: "center",
                  justifyContent: "center",
                }}
              >
                {i + 1}
              </div>
              <div style={{ flex: 1, fontSize: 58, fontWeight: 800, lineHeight: 1.2 }}>{withDomain(text)}</div>
              <div style={{ flex: "0 0 auto" }}>
                <Icon />
              </div>
            </div>
          );
        })}
      </div>
    </AbsoluteFill>
  );
};

// ----- Démonstration hors ligne : extrait de l'enregistrement Playwright -----
const PHONE_WIDTH = 600;
const PHONE_HEIGHT = Math.round((PHONE_WIDTH * 960) / 540);

const Offline: React.FC<InstallProps> = ({ lang }) => {
  const t = TEXTS[lang];
  const { fps } = useVideoConfig();
  const a = useEnter(0);
  const b = useEnter(8);
  const phone = useEnter(14);

  return (
    <AbsoluteFill style={{ alignItems: "center", color: "white" }}>
      <div
        style={{
          marginTop: 150,
          fontSize: 100,
          fontWeight: 800,
          opacity: a,
          transform: `translateY(${interpolate(a, [0, 1], [50, 0])}px)`,
        }}
      >
        {t.offlineTitle}
      </div>
      <div
        style={{
          marginTop: 24,
          padding: `0 ${SAFE_X}px`,
          textAlign: "center",
          fontSize: 48,
          fontWeight: 500,
          color: GRIS,
          lineHeight: 1.3,
          opacity: b,
        }}
      >
        {t.offlineSub}
      </div>
      <div
        style={{
          marginTop: 70,
          width: PHONE_WIDTH,
          height: PHONE_HEIGHT,
          borderRadius: 48,
          overflow: "hidden",
          border: `10px solid ${BLEU_FONCE}`,
          boxShadow: "0 30px 80px rgba(0,0,0,0.6)",
          opacity: phone,
          transform: `translateY(${interpolate(phone, [0, 1], [80, 0])}px)`,
        }}
      >
        <OffthreadVideo
          src={staticFile(lang === "en" ? "demos/offline-en.mp4" : "demos/offline.mp4")}
          startFrom={OFFLINE_CLIP_START_SECONDS * fps}
          muted
          style={{ width: PHONE_WIDTH, height: PHONE_HEIGHT, objectFit: "cover" }}
        />
      </div>
    </AbsoluteFill>
  );
};

const Cta: React.FC<InstallProps> = ({ lang }) => {
  const t = TEXTS[lang];
  const a = useEnter(0);
  const b = useEnter(8);
  const c = useEnter(18);

  return (
    <AbsoluteFill
      style={{ alignItems: "center", justifyContent: "center", padding: `0 ${SAFE_X}px`, color: "white", textAlign: "center" }}
    >
      <div
        style={{
          fontSize: 100,
          fontWeight: 800,
          lineHeight: 1.1,
          opacity: a,
          transform: `translateY(${interpolate(a, [0, 1], [60, 0])}px)`,
        }}
      >
        {t.ctaTitle}
      </div>
      <div style={{ fontSize: 48, fontWeight: 500, color: GRIS, marginTop: 30, lineHeight: 1.3, opacity: b }}>
        {t.ctaSub}
      </div>
      <Img
        src={staticFile("qr-application-bord-de-terrain.png")}
        style={{
          width: 420,
          height: 420,
          marginTop: 80,
          padding: 24,
          borderRadius: 32,
          backgroundColor: "white",
          opacity: c,
          transform: `scale(${interpolate(c, [0, 1], [0.85, 1])})`,
        }}
      />
      <div style={{ fontSize: 56, fontWeight: 800, marginTop: 60, opacity: c }}>app.kayak-polo.info</div>
      <div
        style={{
          marginTop: 40,
          padding: "14px 40px",
          borderRadius: 60,
          backgroundColor: ROUGE,
          fontSize: 44,
          fontWeight: 800,
          opacity: c,
        }}
      >
        {t.hashtag}
      </div>
    </AbsoluteFill>
  );
};

export const InstallReel: React.FC<InstallProps> = ({ lang }) => {
  const { fps } = useVideoConfig();
  const t = TEXTS[lang];
  let from = 0;
  const scene = (seconds: number, node: React.ReactNode, key: string) => {
    const el = (
      <Sequence key={key} from={from} durationInFrames={seconds * fps} premountFor={fps}>
        {node}
      </Sequence>
    );
    from += seconds * fps;
    return el;
  };

  return (
    <AbsoluteFill style={{ fontFamily }}>
      <Background />
      {scene(SCENE_SECONDS.hook, <Hook lang={lang} />, "hook")}
      {scene(SCENE_SECONDS.iphone, <Steps title={t.iphone} steps={t.iphoneSteps} icons={IPHONE_ICONS} />, "iphone")}
      {scene(SCENE_SECONDS.android, <Steps title={t.android} steps={t.androidSteps} icons={ANDROID_ICONS} />, "android")}
      {scene(SCENE_SECONDS.offline, <Offline lang={lang} />, "offline")}
      {scene(SCENE_SECONDS.cta, <Cta lang={lang} />, "cta")}
    </AbsoluteFill>
  );
};
