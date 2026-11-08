/**
 * Buzzer for the scoring console (spec §6.5/§7.5): sounds at shotclock expiry, period end
 * and end of inter-period breaks (decision §0.9). A penalty expiry only gets a short `beep()`
 * (§0.15): a buzzer there would be taken for the game clock or the shot clock. Plays the buzzer of the
 * legacy online score sheet (FeuilleMarque3, `img/buzzeer-180942.mp3`), copied into
 * `public/sounds/` so it is served under /admin2 and kept by the PWA cache.
 *
 * Web Audio rather than <audio>: overlapping buzzers (shotclock + period end at the same
 * time) both play, with no start latency. Browsers only let audio start after a user
 * gesture, so the context is unlocked — and the sound decoded — on the first click or key
 * in the page; a buzzer due before any gesture is skipped by the browser either way. When
 * the file cannot be loaded (offline before it was ever cached), a synthesized square-wave
 * blast stands in.
 */
const BUZZER_PATH = 'sounds/buzzer.mp3'
const FALLBACK_MS = 900
const FALLBACK_HZ = 660
/** Penalty expiry: two short high tones, nothing like the buzzer. */
const BEEP_MS = 120
const BEEP_GAP_MS = 80
const BEEP_HZ = 1320

export function useBuzzer() {
  const soundUrl = `${useRuntimeConfig().app.baseURL}${BUZZER_PATH}`
  let ctx: AudioContext | null = null
  let buffer: AudioBuffer | null = null
  let loading: Promise<void> | null = null

  const ensureContext = (): AudioContext | null => {
    if (typeof window === 'undefined') return null
    ctx ??= new AudioContext()
    if (ctx.state === 'suspended') void ctx.resume()
    return ctx
  }

  const load = (audio: AudioContext): Promise<void> => {
    loading ??= fetch(soundUrl)
      .then(r => r.ok ? r.arrayBuffer() : Promise.reject(new Error(`buzzer ${r.status}`)))
      .then(data => audio.decodeAudioData(data))
      .then((decoded) => { buffer = decoded })
      .catch(() => { loading = null }) // retried on the next play
    return loading
  }

  const tone = (audio: AudioContext, type: OscillatorType, hz: number, ms: number, delayMs = 0) => {
    const at = audio.currentTime + delayMs / 1000
    const osc = audio.createOscillator()
    const gain = audio.createGain()
    osc.type = type
    osc.frequency.value = hz
    gain.gain.setValueAtTime(0.4, at)
    gain.gain.exponentialRampToValueAtTime(0.001, at + ms / 1000)
    osc.connect(gain).connect(audio.destination)
    osc.start(at)
    osc.stop(at + ms / 1000)
  }
  const synthesized = (audio: AudioContext) => tone(audio, 'square', FALLBACK_HZ, FALLBACK_MS)

  /** Sound the buzzer (the legacy one; the synthesized blast while it is not loaded). */
  const play = () => {
    const audio = ensureContext()
    if (!audio) return
    if (!buffer) {
      void load(audio)
      synthesized(audio)
      return
    }
    const source = audio.createBufferSource()
    source.buffer = buffer
    source.connect(audio.destination)
    source.start()
  }

  /** Short double beep (penalty expiry) — distinct from the buzzer. */
  const beep = () => {
    const audio = ensureContext()
    if (!audio) return
    tone(audio, 'sine', BEEP_HZ, BEEP_MS)
    tone(audio, 'sine', BEEP_HZ, BEEP_MS, BEEP_MS + BEEP_GAP_MS)
  }

  /** The console's "test sound" button: the real buzzer, as in the legacy sheet. */
  const test = () => play()

  // Unlock audio and decode the sound on the first gesture, so the first buzzer is on time.
  const prime = () => {
    const audio = ensureContext()
    if (audio) void load(audio)
  }
  onMounted(() => {
    window.addEventListener('pointerdown', prime, { once: true, capture: true })
    window.addEventListener('keydown', prime, { once: true, capture: true })
  })
  onUnmounted(() => {
    window.removeEventListener('pointerdown', prime, { capture: true })
    window.removeEventListener('keydown', prime, { capture: true })
  })

  return { play, beep, test }
}
