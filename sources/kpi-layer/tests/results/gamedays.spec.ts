import { describe, expect, it } from 'vitest'
import { groupGamedays } from '../../app/utils/results/gamedays'
import type { Gameday } from '../../app/utils/results/types'

const officials = { rc: 'RC', r1: 'R1', delegate: 'Del', chief_referee: 'Chef' }
const gameday = (id: number, phase: string, overrides: Partial<Gameday> = {}): Gameday => ({
  id, name: phase, phase, start: '2026-06-20', end: '2026-06-21', place: 'Thury-Harcourt', department: 'FRA',
  organizer: 'Thury', officials, ...overrides,
})

describe('groupGamedays', () => {
  it('CMP-08: gamedays sharing every parameter (phases of a cup) are shown once', () => {
    const groups = groupGamedays([gameday(1, 'Group UW'), gameday(2, 'Group UX'), gameday(3, '7th place')])
    expect(groups).toHaveLength(1)
    expect(groups[0]!.gamedays.map(item => item.id)).toEqual([1, 2, 3])
    expect(groups[0]!.phases).toEqual(['Group UW', 'Group UX', '7th place'])
  })

  it('CMP-08: gamedays with different parameters (a championship) stay separate, in order', () => {
    const groups = groupGamedays([
      gameday(1, 'J1', { place: 'Lacville' }),
      gameday(2, 'J2', { place: 'Rivecity', start: '2026-07-01', end: '2026-07-02' }),
    ])
    expect(groups.map(group => group.gamedays.map(item => item.id))).toEqual([[1], [2]])
  })

  it('CMP-08: groups the phases that share parameters even when others differ', () => {
    const groups = groupGamedays([
      gameday(1, 'Poule A'),
      gameday(2, 'Finale', { start: '2026-06-22', end: '2026-06-22' }),
      gameday(3, 'Poule B'),
    ])
    expect(groups.map(group => group.phases)).toEqual([['Poule A', 'Poule B'], ['Finale']])
  })

  it('does not list a phase twice and ignores missing phase names', () => {
    expect(groupGamedays([gameday(1, 'Poule A'), gameday(2, 'Poule A'), gameday(3, '', { phase: null, name: null })])[0]!.phases).toEqual(['Poule A'])
  })

  it('no gameday, no group', () => {
    expect(groupGamedays([])).toEqual([])
  })
})
