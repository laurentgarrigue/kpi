// PAGE_SCORING.md §7.2 — official field: referee team first, then any licence holder, free text allowed.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { flushPromises } from '@vue/test-utils'
import OfficialInput from '../../components/scoring/OfficialInput.vue'
import type { OfficialSuggestion, OfficialSuggestionsResponse } from '../../types/scoring'
import { t } from './helpers'

const TEAM_ITEM: OfficialSuggestion = { group: 'team', value: 'DUPONT Jean (101)', label: 'DUPONT Jean' }
const TEAM_ITEM_2: OfficialSuggestion = { group: 'team', value: 'MARTIN Luc (102)', label: 'MARTIN Luc' }
const OTHER_ITEM: OfficialSuggestion = { group: 'other', value: 'DURAND Paul (103)', label: 'DURAND Paul' }

const response = (items: OfficialSuggestion[], team: string | null = 'Lyon'): OfficialSuggestionsResponse => ({ team, items })

const mountField = async (suggest = vi.fn().mockResolvedValue(response([TEAM_ITEM, TEAM_ITEM_2, OTHER_ITEM])), props = {}) => {
  const wrapper = await mountSuspended(OfficialInput, {
    props: { modelValue: '', role: 'table' as const, suggest, ...props },
    attachTo: document.body,
  })
  return { wrapper, suggest }
}

const options = (w: Awaited<ReturnType<typeof mountSuspended>>) => w.findAll('[role=option]')

let mounted: Array<{ unmount: () => void }> = []
beforeEach(() => { vi.useFakeTimers() })
afterEach(() => {
  vi.useRealTimers()
  mounted.forEach(w => w.unmount())
  mounted = []
})

const open = async (props = {}, suggest?: ReturnType<typeof vi.fn>) => {
  const mountedField = await mountField(suggest, props)
  mounted.push(mountedField.wrapper)
  await mountedField.wrapper.find('input').trigger('focus')
  await flushPromises()
  return mountedField
}

describe('ScoringOfficialInput — suggestions', () => {
  it('lists the referee team as soon as the field gets focus', async () => {
    const { wrapper, suggest } = await open({ modelValue: ' Dup ' })
    expect(suggest).toHaveBeenCalledWith('table', 'Dup')
    expect(options(wrapper)).toHaveLength(3)
  })

  it('titles each group once: the team name, then the other licence holders', async () => {
    const { wrapper } = await open()
    const headings = wrapper.findAll('[role=presentation]').map(h => h.text())
    expect(headings).toEqual(['Lyon', t('scoring.settings.other_licensees')])
  })

  it('opens nothing when there is no suggestion', async () => {
    const { wrapper } = await open({}, vi.fn().mockResolvedValue(response([], null)))
    expect(wrapper.find('[role=listbox]').exists()).toBe(false)
    expect(wrapper.find('input').attributes('aria-expanded')).toBe('false')
  })

  it('does not search a disabled field', async () => {
    const suggest = vi.fn().mockResolvedValue(response([TEAM_ITEM]))
    const { wrapper } = await mountField(suggest, { disabled: true })
    mounted.push(wrapper)
    await wrapper.find('input').trigger('focus')
    await flushPromises()
    expect(suggest).not.toHaveBeenCalled()
  })

  it('searches on typing after the debounce, and only once for fast keystrokes', async () => {
    const { wrapper, suggest } = await open()
    suggest.mockClear()
    const input = wrapper.find('input')
    await input.setValue('du')
    await input.setValue('dup')
    expect(suggest).not.toHaveBeenCalled()
    await vi.advanceTimersByTimeAsync(250)
    expect(suggest).toHaveBeenCalledTimes(1)
    expect(suggest).toHaveBeenCalledWith('table', 'dup')
    expect(wrapper.emitted('update:modelValue')!.map(e => e[0])).toEqual(['du', 'dup'])
  })

  it('ignores an answer overtaken by a newer search', async () => {
    let resolveFirst!: (r: OfficialSuggestionsResponse) => void
    const suggest = vi.fn()
      .mockReturnValueOnce(new Promise<OfficialSuggestionsResponse>(r => { resolveFirst = r }))
      .mockResolvedValueOnce(response([OTHER_ITEM], 'Paris'))
    const { wrapper } = await mountField(suggest)
    mounted.push(wrapper)
    await wrapper.find('input').trigger('focus') // slow search #1
    await wrapper.find('input').setValue('du')
    await vi.advanceTimersByTimeAsync(250) // search #2, answers first
    resolveFirst(response([TEAM_ITEM], 'Lyon'))
    await flushPromises()
    expect(options(wrapper).map(o => o.text())).toEqual([OTHER_ITEM.value])
  })

  it('keeps working when the search fails (the API already toasts)', async () => {
    const { wrapper } = await open({}, vi.fn().mockRejectedValue(new Error('500')))
    expect(wrapper.find('[role=listbox]').exists()).toBe(false)
  })
})

describe('ScoringOfficialInput — picking', () => {
  it('picks a suggestion with the mouse and closes the list', async () => {
    const { wrapper } = await open()
    await options(wrapper)[1]!.trigger('mousedown')
    expect(wrapper.emitted('update:modelValue')!.at(-1)).toEqual([TEAM_ITEM_2.value])
    expect(wrapper.find('[role=listbox]').exists()).toBe(false)
  })

  it('moves through the list with the arrows, wrapping around', async () => {
    const { wrapper } = await open()
    const input = wrapper.find('input')
    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(options(wrapper)[0]!.attributes('aria-selected')).toBe('true')
    await input.trigger('keydown', { key: 'ArrowUp' })
    expect(options(wrapper)[2]!.attributes('aria-selected')).toBe('true')
    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(options(wrapper)[0]!.attributes('aria-selected')).toBe('true')
  })

  it('exposes the active option to assistive technologies', async () => {
    const { wrapper } = await open()
    const input = wrapper.find('input')
    expect(input.attributes('aria-activedescendant')).toBeUndefined()
    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(input.attributes('aria-activedescendant')).toBe(options(wrapper)[0]!.attributes('id'))
  })

  it('picks the active option with Enter', async () => {
    const { wrapper } = await open()
    const input = wrapper.find('input')
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'ArrowDown' })
    await input.trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')!.at(-1)).toEqual([TEAM_ITEM_2.value])
  })

  it('leaves Enter alone when no option is active (free text)', async () => {
    const { wrapper } = await open()
    await wrapper.find('input').trigger('keydown', { key: 'Enter' })
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('closes the list with Escape and on blur', async () => {
    const { wrapper } = await open()
    await wrapper.find('input').trigger('keydown', { key: 'Escape' })
    expect(wrapper.find('[role=listbox]').exists()).toBe(false)
    await wrapper.find('input').trigger('focus')
    await flushPromises()
    expect(wrapper.find('[role=listbox]').exists()).toBe(true)
    await wrapper.find('input').trigger('blur')
    expect(wrapper.find('[role=listbox]').exists()).toBe(false)
  })
})

describe('ScoringOfficialInput — clearing', () => {
  it('offers the cross only when the field holds a value and is enabled', async () => {
    const empty = await mountField(undefined, { modelValue: '' })
    mounted.push(empty.wrapper)
    expect(empty.wrapper.find(`[aria-label="${t('scoring.settings.clear_official')}"]`).exists()).toBe(false)
    const disabled = await mountField(undefined, { modelValue: 'X', disabled: true })
    mounted.push(disabled.wrapper)
    expect(disabled.wrapper.find(`[aria-label="${t('scoring.settings.clear_official')}"]`).exists()).toBe(false)
    const filled = await mountField(undefined, { modelValue: 'X' })
    mounted.push(filled.wrapper)
    expect(filled.wrapper.find(`[aria-label="${t('scoring.settings.clear_official')}"]`).exists()).toBe(true)
  })

  it('empties the field and lists the referee team again for a new pick', async () => {
    const { wrapper, suggest } = await mountField(undefined, { modelValue: 'DUPONT Jean (101)' })
    mounted.push(wrapper)
    await wrapper.find(`[aria-label="${t('scoring.settings.clear_official')}"]`).trigger('click')
    await flushPromises()
    expect(wrapper.emitted('update:modelValue')![0]).toEqual([''])
    expect(suggest).toHaveBeenCalledWith('table', '')
    expect(options(wrapper)).toHaveLength(3)
  })
})
