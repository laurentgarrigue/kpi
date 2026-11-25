// TeamAutocomplete — search a team (kp_equipe) by label; the v-model is the team number.
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import TeamAutocomplete from '../../components/admin/TeamAutocomplete.vue'
import { t } from './helpers'

const team = (numero: number, libelle: string) => ({
  numero, libelle, codeClub: '0101', club: 'Club Test', label: `${libelle} (Club Test)`,
})

const api = vi.hoisted(() => ({ get: vi.fn() }))
mockNuxtImport('useApi', () => () => api)

const mount = (props: Record<string, unknown> = {}) =>
  mountSuspended(TeamAutocomplete, { props: { modelValue: null, ...props }, attachTo: document.body })

const type = async (wrapper: Awaited<ReturnType<typeof mount>>, text: string) => {
  await wrapper.find('input').setValue(text)
}
const typeAndSearch = async (wrapper: Awaited<ReturnType<typeof mount>>, text: string) => {
  await type(wrapper, text)
  await vi.waitFor(() => expect(api.get).toHaveBeenCalled())
  await nextTick()
  await nextTick()
}
const resultButtons = (wrapper: Awaited<ReturnType<typeof mount>>) =>
  wrapper.findAll('button').filter(b => b.text().includes('n° '))

beforeEach(() => {
  api.get.mockReset().mockResolvedValue([team(12, 'Equipe A'), team(34, 'Equipe B')])
})

describe('TeamAutocomplete', () => {
  it('does not search before 2 characters', async () => {
    const wrapper = await mount()
    await type(wrapper, 'E')
    await new Promise(resolve => setTimeout(resolve, 400))
    expect(api.get).not.toHaveBeenCalled()
  })

  it('searches the teams endpoint once the query is long enough and lists the results', async () => {
    const wrapper = await mount()
    await typeAndSearch(wrapper, 'Equ')
    expect(api.get).toHaveBeenCalledWith('/admin/operations/autocomplete/teams', { q: 'Equ' })
    expect(resultButtons(wrapper).map(b => b.text())).toEqual([
      expect.stringContaining('Equipe A'),
      expect.stringContaining('Equipe B'),
    ])
  })

  it('emits the team number and shows its label when a result is picked', async () => {
    const wrapper = await mount()
    await typeAndSearch(wrapper, 'Equ')
    await resultButtons(wrapper)[1]!.trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([[34]])
    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('Equipe B (Club Test)')
    expect(resultButtons(wrapper)).toHaveLength(0)
  })

  it('says so when nothing matches', async () => {
    api.get.mockResolvedValue([])
    const wrapper = await mount()
    await typeAndSearch(wrapper, 'zzz')
    expect(wrapper.text()).toContain(t('common.no_results'))
  })

  it('shows no result when the search fails', async () => {
    api.get.mockRejectedValue(new Error('boom'))
    const wrapper = await mount()
    await typeAndSearch(wrapper, 'Equ')
    expect(resultButtons(wrapper)).toHaveLength(0)
  })

  it('clears the choice with the delete button', async () => {
    const wrapper = await mount()
    await typeAndSearch(wrapper, 'Equ')
    await resultButtons(wrapper)[0]!.trigger('click')
    await wrapper.find(`button[aria-label="${t('common.delete')}"]`).trigger('click')
    expect(wrapper.emitted('update:modelValue')).toEqual([[12], [null]])
    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('')
  })

  it('invalidates the previous choice as soon as the user types again', async () => {
    const wrapper = await mount({ modelValue: 12 })
    await type(wrapper, 'Autre')
    expect(wrapper.emitted('update:modelValue')).toEqual([[null]])
  })

  it('empties the field when the parent resets the model after a pick', async () => {
    const wrapper = await mount()
    await typeAndSearch(wrapper, 'Equ')
    await resultButtons(wrapper)[0]!.trigger('click')
    await wrapper.setProps({ modelValue: 12 })
    await wrapper.setProps({ modelValue: null })
    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('')
  })

  it('closes the list on a click outside', async () => {
    const wrapper = await mount()
    await typeAndSearch(wrapper, 'Equ')
    expect(resultButtons(wrapper)).toHaveLength(2)
    document.body.click()
    await nextTick()
    expect(resultButtons(wrapper)).toHaveLength(0)
  })

  it('shows neither list nor clear button when disabled', async () => {
    const wrapper = await mount({ disabled: true })
    expect(wrapper.find('input').attributes('disabled')).toBeDefined()
    await type(wrapper, 'Equ')
    expect(wrapper.find(`button[aria-label="${t('common.delete')}"]`).exists()).toBe(false)
  })
})
