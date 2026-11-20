import {
  AbsoluteFill,
  cancelRender,
  continueRender,
  delayRender,
  Img,
  interpolate,
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

export type FriseProps = {
  lang: "fr" | "en";
};

const TEXTS = {
  fr: {
    kicker: "L'application bord de terrain",
    title1: "Le match",
    title2: "minute par minute",
    caption: "Chaque but, chaque carton, période par période",
    ctaTitle: "Suis les matchs en direct",
    ctaSub: "Un scan du QR code et tout le plan d'eau suit les scores",
    hashtag: "#KPIsaison2027",
  },
  en: {
    kicker: "The matchday app",
    title1: "Every game,",
    title2: "minute by minute",
    caption: "Every goal, every card, period by period",
    ctaTitle: "Follow the games live",
    ctaSub: "Scan the QR code and follow every score from the waterside",
    hashtag: "#canoepolo",
  },
} as const;

const SAFE_X = 80;

// Durées (en secondes) des trois scènes
export const SCENE_SECONDS = { hook: 3, frise: 6, cta: 4 } as const;
export const TOTAL_SECONDS =
  SCENE_SECONDS.hook + SCENE_SECONDS.frise + SCENE_SECONDS.cta;

const useEnter = (delayFrames = 0) => {
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  return spring({ frame: frame - delayFrames, fps, config: { damping: 200 } });
};

const Background: React.FC = () => (
  <AbsoluteFill style={{ backgroundColor: NOIR, fontFamily }}>
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

const Hook: React.FC<FriseProps> = ({ lang }) => {
  const t = TEXTS[lang];
  const kicker = useEnter(0);
  const l1 = useEnter(6);
  const l2 = useEnter(14);
  const rise = (p: number) => interpolate(p, [0, 1], [60, 0]);

  return (
    <AbsoluteFill
      style={{
        justifyContent: "center",
        padding: `0 ${SAFE_X}px`,
        color: "white",
      }}
    >
      <div
        style={{
          color: BLEU,
          fontSize: 42,
          fontWeight: 800,
          letterSpacing: "0.06em",
          textTransform: "uppercase",
          opacity: kicker,
          transform: `translateY(${rise(kicker)}px)`,
        }}
      >
        {t.kicker}
      </div>
      <div
        style={{
          fontSize: 150,
          fontWeight: 800,
          lineHeight: 1.05,
          marginTop: 40,
          opacity: l1,
          transform: `translateY(${rise(l1)}px)`,
        }}
      >
        {t.title1}
      </div>
      <div
        style={{
          fontSize: 150,
          fontWeight: 800,
          lineHeight: 1.05,
          color: BLEU,
          opacity: l2,
          transform: `translateY(${rise(l2)}px)`,
        }}
      >
        {t.title2}
      </div>
    </AbsoluteFill>
  );
};

// La capture (1479×1536) est affichée en largeur 920 ; elle se dévoile de haut en bas :
// d'abord la frise des périodes, puis les événements un par un
const SHOT_WIDTH = 920;
// Capture propre à chaque langue (interface de l'application dans la langue de la vidéo)
const SHOTS = {
  fr: { file: "bord-de-terrain-frise-match.png", width: 1479, height: 1536 },
  en: { file: "bord-de-terrain-frise-match-en.png", width: 1524, height: 1430 },
} as const;
const TIMELINE_SHARE = 0.38; // part haute de la capture occupée par la frise graphique

const Frise: React.FC<FriseProps> = ({ lang }) => {
  const t = TEXTS[lang];
  const frame = useCurrentFrame();
  const { fps } = useVideoConfig();
  const enter = useEnter(0);
  const shot = SHOTS[lang];
  const shotHeight = Math.round((SHOT_WIDTH * shot.height) / shot.width);

  const revealed = interpolate(
    frame,
    [0.8 * fps, 1.6 * fps, 2 * fps, 4.6 * fps],
    [0, TIMELINE_SHARE, TIMELINE_SHARE, 1],
    { extrapolateLeft: "clamp", extrapolateRight: "clamp" },
  );

  return (
    <AbsoluteFill style={{ alignItems: "center", color: "white" }}>
      <div
        style={{
          marginTop: 220,
          padding: `0 ${SAFE_X}px`,
          textAlign: "center",
          fontSize: 68,
          fontWeight: 800,
          lineHeight: 1.2,
          opacity: enter,
        }}
      >
        {t.caption}
      </div>
      <div
        style={{
          marginTop: 110,
          width: SHOT_WIDTH,
          height: shotHeight,
          borderRadius: 36,
          overflow: "hidden",
          border: `8px solid ${BLEU_FONCE}`,
          boxShadow: "0 30px 80px rgba(0,0,0,0.6)",
          opacity: enter,
          transform: `translateY(${interpolate(enter, [0, 1], [80, 0])}px)`,
          clipPath: `inset(0 0 ${(1 - revealed) * 100}% 0)`,
        }}
      >
        <Img
          src={staticFile(shot.file)}
          style={{ width: SHOT_WIDTH, height: shotHeight, display: "block" }}
        />
      </div>
    </AbsoluteFill>
  );
};

const Cta: React.FC<FriseProps> = ({ lang }) => {
  const t = TEXTS[lang];
  const a = useEnter(0);
  const b = useEnter(8);
  const c = useEnter(18);

  return (
    <AbsoluteFill
      style={{
        alignItems: "center",
        justifyContent: "center",
        padding: `0 ${SAFE_X}px`,
        color: "white",
        textAlign: "center",
      }}
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
      <div
        style={{
          fontSize: 48,
          fontWeight: 500,
          color: GRIS,
          marginTop: 30,
          lineHeight: 1.3,
          opacity: b,
        }}
      >
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
      <div
        style={{
          fontSize: 56,
          fontWeight: 800,
          marginTop: 60,
          opacity: c,
        }}
      >
        app.kayak-polo.info
      </div>
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

export const FriseReel: React.FC<FriseProps> = ({ lang }) => {
  const { fps } = useVideoConfig();
  const hook = SCENE_SECONDS.hook * fps;
  const frise = SCENE_SECONDS.frise * fps;
  const cta = SCENE_SECONDS.cta * fps;

  return (
    <AbsoluteFill style={{ fontFamily }}>
      <Background />
      <Sequence durationInFrames={hook} premountFor={fps}>
        <Hook lang={lang} />
      </Sequence>
      <Sequence from={hook} durationInFrames={frise} premountFor={fps}>
        <Frise lang={lang} />
      </Sequence>
      <Sequence from={hook + frise} durationInFrames={cta} premountFor={fps}>
        <Cta lang={lang} />
      </Sequence>
    </AbsoluteFill>
  );
};
