// Component tests (project `nuxt`): fenêtre « Désigner », bloc « Désignations » d'un périmètre et sa fenêtre
// (FEATURE_DESIGNATIONS.md § 8.4, ticket designations/08).
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { flushPromises } from '@vue/test-utils'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import AddModal from '../../components/designations/AddModal.vue'
import ScopeModal from '../../components/designations/ScopeModal.vue'
import ScopePanel from '../../components/designations/ScopePanel.vue'
import type { RoleDefinition } from '../../utils/designations'
import { bodyButton, track } from './modal-helpers'
import { t } from './helpers'

const api = { get: vi.fn(), post: vi.fn(), del: vi.fn() }
const toast = { add: vi.fn() }
const authStore = { profile: 2 }
mockNuxtImport('useApi', () => () => api)
mockNuxtImport('useToast', () => () => toast)
mockNuxtImport('useAuthStore', () => () => authStore)

const role = (code: string, extra: Partial<RoleDefinition> = {}): RoleDefinition => ({ code, label: `Rôle ${code}`, scopes: ['JOURNEE', 'COMPETITION'], mandateProfile: null, authorities: ['CNA'], maxPerScope: 1, designableByMaxProfile: 3, ...extra })
const roles = { R1: role('R1'), DELEGUE_CNA: role('DELEGUE_CNA', { mandateProfile: 5 }), ARBITRE_NJ: role('ARBITRE_NJ', { authorities: ['CNA', 'ORGANISATEUR'], maxPerScope: 5 }) }
const player = { matric: 4321, nom: 'DURAND', prenom: 'Anne', naissance: null, numeroClub: 'C001', club: 'Club', icf: null, arbitre: null }

beforeEach(() => {
  vi.resetAllMocks()
  authStore.profile = 2
})

describe('AddModal', () => {
  const open = async (target: Record<string, unknown>) => {
    const wrapper = track(await mountSuspended(AddModal, { props: { target: null, season: '2026', roles } }))
    await wrapper.setProps({ target })
    await nextTick()
    return wrapper
  }
  const choose = async (wrapper: Awaited<ReturnType<typeof open>>) => {
    wrapper.findComponent({ name: 'AdminPlayerAutocomplete' }).vm.$emit('update:modelValue', player)
    await flushPromises()
  }
  const submit = () => document.body.querySelector<HTMLButtonElement>('button[type="submit"]')!

  it('keeps the submit button disabled until a person is chosen', async () => {
    await open({ role: 'R1', label: 'N1H · J1', scopeType: 'JOURNEE', scopeIds: ['9801'] })
    expect(submit().disabled).toBe(true)
  })

  it('creates one designation per scope of a grouped row and tells the account outcome', async () => {
    api.post.mockResolvedValue({ account: 'none' })
    const wrapper = await open({ role: 'R1', label: 'N1H', kind: 'gameday', ids: [9801, 9802] })
    await choose(wrapper)
    expect(submit().disabled).toBe(false)
    submit().click()
    await flushPromises()
    expect(api.post).toHaveBeenCalledTimes(2)
    expect(api.post).toHaveBeenNthCalledWith(1, '/admin/designations', expect.objectContaining({ season: '2026', role: 'R1', scopeType: 'JOURNEE', scopeId: '9801', matric: 4321, authority: 'CNA', order: 1, quality: null, email: null }))
    expect(api.post).toHaveBeenNthCalledWith(2, '/admin/designations', expect.objectContaining({ scopeId: '9802' }))
    expect(toast.add).toHaveBeenCalledWith(expect.objectContaining({ title: t('designations.account.none'), color: 'success' }))
    expect(wrapper.emitted('saved')).toHaveLength(1)
  })

  it('asks for the email only when the person has no KPI account, for a role that generates a mandate', async () => {
    api.get.mockResolvedValue({ exists: false })
    api.post.mockResolvedValue({ account: 'none' })
    const wrapper = await open({ role: 'DELEGUE_CNA', label: 'N1H', scopeType: 'EVENEMENT', scopeIds: ['7'] })
    await choose(wrapper)
    expect(api.get).toHaveBeenCalledWith('/admin/designations/account', { matric: '4321' })
    const email = document.body.querySelector<HTMLInputElement>('#designation-email')!
    expect(email).not.toBeNull()
    email.value = 'a@b.fr'
    email.dispatchEvent(new Event('input', { bubbles: true }))
    submit().click()
    await flushPromises()
    expect(api.post).toHaveBeenCalledWith('/admin/designations', expect.objectContaining({ email: 'a@b.fr' }))
    expect(toast.add).toHaveBeenCalledWith(expect.objectContaining({ color: 'warning' }))
  })

  it('does not ask for the email when the person already has an account', async () => {
    api.get.mockResolvedValue({ exists: true })
    const wrapper = await open({ role: 'DELEGUE_CNA', label: 'N1H', scopeType: 'EVENEMENT', scopeIds: ['7'] })
    await choose(wrapper)
    expect(document.body.querySelector('#designation-email')).toBeNull()
  })

  it('offers the authority only for a role with several of them', async () => {
    await open({ role: 'ARBITRE_NJ', label: 'N1H', scopeType: 'JOURNEE', scopeIds: ['9801'] })
    expect(document.body.querySelector('#designation-authority')).not.toBeNull()
    const other = await open({ role: 'R1', label: 'N1H', scopeType: 'JOURNEE', scopeIds: ['9801'] })
    await other.setProps({ target: { role: 'R1', label: 'N1H', scopeType: 'JOURNEE', scopeIds: ['9801'] } })
  })

  it('stays open and silent when the API refuses (useApi already shows the error)', async () => {
    api.post.mockRejectedValue(new Error('403'))
    const wrapper = await open({ role: 'R1', label: 'N1H', scopeType: 'JOURNEE', scopeIds: ['9801'] })
    await choose(wrapper)
    submit().click()
    await flushPromises()
    expect(wrapper.emitted('saved')).toBeUndefined()
    expect(toast.add).not.toHaveBeenCalled()
  })

  it('emits close from the cancel button', async () => {
    const wrapper = await open({ role: 'R1', label: 'N1H', scopeType: 'JOURNEE', scopeIds: ['9801'] })
    bodyButton(t('common.cancel'))!.click()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})

describe('ScopePanel', () => {
  const items = [{ id: 11, role: 'R1', matric: 5, lastName: 'MARTIN', firstName: 'Luc', club: 'C002' }]
  const mountPanel = async (props: Record<string, unknown>) => {
    const wrapper = track(await mountSuspended(ScopePanel, { props: { season: '2026', scopeType: 'COMPETITION', scopeId: 'N1H', label: 'N1H', ...props } }))
    await flushPromises()
    return wrapper
  }

  it('lists the own designations of a competition', async () => {
    api.get.mockImplementation(async (url: string) => (url.endsWith('/roles') ? roles : { items }))
    const wrapper = await mountPanel({})
    expect(api.get).toHaveBeenCalledWith('/admin/designations', { season: '2026', scopeType: 'COMPETITION', scopeId: 'N1H' })
    expect(wrapper.text()).toContain('MARTIN Luc (C002)')
  })

  it('reads the effective designations of a gameday', async () => {
    api.get.mockImplementation(async (url: string) => (url.endsWith('/roles') ? roles : { roles: { R1: { requirement: 'required', status: 'red', holders: [] } } }))
    const wrapper = await mountPanel({ scopeType: 'JOURNEE', scopeId: 9801 })
    expect(api.get).toHaveBeenCalledWith('/admin/designations/effective', { gameday: '9801' })
    expect(wrapper.text()).toContain('Rôle R1')
  })

  it('opens the designation window from a cell, and reloads once saved', async () => {
    api.get.mockImplementation(async (url: string) => (url.endsWith('/roles') ? roles : { items: [] }))
    const wrapper = await mountPanel({})
    await wrapper.find('button[aria-label]').trigger('click')
    const modal = wrapper.findComponent({ name: 'DesignationsAddModal' })
    expect((modal.props('target') as { scopeIds: string[] }).scopeIds).toEqual(['N1H'])
    modal.vm.$emit('saved')
    await flushPromises()
    expect(wrapper.emitted('changed')).toHaveLength(1)
    expect(modal.props('target')).toBeNull()
  })

  it('does not offer to add for a profile that cannot write', async () => {
    authStore.profile = 6
    api.get.mockImplementation(async (url: string) => (url.endsWith('/roles') ? roles : { items: [] }))
    const wrapper = await mountPanel({})
    expect(wrapper.find('button[aria-label]').exists()).toBe(false)
  })

  it('removes a holder after confirmation', async () => {
    api.get.mockImplementation(async (url: string) => (url.endsWith('/roles') ? roles : { items }))
    api.del.mockResolvedValue({})
    const wrapper = await mountPanel({})
    await wrapper.find(`button[aria-label="${t('designations.remove.label', { name: 'MARTIN Luc (C002)' })}"]`).trigger('click')
    await nextTick()
    bodyButton(t('designations.remove.confirm'))!.click()
    await flushPromises()
    expect(api.del).toHaveBeenCalledWith('/admin/designations/11')
    expect(toast.add).toHaveBeenCalledWith(expect.objectContaining({ title: t('designations.removed') }))
    expect(wrapper.emitted('changed')).toHaveLength(1)
  })
})

describe('ScopeModal', () => {
  it('shows the panel of its target and relays close and changes', async () => {
    api.get.mockImplementation(async (url: string) => (url.endsWith('/roles') ? roles : { items: [] }))
    const wrapper = track(await mountSuspended(ScopeModal, { props: { target: { season: '2026', scopeType: 'COMPETITION', scopeId: 'N1H', label: 'N1H' } } }))
    await flushPromises()
    const panel = wrapper.findComponent({ name: 'DesignationsScopePanel' })
    expect(panel.exists()).toBe(true)
    panel.vm.$emit('changed')
    expect(wrapper.emitted('changed')).toHaveLength(1)
    wrapper.findComponent({ name: 'AdminModal' }).vm.$emit('close')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('renders no panel without a target', async () => {
    const wrapper = track(await mountSuspended(ScopeModal, { props: { target: null } }))
    expect(wrapper.findComponent({ name: 'DesignationsScopePanel' }).exists()).toBe(false)
  })
})
