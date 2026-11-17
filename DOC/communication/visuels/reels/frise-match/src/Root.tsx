import { Composition } from "remotion";
import { FriseReel, TOTAL_SECONDS } from "./Composition";

const FPS = 30;

export const RemotionRoot: React.FC = () => {
  return (
    <>
      <Composition
        id="FriseFR"
        component={FriseReel}
        defaultProps={{ lang: "fr" as const }}
        durationInFrames={TOTAL_SECONDS * FPS}
        fps={FPS}
        width={1080}
        height={1920}
      />
      <Composition
        id="FriseEN"
        component={FriseReel}
        defaultProps={{ lang: "en" as const }}
        durationInFrames={TOTAL_SECONDS * FPS}
        fps={FPS}
        width={1080}
        height={1920}
      />
    </>
  );
};
