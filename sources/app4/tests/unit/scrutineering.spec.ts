import { describe, expect, it } from 'vitest'
import {
  adjacentTeams, canManageScrutineering, canViewTeams, canWriteTeamPresence, controlPlayers, isScrutineerProfile, nextEquipmentValue,
  contextEventId, contextParams, resolveContext, sameClubTeams, teamsByCompetition, totalValidPaddles,
} from '../../utils/scrutineering'
import type { ScrutineeringPlayer, ScrutineeringTeam } from '../../types/scrutineering'

const team = (id: number, club: string, competition = 'SCA'): ScrutineeringTeam => ({
  team_id: id, label: `Team ${id}`, club, club_label: club, logo: null,
  competition_code: competition, competition_label: competition, season: '2997', locked: false,
})
const player = (id: number, cap: string, paddles = 0): ScrutineeringPlayer => ({
  player_id: id, last_name: 'N', first_name: 'P', num: id, cap,
  kayak_status: 0, vest_status: 0, helmet_status: 0, paddle_count: paddles, comment: '',
})

describe('rights', () => {
  it('ADM-SCR-02: profiles 1–2 and the profile 10 manage the scrutineering', () => {
    expect([1, 2, 3, 7, 9, 10].map(canManageScrutineering)).toEqual([true, true, false, false, false, true])
    expect(isScrutineerProfile(10)).toBe(true)
    expect(isScrutineerProfile(2)).toBe(false)
  })

  it('ADM-SCR-01: the teams page is open to profiles ≤ 8 and the profile 10', () => {
    expect([8, 9, 10].map(canViewTeams)).toEqual([true, false, true])
  })

  it('ADM-SCR-05: team presence sheets are written by profiles ≤ 7 and the profile 10', () => {
    expect([7, 8, 9, 10].map(canWriteTeamPresence)).toEqual([true, false, false, true])
  })
})

describe('equipment control', () => {
  it('ADM-SCR-08: statuses cycle 0→4→0 and paddles 0→6→0', () => {
    expect(nextEquipmentValue('kayak_status', 0)).toBe(1)
    expect(nextEquipmentValue('helmet_status', 4)).toBe(0)
    expect(nextEquipmentValue('paddle_count', 5)).toBe(6)
    expect(nextEquipmentValue('paddle_count', 6)).toBe(0)
  })

  it('ADM-SCR-09: the valid paddles total ignores the staff', () => {
    expect(totalValidPaddles([player(1, 'C', 2), player(2, '-', 3), player(3, 'E', 4)])).toBe(5)
  })

  it('ADM-SCR-10: referees and inactive players are not controlled', () => {
    expect(controlPlayers([player(1, '-'), player(2, 'A'), player(3, 'X'), player(4, 'E')]).map(p => p.player_id)).toEqual([1, 4])
  })
})

describe('team navigation', () => {
  const teams = [team(1, 'C001'), team(2, 'C002'), team(3, 'GER'), team(4, 'C001', 'SCB'), team(5, 'GER', 'SCB')]

  it('ADM-SCR-15: other teams of the same club / nation, every competition', () => {
    expect(sameClubTeams(teams, 1).map(t => t.team_id)).toEqual([4])
    expect(sameClubTeams(teams, 3).map(t => t.team_id)).toEqual([5])
  })

  it('ADM-SCR-17: previous and next teams follow the selector order', () => {
    expect(adjacentTeams(teams, 1)).toEqual({ previous: null, next: teams[1] })
    expect(adjacentTeams(teams, 3)).toEqual({ previous: teams[1], next: teams[3] })
    expect(adjacentTeams(teams, 99)).toEqual({ previous: null, next: null })
  })

  it('groups the selector by competition, in the order of the list', () => {
    expect(teamsByCompetition(teams).map(group => [group.code, group.teams.map(t => t.team_id)])).toEqual([['SCA', [1, 2, 3]], ['SCB', [4, 5]]])
  })

})

describe('context (§ 1.3)', () => {
  it('ADM-SCR-23: the event filter of the page, else the page competition of the season', () => {
    expect(resolveContext('event:80', '2997', 'SCA')).toEqual({ event: 80 })
    expect(resolveContext('group:TST', '2997', 'SCC')).toEqual({ season: '2997', competition: 'SCC' })
    expect(resolveContext('', '2997', 'SCC')).toEqual({ season: '2997', competition: 'SCC' })
    expect(resolveContext('', '2997', '')).toBeNull()
    expect(resolveContext('', '', 'SCC')).toBeNull()
  })

  it('ADM-SCR-22: the query of a context, and the event of the journal only in an event context', () => {
    expect(contextParams({ event: 80 })).toEqual({ event: 80 })
    expect(contextParams({ season: '2997', competition: 'SCC' })).toEqual({ season: '2997', competition: 'SCC' })
    expect(contextEventId({ event: 80 })).toBe(80)
    expect(contextEventId({ season: '2997', competition: 'SCC' })).toBeNull()
    expect(contextEventId(null)).toBeNull()
  })
})
