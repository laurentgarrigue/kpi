import { Composition } from "remotion";
import { InstallReel, TOTAL_SECONDS } from "./Composition";

const FPS = 30;

export const RemotionRoot: React.FC = () => {
  return (
    <>
      <Composition
        id="InstallFR"
        component={InstallReel}
        defaultProps={{ lang: "fr" as const }}
        durationInFrames={TOTAL_SECONDS * FPS}
        fps={FPS}
        width={1080}
        height={1920}
      />
      <Composition
        id="InstallEN"
        component={InstallReel}
        defaultProps={{ lang: "en" as const }}
        durationInFrames={TOTAL_SECONDS * FPS}
        fps={FPS}
        width={1080}
        height={1920}
      />
    </>
  );
};
