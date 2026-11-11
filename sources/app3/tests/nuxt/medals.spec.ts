// Match medals on the public website (FEATURE_MATCH_MEDALS.md § 7 to § 9.2), on real api2 responses of the MED
// competition (season 2998) captured after computing its medals.
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import MedalShield from '~/components/medal/MedalShield.vue'
import type { MedalItem } from '~/utils/medals'
import { fakeApi2 } from '../fixtures/api2'
import { mountRoute } from './results-setup'

const { api2 } = vi.hoisted(() => ({ api2: vi.fn() }))
mockNuxtImport('useApi2', () => () => api2)
beforeEach(() => {
  api2.mockReset()
  api2.mockImplementation(fakeApi2)
  clearNuxtData()
})

describe('stats tab (§ 9.2)', () => {
  it('MED-45: the timeline statistics published on app3 are listed by their catalogue name', async () => {
    const wrapper = await mountRoute('/competitions/2998/MED/stats')
    const kinds = wrapper.findAll('[data-testid="stat-kinds"] a').map(link => link.text())
    expect(kinds).toEqual([
      'Meilleurs buteurs',
      'Médailles des équipes · Team Medals',
      'Médailles des joueurs · Player Medals',
      'Premier but gagnant · First Goal Wins',
      'Supériorité % · Powerplay %',
      'Matchs arbitrés · Games Officiated',
      'Médailles des arbitres · Referee Medals',
    ])
  })

  it('MED-45: a statistic shows its explanation and the games it is computed on, with a link to the explanations', async () => {
    const wrapper = await mountRoute('/competitions/2998/MED/stats?stat=powerplay_rate')
    const stat = wrapper.find('[data-testid="medal-stat"]')
    expect(stat.find('h2').text()).toBe('Supériorité % · Powerplay %')
    expect(stat.text()).toContain('Buts marqués en supériorité numérique, rapportés aux exclusions des adversaires.')
    expect(stat.find('[data-testid="medal-stat-basis"]').text()).toContain('Calculé sur 3 matchs à feuille complète (sur 4 validés).')
    expect(stat.find('[data-testid="medal-stat-basis"] a').attributes('href')).toBe('/medals')
    const oscar = stat.findAll('[data-testid="medal-row"]').find(row => row.text().includes('Medal Oscar'))!
    expect(oscar.text()).toContain('100 %')
    expect(oscar.text()).toContain('1 buts / 1 exclusions adverses')
  })

  it('team medals: one column per pictogram, named for screen readers, and the total', async () => {
    const wrapper = await mountRoute('/competitions/2998/MED/stats?stat=medals_teams')
    const table = wrapper.find('[data-testid="medal-table"]')
    expect(table.findAll('thead .sr-only').map(cell => cell.text())).toContain('Cage inviolée · Clean Sheet')
    expect(table.findAll('[data-testid="medal-row"]')[0]!.text()).toContain('Medal Oscar')
  })

  it('first goal wins: the figure of the competition first', async () => {
    const wrapper = await mountRoute('/competitions/2998/MED/stats?stat=opener_wins')
    expect(wrapper.find('[data-testid="medal-lead"]').text()).toContain('L’équipe qui ouvre le score gagne')
  })

  it('MED-51, MED-55: referees sorted by games, the cards of a game counting for both', async () => {
    const wrapper = await mountRoute('/competitions/2998/MED/stats?stat=referee_games')
    const rows = wrapper.findAll('[data-testid="medal-row"]').map(row => row.find('td').text())
    expect(rows).toEqual(['REFEREE One', 'REFEREE Two'])
    expect(wrapper.find('[data-testid="medal-referee-note"]').text()).toContain('2 matchs sans arbitre désigné')
  })

  it('MED-42, MED-43: player medals as pills with a written name and an accessible explanation', async () => {
    const wrapper = await mountRoute('/competitions/2998/MED/stats?stat=medals_players')
    const pill = wrapper.find('[data-testid="medal-pill"][data-medal="hat_trick"]')
    expect(pill.text()).toContain('Triplé')
    expect(pill.attributes('aria-label')).toBe('Triplé · Hat-trick. Marque au moins 3 buts dans le match.')
    expect(pill.attributes('tabindex')).toBe('0')
  })

  it('player medals: the team and the shirt number under the name', async () => {
    const wrapper = await mountRoute('/competitions/2998/MED/stats?stat=medals_players')
    expect(wrapper.find('[data-testid="medal-row"]').text()).toContain('Medal Oscar, n° 1')
  })

  it('MED-42: in English, the English name alone', async () => {
    const wrapper = await mountRoute('/en/competitions/2998/MED/stats?stat=medals_players')
    expect(wrapper.find('[data-testid="medal-pill"][data-medal="hat_trick"]').attributes('aria-label')).toBe('Hat-trick. Scores at least 3 goals in the game.')
  })
})

describe('explanation page (§ 8)', () => {
  it('MED-48: every published element, by family, with its two names and its explanation', async () => {
    const wrapper = await mountRoute('/medals')
    const codes = wrapper.findAll('[data-testid="medal-explained"]').map(item => item.attributes('data-medal'))
    expect(codes).toContain('fair_play')
    expect(codes).toContain('game_winner')
    expect(codes).toContain('referee_games')
    expect(codes).not.toContain('wire_to_wire')
    const powerplay = wrapper.find('[data-testid="medal-explained"][data-medal="powerplay"]')
    expect(powerplay.find('h3').text()).toBe('Supériorité · Powerplay')
    expect(powerplay.text()).toContain('Marque pendant qu’un adversaire est exclu (supériorité numérique).')
    expect(wrapper.find('[data-testid="medal-principles"]').text()).toContain('Seuils de la saison 2998')
  })
})

describe('team page (§ 9.2)', () => {
  it('MED-49: the game medals of the season, each counter leading to its games', async () => {
    const wrapper = await mountRoute('/teams/161')
    const section = wrapper.find('[data-testid="team-match-medals"]')
    const hatTrick = section.find('[data-testid="team-medal"][data-medal="hat_trick"]')
    expect(hatTrick.find('[data-testid="team-medal-count"]').text()).toBe('1')
    expect(hatTrick.find('[data-testid="team-medal-game"]').attributes('href')).toMatch(/\/game\/9463$/)
    expect(hatTrick.find('[data-testid="team-medal-game"]').attributes('target')).toBe('_blank')
    expect(section.find('[data-medal="game_winner"]').exists()).toBe(false)
  })
})

describe('games list (§ 9.2)', () => {
  it('a labelled game shows its label as a pictogram, named in its tooltip', async () => {
    const wrapper = await mountRoute('/competitions/2998/MED/games')
    const row = wrapper.find('[data-game="9461"]')
    expect(row.find('[data-testid="game-labels"] [data-medal="thriller"]').attributes('aria-label')).toContain('Suspense · Thriller')
    expect(wrapper.find('[data-game="9462"] [data-testid="game-labels"]').exists()).toBe(false)
  })
})

describe('shield (§ 7.3)', () => {
  const item = { code: 'powerplay', type: 'medal', scopes: ['T', 'P'], family: 'superiorite', data_level: 'T', name_fr: 'Supériorité', name_en: 'Powerplay', explanation_fr: '', explanation_en: '', icon: 'powerplay', icon_svg: '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>' } as MedalItem

  it('MED-62: the pictogram is in a family-coloured shield with the KPI watermark, and the counter from 2', async () => {
    const one = await mountSuspended(MedalShield, { props: { item } })
    expect(one.classes()).toContain('text-fam-superiorite')
    expect(one.find('svg text').text()).toBe('KPI')
    expect(one.find('[data-testid="medal-shield-count"]').exists()).toBe(false)
    const three = await mountSuspended(MedalShield, { props: { item, count: 3, size: 40 } })
    expect(three.find('[data-testid="medal-shield-count"]').text()).toBe('×3')
    expect(three.attributes('style')).toContain('width: 40px')
  })

  it('MED-62: every element of the explanations page has its shield', async () => {
    const wrapper = await mountRoute('/medals')
    const items = wrapper.findAll('[data-testid="medal-explained"]')
    expect(items.length).toBeGreaterThan(0)
    for (const element of items) expect(element.find('[data-testid="medal-shield"]').exists()).toBe(true)
  })
})
