import { describe, expect, it } from 'vitest'
import { canCopyTeamComposition } from '../../utils/presencePermissions'

const openSheet = { locked: false, inPerimeter: true }

describe('team presence sheet: copy from another competition', () => {
  it('profiles 4, 7 and 10 copy on a team of their perimeter; profiles 8 and 9 never do', () => {
    expect([1, 4, 5, 6, 7, 8, 9, 10].map(profile => canCopyTeamComposition(profile, openSheet)))
      .toEqual([true, true, true, true, true, false, false, true])
  })

  it('a team outside the clubs or competitions of the user has no copy', () => {
    expect([4, 7, 10].map(profile => canCopyTeamComposition(profile, { locked: false, inPerimeter: false })))
      .toEqual([false, false, false])
  })

  it('a locked competition has no copy', () => {
    expect([4, 7, 10].map(profile => canCopyTeamComposition(profile, { locked: true, inPerimeter: true })))
      .toEqual([false, false, false])
  })
})
