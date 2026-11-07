import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createLiveConnectionWatch, LIVE_RECONNECT_GRACE_MS } from '../../utils/scoringLiveConnection'

/**
 * Bandeau « Connexion perdue » de la console (PAGE_SCORING.md, revue UX « Clarté », révisé le 2026-11-07).
 * Le hub Mercure recycle chaque abonnement toutes les ~8–10 min ; le navigateur se réabonne ~5 s
 * plus tard. Seule une coupure qui dure au-delà du délai de grâce doit alerter l'opérateur.
 */
describe('createLiveConnectionWatch — délai de grâce avant « Connexion perdue »', () => {
  beforeEach(() => { vi.useFakeTimers() })
  afterEach(() => { vi.useRealTimers() })

  it('fixe le délai de grâce à 10 s', () => {
    expect(LIVE_RECONNECT_GRACE_MS).toBe(10_000)
  })

  it('ne signale rien si l’abonnement se rétablit avant la fin du délai (recyclage du hub)', () => {
    const onLostChange = vi.fn()
    const watch = createLiveConnectionWatch(onLostChange)
    watch.open()
    watch.error()
    vi.advanceTimersByTime(5_000)
    watch.open()
    vi.advanceTimersByTime(LIVE_RECONNECT_GRACE_MS)
    expect(onLostChange).not.toHaveBeenCalled()
  })

  it('signale la perte quand l’abonnement reste en erreur au-delà du délai', () => {
    const onLostChange = vi.fn()
    const watch = createLiveConnectionWatch(onLostChange)
    watch.open()
    watch.error()
    vi.advanceTimersByTime(LIVE_RECONNECT_GRACE_MS - 1)
    expect(onLostChange).not.toHaveBeenCalled()
    vi.advanceTimersByTime(1)
    expect(onLostChange).toHaveBeenCalledExactlyOnceWith(true)
  })

  it('compte le délai depuis la première erreur, pas depuis la dernière (le navigateur en émet une par tentative)', () => {
    const onLostChange = vi.fn()
    const watch = createLiveConnectionWatch(onLostChange)
    watch.error()
    vi.advanceTimersByTime(6_000)
    watch.error()
    vi.advanceTimersByTime(4_000)
    expect(onLostChange).toHaveBeenCalledExactlyOnceWith(true)
  })

  it('retire le signalement au rétablissement', () => {
    const onLostChange = vi.fn()
    const watch = createLiveConnectionWatch(onLostChange)
    watch.error()
    vi.advanceTimersByTime(LIVE_RECONNECT_GRACE_MS)
    watch.open()
    expect(onLostChange).toHaveBeenLastCalledWith(false)
    expect(onLostChange).toHaveBeenCalledTimes(2)
  })

  it('distingue le premier abonnement d’un réabonnement (la console relit alors l’état)', () => {
    const watch = createLiveConnectionWatch(() => {})
    expect(watch.open()).toEqual({ reconnected: false })
    watch.error()
    expect(watch.open()).toEqual({ reconnected: true })
  })

  it('ne signale plus rien après dispose()', () => {
    const onLostChange = vi.fn()
    const watch = createLiveConnectionWatch(onLostChange)
    watch.error()
    watch.dispose()
    vi.advanceTimersByTime(LIVE_RECONNECT_GRACE_MS * 2)
    expect(onLostChange).not.toHaveBeenCalled()
  })
})
