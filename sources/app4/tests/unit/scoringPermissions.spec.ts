import { computed, ref } from 'vue'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { useScoringPermissions } from '../../composables/useScoringPermissions'

/** Scoring console access: profiles 1 to 6 and 9; validation / lock stay on profiles <= 6 (PAGE_SCORING.md §6.3). */
const permissionsOf = (profile: number, locked = false) => {
  vi.stubGlobal('computed', computed)
  vi.stubGlobal('useAuthStore', () => ({ profile }))
  return useScoringPermissions(ref(locked))
}

describe('Scoring permissions', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('profiles 1 to 6 and 9 open the console and score', () => {
    for (const profile of [1, 2, 3, 4, 5, 6, 9]) {
      const permissions = permissionsOf(profile)
      expect([profile, permissions.canView.value, permissions.canScore.value, permissions.canManagePlayers.value])
        .toEqual([profile, true, true, true])
    }
  })

  it('profiles 7, 8 and 10 have no access', () => {
    for (const profile of [7, 8, 10]) {
      const permissions = permissionsOf(profile)
      expect([profile, permissions.canView.value, permissions.canScore.value, permissions.canValidate.value])
        .toEqual([profile, false, false, false])
    }
  })

  it('the score table (9) scores but neither validates nor locks', () => {
    const permissions = permissionsOf(9)
    expect([permissions.canValidate.value, permissions.canLock.value]).toEqual([false, false])
  })

  it('profiles 1 to 6 validate and lock', () => {
    for (const profile of [1, 6]) {
      const permissions = permissionsOf(profile)
      expect([permissions.canValidate.value, permissions.canLock.value]).toEqual([true, true])
    }
  })

  it('a locked match is read-only for everyone', () => {
    const permissions = permissionsOf(1, true)
    expect([permissions.canView.value, permissions.canScore.value, permissions.canManagePlayers.value])
      .toEqual([true, false, false])
  })
})
