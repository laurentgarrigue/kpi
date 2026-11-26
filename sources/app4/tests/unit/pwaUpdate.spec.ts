// PWA-06b (DOC/specs/PWA_AUTO_UPDATE.md §8) — what the « reload » button of the update banner does.
import { describe, expect, it } from 'vitest'
import { updateActionFor } from '../../utils/pwaUpdate'

describe('updateActionFor', () => {
  it('activates the waiting worker when the page is controlled (nominal update)', () => {
    expect(updateActionFor({ hasWaitingWorker: true, isControlled: true })).toBe('activate-waiting-worker')
  })

  it('reloads on a first visit: no worker controls the page, the new one is already active', () => {
    expect(updateActionFor({ hasWaitingWorker: false, isControlled: false })).toBe('reload')
  })

  it('reloads when no worker is waiting any more (activated from another tab)', () => {
    expect(updateActionFor({ hasWaitingWorker: false, isControlled: true })).toBe('reload')
  })

  it('reloads when a worker waits but does not control the page (SKIP_WAITING would never reload it)', () => {
    expect(updateActionFor({ hasWaitingWorker: true, isControlled: false })).toBe('reload')
  })
})
