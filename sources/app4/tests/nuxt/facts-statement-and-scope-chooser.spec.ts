// Component tests (project `nuxt`): relevé des faits (REL-01 à REL-05) et choix de l'élément d'un nouveau rapport.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import FactsStatement from '../../components/designations/FactsStatement.vue'
import ScopeChooser from '../../components/reports/ScopeChooser.vue'
import { bodyButton, track } from './modal-helpers'
import { t } from './helpers'

const api = { get: vi.fn(), getBlob: vi.fn() }
mockNuxtImport('useApi', () => () => api)

beforeEach(() => vi.resetAllMocks())

describe('FactsStatement', () => {
  const rows = [
    { club: 'C001', clubLabel: 'Club Un', fact: 'card_yellow', qualifier: 'unknown_qualifier', quantity: 3, details: [{ label: 'Match 1', sourceType: 'MATCH', sourceId: '1' }, { label: 'Rapport', sourceType: 'REPORT', sourceId: '12' }] },
    { club: 'C002', clubLabel: 'Club Deux', fact: 'card_red', qualifier: '', quantity: 1, details: [] },
    { club: null, clubLabel: '', fact: 'card_red', qualifier: '', quantity: 2, details: [] },
  ]
  const mountFacts = async (season = '2026') => {
    api.get.mockResolvedValue({ items: rows })
    const wrapper = await mountSuspended(FactsStatement, { props: { season } })
    await flushPromises()
    return wrapper
  }

  it('loads the statement of the season and shows one line per club and fact, with the sources', async () => {
    const wrapper = await mountFacts()
    expect(api.get).toHaveBeenCalledWith('/admin/designations/facts', { season: '2026' })
    expect(wrapper.findAll('tbody tr')).toHaveLength(3)
    expect(wrapper.text()).toContain('C001 · Club Un')
    expect(wrapper.text()).toContain(t('designations.facts.no_club'))
    expect(wrapper.text()).toContain('unknown_qualifier')
    expect(wrapper.findAll('details a')).toHaveLength(1)
    expect(wrapper.find('details a').text()).toBe('Rapport')
  })

  it('does not load without a season', async () => {
    await mountSuspended(FactsStatement, { props: { season: '' } })
    expect(api.get).not.toHaveBeenCalled()
  })

  it('filters by club, then by kind of fact', async () => {
    const wrapper = await mountFacts()
    await wrapper.find('input[type="search"]').setValue('deux')
    expect(wrapper.findAll('tbody tr')).toHaveLength(1)
    await wrapper.find('input[type="search"]').setValue('')
    await wrapper.find('select').setValue('card_red')
    expect(wrapper.findAll('tbody tr')).toHaveLength(2)
    await wrapper.find('input[type="search"]').setValue('zzz')
    expect(wrapper.text()).toContain(t('designations.facts.empty'))
  })

  it('downloads the statement as CSV and as Excel', async () => {
    const wrapper = await mountFacts()
    api.getBlob.mockResolvedValue(new ArrayBuffer(2))
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:facts')
    vi.spyOn(URL, 'revokeObjectURL').mockImplementation(() => {})
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {})
    const [csv, xlsx] = wrapper.findAll('.ml-auto button')
    await csv.trigger('click')
    await flushPromises()
    expect(api.getBlob).toHaveBeenCalledWith('/admin/designations/facts?season=2026&format=csv')
    await xlsx.trigger('click')
    await flushPromises()
    expect(api.getBlob).toHaveBeenLastCalledWith('/admin/designations/facts?season=2026&format=xlsx')
    expect(click).toHaveBeenCalledTimes(2)
  })
})

describe('ScopeChooser', () => {
  beforeEach(() => vi.useFakeTimers())
  afterEach(() => vi.useRealTimers())

  const open = async () => {
    const wrapper = track(await mountSuspended(ScopeChooser, { props: { open: false, season: '2026' } }))
    await wrapper.setProps({ open: true })
    await nextTick()
    return wrapper
  }
  const search = async (text: string) => {
    const input = document.body.querySelector<HTMLInputElement>('#report-scope-search')!
    input.value = text
    input.dispatchEvent(new Event('input', { bubbles: true }))
    await nextTick()
    await vi.advanceTimersByTimeAsync(400)
  }

  it('does not search for fewer than two characters, but does for a single-digit match number', async () => {
    await open()
    await search('a')
    expect(api.get).not.toHaveBeenCalled()
    api.get.mockResolvedValue({ items: [] })
    await search('7')
    expect(api.get).toHaveBeenCalledWith('/admin/reports/scopes', { season: '2026', q: '7', types: '' })
  })

  it('searches the server, restricted to the ticked kinds, and shows « no result »', async () => {
    api.get.mockResolvedValue({ items: [] })
    await open()
    document.body.querySelector<HTMLInputElement>('input[type="checkbox"]')!.click()
    await search('tournoi')
    expect(api.get).toHaveBeenLastCalledWith('/admin/reports/scopes', { season: '2026', q: 'tournoi', types: 'EVENEMENT' })
    expect(document.body.textContent).toContain(t('reports.new.no_result'))
  })

  it('chooses an item and emits it on submit; the next button waits for a choice', async () => {
    const item = { scopeType: 'JOURNEE', scopeId: '9801', label: 'N1H J1' }
    api.get.mockResolvedValue({ items: [item] })
    const wrapper = await open()
    const next = () => document.body.querySelector<HTMLButtonElement>('button[type="submit"]')!
    expect(next().disabled).toBe(true)
    await search('N1H')
    document.body.querySelector<HTMLButtonElement>('[role="option"] button')!.click()
    await nextTick()
    expect(next().disabled).toBe(false)
    next().click()
    await nextTick()
    expect(wrapper.emitted('chosen')?.[0]).toEqual([item])
  })

  it('resets the search each time it opens, and closes from the cancel button', async () => {
    api.get.mockResolvedValue({ items: [{ scopeType: 'MATCH', scopeId: '5', label: 'Match 5' }] })
    const wrapper = await open()
    await search('match')
    expect(document.body.querySelectorAll('[role="option"]')).toHaveLength(1)
    await wrapper.setProps({ open: false })
    await wrapper.setProps({ open: true })
    await nextTick()
    expect(document.body.querySelectorAll('[role="option"]')).toHaveLength(0)
    bodyButton(t('common.cancel'))!.click()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
