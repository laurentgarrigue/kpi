/**
 * Fit a single line of text to the largest size its container can hold — whatever the
 * screen's size and shape (spec §6.1: the scoreboard and above all the shot clock are
 * shown on non-standard displays — outdoor LED panels, poolside satellites, portrait
 * screens…). No media query can cover that; the size is measured, not guessed.
 *
 * How it works
 *  - glyph metrics are measured on a canvas at a reference size, using
 *    `actualBoundingBoxAscent/Descent` so the **ink** of the digits fills the screen
 *    (the CSS line box is ~40 % taller than the digits and would waste that space);
 *  - the size is computed against a set of *candidate* strings, not only the current one:
 *    passing ['60', '8.8'] keeps the shot clock digits **stable** when the display
 *    switches from seconds to tenths, instead of jumping at 10 s;
 *  - a ResizeObserver recomputes on any container resize (rotation, LED wall resolution
 *    change, window resize), so the page never needs a reload.
 *
 * The container must carry the font styles (family/weight/style): they are read from its
 * computed style so the measurement matches what is painted.
 */
export function useFitText(
  container: Ref<HTMLElement | null>,
  candidates: () => string[],
  options: { fill?: number, maxPx?: number } = {}
) {
  /** Font size in px to bind on the text element. */
  const fontSize = ref(16)

  const REFERENCE_PX = 100
  let observer: ResizeObserver | null = null
  let canvasCtx: CanvasRenderingContext2D | null = null

  const context = (): CanvasRenderingContext2D | null => {
    if (canvasCtx || typeof document === 'undefined') return canvasCtx
    canvasCtx = document.createElement('canvas').getContext('2d')
    return canvasCtx
  }

  const fit = () => {
    const el = container.value
    const ctx = context()
    if (!el || !ctx) return

    const width = el.clientWidth
    const height = el.clientHeight
    if (width === 0 || height === 0) return

    const style = getComputedStyle(el)
    ctx.font = `${style.fontStyle} ${style.fontWeight} ${REFERENCE_PX}px ${style.fontFamily}`
    // Chromium honours letter-spacing on canvas; elsewhere it is ignored (spacing is 0
    // on the display pages, so the measurement stays faithful).
    if ('letterSpacing' in ctx) {
      (ctx as CanvasRenderingContext2D & { letterSpacing: string }).letterSpacing = style.letterSpacing
    }

    let inkWidth = 0
    let inkHeight = 0
    for (const text of candidates()) {
      if (!text) continue
      const m = ctx.measureText(text)
      const h = (m.actualBoundingBoxAscent || 0) + (m.actualBoundingBoxDescent || 0)
      inkWidth = Math.max(inkWidth, m.width)
      // Fallback for engines without ink metrics: the em box is a safe approximation.
      inkHeight = Math.max(inkHeight, h || REFERENCE_PX * 0.72)
    }
    if (inkWidth === 0 || inkHeight === 0) return

    const fill = options.fill ?? 0.94
    const size = Math.floor(
      REFERENCE_PX * Math.min((width * fill) / inkWidth, (height * fill) / inkHeight)
    )
    fontSize.value = Math.max(8, options.maxPx ? Math.min(size, options.maxPx) : size)
  }

  onMounted(() => {
    fit()
    if (container.value && typeof ResizeObserver !== 'undefined') {
      observer = new ResizeObserver(fit)
      observer.observe(container.value)
    }
    // Fonts may load after the first paint and change the metrics.
    if (typeof document !== 'undefined' && 'fonts' in document) {
      void (document as Document & { fonts: FontFaceSet }).fonts.ready.then(fit)
    }
  })

  // Recompute when the candidate set changes (e.g. the game clock is toggled on).
  watch(() => candidates().join('|'), () => nextTick(fit))

  onUnmounted(() => {
    observer?.disconnect()
    observer = null
  })

  return { fontSize, fit }
}
