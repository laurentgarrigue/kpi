import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { fakeApi2 } from '../fixtures/api2'
import { mountRoute } from './results-setup'

const { api2 } = vi.hoisted(() => ({ api2: vi.fn() }))
mockNuxtImport('useApi2', () => () => api2)
beforeEach(() => {
  api2.mockReset()
  api2.mockImplementation(fakeApi2)
  clearNuxtData()
})

const cards = async (route: string) => (await mountRoute(route)).findAll('[data-competition]')

describe('competitions list (PAGE_COMPETITIONS.md)', () => {
  it('CPL-02: without a group, the first national group is shown', async () => {
    const wrapper = await mountRoute('/competitions/2999')
    expect(wrapper.find('select[name="group"] option[selected]').attributes('value')).toBe('TSTRES')
    expect(api2).toHaveBeenCalledWith('/group/2999/TSTRES/competitions')
  })

  it('CPL-03: groups by section, with the season list', async () => {
    const wrapper = await mountRoute('/competitions/2999?group=TSTRES')
    expect(wrapper.findAll('select[name="group"] optgroup').map(group => group.attributes('label'))).toEqual(['Compétitions internationales', 'Compétitions nationales'])
    expect(wrapper.findAll('select[name="season"] option').map(option => option.text())).toEqual(['2999', '2998'])
  })

  it('CPL-04: one card per competition, in api2 order, with title, badges and link', async () => {
    const list = await cards('/competitions/2999?group=TSTRES')
    expect(list.map(card => card.attributes('data-competition'))).toEqual(['RCP', 'RMU', 'RCH', 'RAT'])
    expect(list[0]!.find('h2 a').attributes('href')).toBe('/competitions/2999/RCP')
    expect(list[0]!.find('h2').text()).toBe('Phase finale')
    expect(list[0]!.text()).toContain('Coupe')
    expect(list[0]!.text()).toContain('Terminé')
  })

  it('CPL-06/CPL-10: medals for a finished final round, qualified and eliminated marks otherwise, with text', async () => {
    const list = await cards('/competitions/2999?group=TSTRES')
    const cup = list[0]!
    expect(cup.findAll('[data-medal]').map(medal => medal.attributes('data-medal'))).toEqual(['1', '2', '3'])
    expect(cup.find('[data-medal="1"]').text()).toContain('Médaille d\'or')
    const championship = list[2]!
    expect(championship.findAll('[data-mark="qualified"]')).toHaveLength(1)
    expect(championship.findAll('[data-mark="eliminated"]')).toHaveLength(1)
    expect(championship.find('[data-mark="qualified"]').text()).toBe('Qualifié')
  })

  it('CPL-07: a competition without ranking says so', async () => {
    const list = await cards('/competitions/2999?group=TSTRES')
    expect(list[3]!.find('[data-testid="no-ranking"]').text()).toBe('Classement non disponible')
  })

  it('CPL-07: an unknown group shows a dedicated message', async () => {
    const wrapper = await mountRoute('/competitions/2999?group=NOPE')
    // Unknown group → default group (CPL-02); an empty group → message.
    expect(wrapper.find('[data-competition]').exists()).toBe(true)
    api2.mockImplementation(async (path: string) => (path.endsWith('/competitions') ? fakeApi2('/group/2999/NOPE/competitions') : fakeApi2(path)))
    clearNuxtData()
    const empty = await mountRoute('/competitions/2999?group=TSTRES')
    expect(empty.find('[data-testid="no-competition"]').text()).toBe('Aucune compétition publiée pour ce groupe.')
  })

  it('CPL-08: the selection is a GET form to /competitions, usable without JavaScript', async () => {
    const wrapper = await mountRoute('/competitions/2999?group=TSTRES')
    const form = wrapper.find('[data-testid="competition-selector"]')
    expect(form.attributes('method')).toBe('get')
    expect(form.attributes('action')).toBe('/competitions')
    expect(form.find('button[type="submit"]').exists()).toBe(true)
  })

  it('CPL-11: the event covering only part of the group is listed discreetly, with a link', async () => {
    const wrapper = await mountRoute('/competitions/2999?group=TSTRES')
    expect(wrapper.find('[data-testid="main-event"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="other-events"] a').attributes('href')).toBe('/events/77')
  })

  it('links to all the games of the group', async () => {
    const wrapper = await mountRoute('/competitions/2999?group=TSTRES')
    expect(wrapper.find('[data-testid="group-games-link"]').attributes('href')).toBe('/groups/2999/TSTRES/games')
  })
})
