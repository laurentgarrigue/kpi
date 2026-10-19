import { describe, expect, it } from 'vitest'
import {
  adjacentTeams, canManageScrutineering, canWriteTeamPresence, controlPlayers, isScrutineerProfile, nextEquipmentValue,
  resolveEventId, sameClubTeams, sameNationTeams, teamsByCompetition, totalValidPaddles,
} from '../../utils/scrutineering'
import type { ScrutineeringPlayer, ScrutineeringTeam } from '../../types/scrutineering'

const team = (id: number, club: string, nation: string, competition = 'SCA'): ScrutineeringTeam => ({
  team_id: id, label: `Team ${id}`, club, club_label: club, nation, logo: null,
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
  const teams = [team(1, 'C001', 'FRA'), team(2, 'C002', 'FRA'), team(3, 'GER', 'GER'), team(4, 'C001', 'FRA', 'SCB'), team(5, 'GER-W', 'GER', 'SCB')]

  it('ADM-SCR-15: other teams of the same club, every competition', () => {
    expect(sameClubTeams(teams, 1).map(t => t.team_id)).toEqual([4])
  })

  it('ADM-SCR-16: other teams of the same nation that are not of the same club', () => {
    expect(sameNationTeams(teams, 3).map(t => t.team_id)).toEqual([5])
    expect(sameNationTeams(teams, 1).map(t => t.team_id)).toEqual([2])
  })

  it('ADM-SCR-17: previous and next teams follow the selector order', () => {
    expect(adjacentTeams(teams, 1)).toEqual({ previous: null, next: teams[1] })
    expect(adjacentTeams(teams, 3)).toEqual({ previous: teams[1], next: teams[3] })
    expect(adjacentTeams(teams, 99)).toEqual({ previous: null, next: null })
  })

  it('groups the selector by competition, in the order of the list', () => {
    expect(teamsByCompetition(teams).map(group => [group.code, group.teams.map(t => t.team_id)])).toEqual([['SCA', [1, 2, 3]], ['SCB', [4, 5]]])
  })

  it('the event comes from the page filter, else from the work context', () => {
    expect(resolveEventId('event:80', 'event', 77)).toBe(80)
    expect(resolveEventId('group:TST', 'event', 77)).toBe(77)
    expect(resolveEventId('', 'selection', 77)).toBeNull()
  })
})
