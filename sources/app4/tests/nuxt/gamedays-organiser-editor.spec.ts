// Component tests (project `nuxt`): club organisateur modifiable depuis la fenêtre « Officiels » de la page Journées
// (FEATURE_DESIGNATIONS.md ORG-04).
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import OrganiserEditor from '../../components/gamedays/OrganiserEditor.vue'
import { bodyButton, track } from './modal-helpers'
import { t } from './helpers'

const api = { put: vi.fn() }
const toast = { add: vi.fn() }
mockNuxtImport('useApi', () => () => api)
mockNuxtImport('useToast', () => () => toast)

const club = { code: 'C001', label: 'Club Un' }
const mount = async (props: Record<string, unknown> = {}) => track(await mountSuspended(OrganiserEditor, { props: { gamedayId: 9801, organiser: club, ...props } }))
const button = (wrapper: Awaited<ReturnType<typeof mount>>, title: string) => wrapper.find(`button[title="${title}"]`)

beforeEach(() => {
  vi.resetAllMocks()
})

describe('OrganiserEditor', () => {
  it('ORG-04 : shows the current organising club with its code', async () => {
    const wrapper = await mount()
    expect(wrapper.text()).toContain('Club Un')
    expect(wrapper.text()).toContain('C001')
  })

  it('ORG-04 : label and value on one row, like the officials, green when filled', async () => {
    const filled = await mount()
    expect(filled.find('th').text()).toBe(t('gamedays.field.club_organisateur'))
    expect(filled.find('td').classes()).toContain('bg-green-100')
    const empty = await mount({ organiser: { code: '', label: '' } })
    expect(empty.find('td').classes()).not.toContain('bg-green-100')
  })

  it('ORG-04 : the search is in a window opened by the edit button, like in Désignations', async () => {
    const wrapper = await mount()
    expect(wrapper.findComponent({ name: 'AdminTextAutocomplete' }).exists()).toBe(false)
    await button(wrapper, t('designations.organiser_edit')).trigger('click')
    await nextTick()
    expect(wrapper.findComponent({ name: 'AdminTextAutocomplete' }).exists()).toBe(true)
  })

  it('ORG-04 : choosing a club saves its code and label on the gameday and closes the window', async () => {
    api.put.mockResolvedValue({})
    const wrapper = await mount()
    await button(wrapper, t('designations.organiser_edit')).trigger('click')
    await nextTick()
    wrapper.findComponent({ name: 'AdminTextAutocomplete' }).vm.$emit('select', { code: 'C002', libelle: 'Club Deux' })
    await flushPromises()
    expect(api.put).toHaveBeenCalledWith('/admin/gamedays/9801', { organisateur: 'Club Deux', codeOrganisateur: 'C002' })
    expect(toast.add).toHaveBeenCalledWith(expect.objectContaining({ title: t('designations.organiser_saved'), color: 'success' }))
    expect(wrapper.emitted('saved')?.[0]).toEqual([{ code: 'C002', label: 'Club Deux' }])
    expect(wrapper.findComponent({ name: 'AdminTextAutocomplete' }).exists()).toBe(false)
  })

  it('ORG-04 : removing the organising club asks for confirmation first', async () => {
    api.put.mockResolvedValue({})
    const wrapper = await mount()
    await button(wrapper, t('designations.organiser_clear')).trigger('click')
    await nextTick()
    expect(api.put).not.toHaveBeenCalled()
    expect(document.body.textContent).toContain(t('designations.organiser_clear_message', { club: 'Club Un' }))
    bodyButton(t('designations.remove.confirm'))!.click()
    await flushPromises()
    expect(api.put).toHaveBeenCalledWith('/admin/gamedays/9801', { organisateur: '', codeOrganisateur: '' })
    expect(wrapper.emitted('saved')?.[0]).toEqual([{ code: '', label: '' }])
  })

  it('ORG-04 : cancelling the confirmation keeps the club', async () => {
    const wrapper = await mount()
    await button(wrapper, t('designations.organiser_clear')).trigger('click')
    await nextTick()
    bodyButton(t('common.cancel'))!.click()
    await flushPromises()
    expect(api.put).not.toHaveBeenCalled()
  })

  it('read-only: no search and no clear button', async () => {
    const wrapper = await mount({ readonly: true })
    expect(button(wrapper, t('designations.organiser_edit')).exists()).toBe(false)
    expect(button(wrapper, t('designations.organiser_clear')).exists()).toBe(false)
    expect(wrapper.text()).toContain('Club Un')
  })
})
