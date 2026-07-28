/**
 * Buzzer for the scoring console (spec §6.5/§7.5): sounds at shotclock expiry, period end
 * and end of inter-period breaks (decision §0.9). Pure Web Audio — no asset to load, works
 * offline (PWA). The AudioContext is created lazily on first use (browsers require a user
 * gesture before audio can start).
 */
export function useBuzzer() {
  let ctx: AudioContext | null = null

  const ensureContext = (): AudioContext | null => {
    if (typeof window === 'undefined') return null
    ctx ??= new AudioContext()
    if (ctx.state === 'suspended') void ctx.resume()
    return ctx
  }

  /** Play the buzzer: a square-wave blast, `durationMs` long. */
  const beep = (durationMs = 900, frequency = 660) => {
    const audio = ensureContext()
    if (!audio) return
    const osc = audio.createOscillator()
    const gain = audio.createGain()
    osc.type = 'square'
    osc.frequency.value = frequency
    gain.gain.setValueAtTime(0.4, audio.currentTime)
    gain.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + durationMs / 1000)
    osc.connect(gain).connect(audio.destination)
    osc.start()
    osc.stop(audio.currentTime + durationMs / 1000)
  }

  /** Short test blast (the console's "test son" button). */
  const test = () => beep(300)

  return { beep, test }
}
