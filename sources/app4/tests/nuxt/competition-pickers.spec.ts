// Component tests (project `nuxt`): liste de compétitions à cocher et choix de section (mandats, suivi des Désignations).
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import CompetitionChecklist from '../../components/admin/CompetitionChecklist.vue'
import CompetitionSectionPicker from '../../components/admin/CompetitionSectionPicker.vue'
import type { ChecklistCompetition } from '../../types'

const get = vi.fn()
mockNuxtImport('useApi', () => () => ({ get }))

const competitions: ChecklistCompetition[] = [
  { code: 'N1H', libelle: 'National 1 Hommes', section: 1, sectionLabel: 'Nationales' },
  { code: 'N1D', libelle: 'National 1 Dames', section: 1, sectionLabel: 'Nationales' },
  { code: 'R1', libelle: 'Régional 1', section: 2, sectionLabel: 'Régionales' },
]

describe('CompetitionChecklist', () => {
  it('groups the competitions by section', async () => {
    const wrapper = await mountSuspended(CompetitionChecklist, { props: { modelValue: [], competitions } })
    expect(wrapper.text()).toContain('— Nationales —')
    expect(wrapper.text()).toContain('— Régionales —')
    expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(3)
  })

  it('filters by code or label, and exposes what is shown', async () => {
    const wrapper = await mountSuspended(CompetitionChecklist, { props: { modelValue: [], competitions } })
    await wrapper.find('input[type="text"]').setValue('dames')
    expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(1)
    expect((wrapper.vm as unknown as { shown: string[] }).shown).toEqual(['N1D'])
    await wrapper.find('input[type="text"]').setValue('zzz')
    expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0)
  })

  it('clears the search from its button and from the exposed method', async () => {
    const wrapper = await mountSuspended(CompetitionChecklist, { props: { modelValue: [], competitions } })
    await wrapper.find('input[type="text"]').setValue('N1')
    await wrapper.find('button').trigger('click')
    expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(3)
    await wrapper.find('input[type="text"]').setValue('R1')
    ;(wrapper.vm as unknown as { clearSearch: () => void }).clearSearch()
    await flushPromises()
    expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(3)
  })

  it('emits the ticked codes, adding and removing one', async () => {
    const wrapper = await mountSuspended(CompetitionChecklist, { props: { modelValue: ['N1H'], competitions } })
    const boxes = wrapper.findAll('input[type="checkbox"]')
    expect((boxes[0].element as HTMLInputElement).checked).toBe(true)
    await boxes[1].setValue(true)
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([['N1H', 'N1D']])
    await boxes[0].setValue(false)
    expect(wrapper.emitted('update:modelValue')?.[1]).toEqual([[]])
  })

  it('shows a loading state instead of the list', async () => {
    const wrapper = await mountSuspended(CompetitionChecklist, { props: { modelValue: [], competitions, loading: true } })
    expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0)
  })
})

describe('CompetitionSectionPicker', () => {
  beforeEach(() => {
    get.mockReset()
    localStorage.clear()
    get.mockResolvedValue({
      groups: [
        { section: 1, sectionLabel: 'Nationales', competitions: [{ code: 'N1H', libelle: 'National 1 Hommes' }, { code: 'N1D', libelle: 'National 1 Dames' }] },
        { section: 2, sectionLabel: 'Régionales', competitions: [{ code: 'R1', libelle: 'Régional 1' }] },
      ],
    })
  })

  const mountPicker = async (props: Record<string, unknown>) => {
    const wrapper = await mountSuspended(CompetitionSectionPicker, { props: { season: '2026', modelValue: [], ...props } })
    await flushPromises()
    return wrapper
  }

  const chooseSection = async (wrapper: Awaited<ReturnType<typeof mountPicker>>, value: string) => {
    const select = wrapper.find('select')
    ;(select.element as HTMLSelectElement).value = value
    await select.trigger('change')
  }

  it('loads the competitions of the season and lists their sections', async () => {
    const wrapper = await mountPicker({})
    expect(get).toHaveBeenCalledWith('/admin/filters/competitions', { seasons: '2026' })
    expect(wrapper.findAll('select option').map(o => o.text())).toEqual(expect.arrayContaining(['Nationales', 'Régionales']))
  })

  it('does not load without a season', async () => {
    await mountPicker({ season: '' })
    expect(get).not.toHaveBeenCalled()
  })

  it('choosing a section selects its competitions and remembers the section', async () => {
    const wrapper = await mountPicker({ storageKey: 'test_section' })
    await chooseSection(wrapper, '1')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([['N1H', 'N1D']])
    expect(localStorage.getItem('test_section')).toBe('1')
    await chooseSection(wrapper, '')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[]])
  })

  it('restores the remembered section', async () => {
    localStorage.setItem('test_section', '2')
    const wrapper = await mountPicker({ storageKey: 'test_section' })
    expect((wrapper.find('select').element as HTMLSelectElement).value).toBe('2')
  })

  it('ignores a corrupt remembered section', async () => {
    localStorage.setItem('test_section', '{not json')
    const wrapper = await mountPicker({ storageKey: 'test_section' })
    expect((wrapper.find('select').element as HTMLSelectElement).value).toBe('')
  })

  it('drops the chosen competitions that are not in the season, and clears them on request', async () => {
    const wrapper = await mountPicker({ modelValue: ['N1H', 'OLD'] })
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([['N1H']])
    const clear = wrapper.findAll('details button').find(b => b.text().length > 0 && b.classes().includes('underline') && b.element === wrapper.findAll('details button')[1].element)
    await clear?.trigger('click')
    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([[]])
  })

  it('adds the shown competitions to the chosen ones', async () => {
    const wrapper = await mountPicker({ modelValue: ['R1'] })
    await wrapper.findAll('details button')[0].trigger('click')
    const last = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as string[]
    expect([...last].sort()).toEqual(['N1D', 'N1H', 'R1'])
  })
})
