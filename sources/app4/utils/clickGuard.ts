/**
 * Click debounce for the scoring console keys: a press held too long or too hard (worn
 * mouse switch, double contact, touch screen) must count once. Returns `accept(key)`, true
 * when the click for that button (`key`) is taken into account, false when it falls within
 * `minIntervalMs` of the last accepted one. Each button has its own window.
 */
export function createClickGuard(minIntervalMs: number, now: () => number = Date.now) {
  const lastAccepted = new Map<string, number>()
  return (key: string): boolean => {
    const t = now()
    const last = lastAccepted.get(key)
    if (last !== undefined && t - last < minIntervalMs) return false
    lastAccepted.set(key, t)
    return true
  }
}
