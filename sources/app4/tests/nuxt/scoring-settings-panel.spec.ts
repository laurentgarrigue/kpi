// PAGE_SCORING.md §7.1/§7.2/§7.8 — « Paramètres du match »: officials, compositions, escape hatches.
import { afterEach, describe, expect, it, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import SettingsPanel from '../../components/scoring/SettingsPanel.vue'
import type { ScoringMatch, ScoringPlayer } from '../../types/scoring'
import { t } from './helpers'

const match = (overrides: Partial<ScoringMatch> = {}): ScoringMatch => ({
  id: 5001, idJournee: 1, numeroOrdre: 3, dateMatch: '2026-11-20', heureMatch: '09:00', libelle: null, terrain: '1',
  validation: 'N', publication: 'N', statut: 'ATT', type: 'C', periode: null,
  scoreA: null, scoreB: null, scoreDetailA: null, scoreDetailB: null,
  idEquipeA: 1, equipeA: 'Lyon', idEquipeB: 2, equipeB: 'Paris',
  codeCompetition: 'N1H', competitionStatut: 'ON', phase: null,
  secretaire: 'DUPONT Jean (101)', arbitrePrincipal: 'MARTIN Luc',
  ...overrides,
} as ScoringMatch)

const player = (overrides: Partial<ScoringPlayer> = {}): ScoringPlayer => ({
  matric: 101, nom: 'Dupont', prenom: 'Jean', numero: 7, capitaine: '-', team: 'A', ...overrides,
})

const suggestOfficials = vi.fn().mockResolvedValue({ team: null, items: [] })

const base = () => ({
  match: match(),
  playersA: [player(), player({ matric: 102, nom: 'Martin', prenom: 'Luc', numero: 3, capitaine: 'C' })],
  playersB: [player({ matric: 201, nom: 'Durand', prenom: 'Paul', numero: 5, team: 'B' })],
  canScore: true,
  canManagePlayers: true,
  suggestOfficials,
})

type Wrapper = Awaited<ReturnType<typeof mountSuspended>>
const mounted: Wrapper[] = []
const mountPanel = async (props: Record<string, unknown> = {}) => {
  const w = await mountSuspended(SettingsPanel, { props: { ...base(), ...props }, attachTo: document.body })
  mounted.push(w)
  return w
}
afterEach(() => { mounted.splice(0).forEach(w => w.unmount()) })

const sectionOf = (w: Wrapper, name: string) => w.findAll('section').find(s => s.find('h2').exists() && s.find('h2').text() === name)!
const saveButton = (w: Wrapper) => w.findAll('button').find(b => b.text() === t('scoring.edit.save'))!
const input = (w: Wrapper, id: string) => w.find<HTMLInputElement>(`#${id}`)

describe('ScoringSettingsPanel — match facts (read-only, §7.1)', () => {
  it('shows the match id, type and publication', async () => {
    const w = await mountPanel({ match: match({ type: 'E', publication: 'O' }) })
    const facts = sectionOf(w, t('scoring.settings.match'))
    expect(facts.text()).toContain('ID#5001')
    expect(facts.text()).toContain(t('scoring.settings.type_elimination'))
    expect(facts.text()).toContain(t('scoring.settings.publication_public'))
  })

  it('shows a ranking, private match by default', async () => {
    const w = await mountPanel()
    const facts = sectionOf(w, t('scoring.settings.match'))
    expect(facts.text()).toContain(t('scoring.settings.type_ranking'))
    expect(facts.text()).toContain(t('scoring.settings.publication_private'))
    expect(facts.text()).toContain(t('scoring.settings.read_only_hint'))
  })
})

describe('ScoringSettingsPanel — loading another match (§7.8)', () => {
  it('disables Load until something is typed, then emits the typed number', async () => {
    const w = await mountPanel()
    const load = w.findAll('button').find(b => b.text() === t('scoring.settings.load'))!
    expect(load.attributes('disabled')).toBeDefined()
    await w.find('#scoring-game-lookup').setValue('12')
    expect(load.attributes('disabled')).toBeUndefined()
    await load.trigger('click')
    expect(w.emitted('load-match')![0]).toEqual(['12'])
  })

  it('loads with Enter too', async () => {
    const w = await mountPanel()
    await w.find('#scoring-game-lookup').setValue('5002')
    await w.find('#scoring-game-lookup').trigger('keyup', { key: 'Enter' })
    expect(w.emitted('load-match')![0]).toEqual(['5002'])
  })

  it('asks for the next game on the pitch', async () => {
    const w = await mountPanel()
    await w.findAll('button').find(b => b.text() === t('scoring.settings.load_next'))!.trigger('click')
    expect(w.emitted('load-next')).toHaveLength(1)
  })
})

describe('ScoringSettingsPanel — officials (§7.2)', () => {
  it('fills the form from the match, empty when an official is missing', async () => {
    const w = await mountPanel()
    expect(input(w, 'official-secretaire').element.value).toBe('DUPONT Jean (101)')
    expect(input(w, 'official-chronometre').element.value).toBe('')
  })

  it('refills the form when another match is loaded', async () => {
    const w = await mountPanel()
    await w.setProps({ match: match({ id: 5002, secretaire: 'DURAND Paul (103)' }) })
    expect(input(w, 'official-secretaire').element.value).toBe('DURAND Paul (103)')
  })

  it('keeps Save disabled until an official is edited', async () => {
    const w = await mountPanel()
    expect(saveButton(w).attributes('disabled')).toBeDefined()
    await input(w, 'official-secretaire').setValue('Autre')
    expect(saveButton(w).attributes('disabled')).toBeUndefined()
  })

  it('saves every official in one call, then clears the dirty state', async () => {
    const w = await mountPanel()
    await input(w, 'official-secretaire').setValue('Autre')
    await saveButton(w).trigger('click')
    const [saved] = w.emitted('save-officials')![0] as [Record<string, string>]
    expect(saved).toMatchObject({ secretaire: 'Autre', arbitrePrincipal: 'MARTIN Luc', chronometre: '' })
    expect(Object.keys(saved)).toHaveLength(7)
    expect(saveButton(w).attributes('disabled')).toBeDefined()
  })

  it('announces unsaved officials, and their saving', async () => {
    const w = await mountPanel()
    expect(w.emitted('officials-dirty')![0]).toEqual([false])
    await input(w, 'official-secretaire').setValue('Autre')
    expect(w.emitted('officials-dirty')!.at(-1)).toEqual([true])
    await saveButton(w).trigger('click')
    expect(w.emitted('officials-dirty')!.at(-1)).toEqual([false])
  })

  it('discards the dirty state when another match is loaded', async () => {
    const w = await mountPanel()
    await input(w, 'official-secretaire').setValue('Autre')
    await w.setProps({ match: match({ id: 5002 }) })
    expect(w.emitted('officials-dirty')!.at(-1)).toEqual([false])
    expect(saveButton(w).attributes('disabled')).toBeDefined()
  })

  it('locks the fields and Save without the scoring right', async () => {
    const w = await mountPanel({ canScore: false })
    expect(input(w, 'official-secretaire').attributes('disabled')).toBeDefined()
    expect(saveButton(w).attributes('disabled')).toBeDefined()
  })

  it('groups the fields: scoring table, then referees', async () => {
    const w = await mountPanel()
    const legends = w.findAll('fieldset legend').map(l => l.text())
    expect(legends).toEqual([t('scoring.settings.officials_table'), t('scoring.settings.officials_referees')])
    expect(w.findAll('fieldset')[0]!.findAll('input')).toHaveLength(3)
    expect(w.findAll('fieldset')[1]!.findAll('input')).toHaveLength(4)
  })
})

describe('ScoringSettingsPanel — compositions', () => {
  const teamSection = (w: Wrapper, name: string) => w.findAll('section').find(s => s.find('h2').exists() && s.find('h2').text() === name)!

  it('lists each team with its players in number order, family name in capitals', async () => {
    const w = await mountPanel()
    const rows = teamSection(w, 'Lyon').findAll('tbody tr')
    expect(rows.map(r => r.text())).toEqual([
      expect.stringContaining('MARTIN Luc'), expect.stringContaining('DUPONT Jean'),
    ])
    expect(teamSection(w, 'Paris').text()).toContain('DURAND Paul')
  })

  it('puts the staff under its own heading, after the players', async () => {
    const w = await mountPanel({ playersA: [player({ matric: 9, nom: 'Coach', prenom: 'Ben', numero: 0, capitaine: 'E' }), player()] })
    const section = teamSection(w, 'Lyon')
    expect(section.findAll('tbody')).toHaveLength(2)
    expect(section.findAll('tbody')[1]!.text()).toContain(t('scoring.settings.status_E'))
    expect(section.findAll('tbody')[1]!.text()).toContain('COACH Ben')
  })

  it('says so when a team has no player', async () => {
    const w = await mountPanel({ playersB: [] })
    expect(teamSection(w, 'Paris').text()).toContain(t('scoring.settings.no_player'))
    expect(teamSection(w, 'Paris').find('table').exists()).toBe(false)
  })

  it('emits the new number when it is changed', async () => {
    const w = await mountPanel()
    const number = teamSection(w, 'Paris').find<HTMLInputElement>('input[type=number]')
    number.element.value = '12'
    await number.trigger('change')
    expect(w.emitted('update-player')![0]).toEqual(['B', 201, { numero: 12 }])
  })

  it('removes a player', async () => {
    const w = await mountPanel()
    await teamSection(w, 'Paris').find(`[aria-label="${t('scoring.settings.remove_player')}"]`).trigger('click')
    expect(w.emitted('remove-player')![0]).toEqual(['B', 201])
  })

  it('reloads the present players of one team', async () => {
    const w = await mountPanel()
    await teamSection(w, 'Lyon').findAll('button').find(b => b.text() === t('scoring.settings.reload_players'))!.trigger('click')
    expect(w.emitted('reload-players')![0]).toEqual(['A'])
  })

  it('locks the edition without the player-management right', async () => {
    const w = await mountPanel({ canManagePlayers: false })
    const section = teamSection(w, 'Paris')
    expect(section.find('input[type=number]').attributes('disabled')).toBeDefined()
    expect(section.find(`[aria-label="${t('scoring.settings.remove_player')}"]`).attributes('disabled')).toBeDefined()
    expect(section.findAll('button').find(b => b.text() === t('scoring.settings.reload_players'))!.attributes('disabled')).toBeDefined()
  })

  it('switches between the compositions with the A/B tabs (mobile)', async () => {
    const w = await mountPanel()
    const tabs = w.findAll('[role=tab]')
    expect(tabs.map(x => x.text())).toEqual(['Lyon', 'Paris'])
    expect(tabs[0]!.attributes('aria-selected')).toBe('true')
    expect(teamSection(w, 'Paris').classes()).toContain('max-md:hidden')
    await tabs[1]!.trigger('click')
    expect(tabs[1]!.attributes('aria-selected')).toBe('true')
    expect(teamSection(w, 'Paris').classes()).not.toContain('max-md:hidden')
    expect(teamSection(w, 'Lyon').classes()).toContain('max-md:hidden')
  })
})
