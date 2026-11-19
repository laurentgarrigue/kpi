// PAGE_SCORING.md §7.3 — one team's roster in the « Déroulement » view: pick the player of the next fact.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import TeamColumn from '../../components/scoring/TeamColumn.vue'
import type { ScoringPlayer } from '../../types/scoring'
import type { PlayerTally } from '../../utils/scoringRoster'
import { t } from './helpers'

const player = (overrides: Partial<ScoringPlayer> = {}): ScoringPlayer => ({
  matric: 101, nom: 'Dupont', prenom: 'Jean', numero: 7, capitaine: '-', team: 'A', ...overrides,
})

const base = {
  team: 'A' as const,
  teamName: 'Lyon',
  players: [player(), player({ matric: 102, nom: 'Martin', prenom: 'Luc', numero: 9, capitaine: 'C' })],
  tallies: new Map<string, PlayerTally>(),
  selectedMatric: null,
  excludedCard: new Map<string, 'R' | 'D'>(),
  showRoster: true,
  canScore: true,
}

const rows = (wrapper: Awaited<ReturnType<typeof mountSuspended>>) => wrapper.findAll('li button')

describe('ScoringTeamColumn — roster (§7.3)', () => {
  it('lists the players with their number, family name in capitals and first name', async () => {
    const wrapper = await mountSuspended(TeamColumn, { props: base })
    expect(rows(wrapper)).toHaveLength(2)
    expect(rows(wrapper)[0]!.text()).toContain('7')
    expect(rows(wrapper)[0]!.text()).toContain('DUPONT Jean')
  })

  it('marks the captain, and shows the coach status instead of a number', async () => {
    const wrapper = await mountSuspended(TeamColumn, {
      props: { ...base, players: [player({ capitaine: 'C' }), player({ matric: 5, numero: 0, capitaine: 'E' })] },
    })
    expect(rows(wrapper)[0]!.text()).toContain('Cap.')
    expect(rows(wrapper)[1]!.text()).toContain(t('scoring.settings.status_E'))
    expect(rows(wrapper)[1]!.find('.scoring-digits').exists()).toBe(false)
  })

  it('picks a player with a click', async () => {
    const wrapper = await mountSuspended(TeamColumn, { props: base })
    await rows(wrapper)[1]!.trigger('click')
    expect(wrapper.emitted('select')![0]![0]).toMatchObject({ matric: 102 })
  })

  it('shows the selected player as pressed, the others not', async () => {
    const wrapper = await mountSuspended(TeamColumn, { props: { ...base, selectedMatric: 102 } })
    expect(rows(wrapper).map(r => r.attributes('aria-pressed'))).toEqual(['false', 'true'])
  })

  it('locks the roster without the right to score', async () => {
    const wrapper = await mountSuspended(TeamColumn, { props: { ...base, canScore: false } })
    expect(rows(wrapper).every(r => r.attributes('disabled') !== undefined)).toBe(true)
  })

  it('shows nothing of the roster when it is hidden', async () => {
    const wrapper = await mountSuspended(TeamColumn, { props: { ...base, showRoster: false } })
    expect(wrapper.find('ul').exists()).toBe(false)
  })

  it('highlights the players matching the number being typed', async () => {
    const wrapper = await mountSuspended(TeamColumn, { props: { ...base, candidateMatrics: new Set([102]) } })
    expect(rows(wrapper)[0]!.classes().join(' ')).not.toContain('amber')
    expect(rows(wrapper)[1]!.classes().join(' ')).toContain('amber')
  })
})

describe('ScoringTeamColumn — goals and cards (§7.3)', () => {
  it('shows nothing extra for a player without goal or card', async () => {
    const wrapper = await mountSuspended(TeamColumn, { props: base })
    expect(rows(wrapper)[0]!.findAllComponents({ name: 'ScoringEventIcon' })).toHaveLength(0)
  })

  it('shows one goal as an icon, several as a multiplier', async () => {
    const tallies = new Map<string, PlayerTally>([['101', { goals: 1, cards: [] }], ['102', { goals: 3, cards: [] }]])
    const wrapper = await mountSuspended(TeamColumn, { props: { ...base, tallies } })
    expect(rows(wrapper)[0]!.text()).not.toContain('×')
    expect(rows(wrapper)[1]!.text()).toContain('×3')
  })

  it('shows every card of the player in match order', async () => {
    const tallies = new Map<string, PlayerTally>([['101', { goals: 0, cards: ['J', 'R'] }]])
    const wrapper = await mountSuspended(TeamColumn, { props: { ...base, tallies } })
    expect(rows(wrapper)[0]!.findAllComponents({ name: 'ScoringEventIcon' }).map(c => c.props('code'))).toEqual(['J', 'R'])
  })

  it('dims an excluded player (replaced or out until the end)', async () => {
    const wrapper = await mountSuspended(TeamColumn, { props: { ...base, excludedCard: new Map([['101', 'D' as const]]) } })
    expect(rows(wrapper)[0]!.find('.opacity-60').exists()).toBe(true)
    expect(rows(wrapper)[1]!.find('.opacity-60').exists()).toBe(false)
  })
})

describe('ScoringTeamColumn — empty game sheet', () => {
  const empty = { ...base, players: [] }

  it('says so and offers to reload the present players', async () => {
    const wrapper = await mountSuspended(TeamColumn, { props: empty })
    expect(wrapper.text()).toContain(t('scoring.roster_empty'))
    await wrapper.findAll('button').find(b => b.text() === t('scoring.settings.reload_players'))!.trigger('click')
    expect(wrapper.emitted('reload')).toHaveLength(1)
  })

  it('keeps a team-level fact possible, so the fact buttons are not dead', async () => {
    const wrapper = await mountSuspended(TeamColumn, { props: empty })
    await wrapper.findAll('button').find(b => b.text() === t('scoring.team_fact'))!.trigger('click')
    expect(wrapper.emitted('select-team-fact')).toHaveLength(1)
  })
})

describe('ScoringTeamColumn — mobile team name', () => {
  it('asks the page to widen this side when the name is tapped', async () => {
    const wrapper = await mountSuspended(TeamColumn, { props: { ...base, expanded: false } })
    const name = wrapper.find('button[aria-expanded]')
    expect(name.text()).toContain('Lyon')
    expect(name.attributes('aria-expanded')).toBe('false')
    await name.trigger('click')
    expect(wrapper.emitted('expand')).toHaveLength(1)
  })
})
