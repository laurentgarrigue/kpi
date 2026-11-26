// Component test (project `nuxt`): « Règles métier » section of Operations › System (FEATURE_RULES_CATALOG_PDF.md RCAT-12).
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive } from 'vue'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import SystemTab from '../../components/operations/SystemTab.vue'

const authStore = reactive({ profile: 1 })
const workContext = reactive({
  season: '',
  seasons: [{ code: '2026', active: false }, { code: '2027', active: true }],
  initContext: vi.fn(),
})
const openDocument = vi.fn()

mockNuxtImport('useAuthStore', () => () => authStore)
mockNuxtImport('useWorkContextStore', () => () => workContext)
mockNuxtImport('usePdf', () => () => ({ openDocument }))

describe('SystemTab — business rules PDF', () => {
  beforeEach(() => {
    openDocument.mockReset()
    workContext.initContext.mockReset()
    workContext.season = ''
    authStore.profile = 1
  })

  it('RCAT-12: is hidden from profile 2', async () => {
    authStore.profile = 2
    const wrapper = await mountSuspended(SystemTab)
    expect(wrapper.find('[data-testid="rules-catalog"]').exists()).toBe(false)
    expect(workContext.initContext).not.toHaveBeenCalled()
  })

  it('opens every catalogue of the work season for profile 1', async () => {
    const wrapper = await mountSuspended(SystemTab)
    expect(workContext.initContext).toHaveBeenCalled()
    workContext.season = '2027'
    await wrapper.vm.$nextTick()

    await wrapper.find('[data-testid="rules-catalog-print"]').trigger('click')
    expect(openDocument).toHaveBeenCalledWith('rules-catalog', { season: '2027' })
  })

  it('opens one catalogue of the chosen season', async () => {
    const wrapper = await mountSuspended(SystemTab)
    await wrapper.find('[data-testid="rules-catalog-season"]').setValue('2026')
    await wrapper.find('[data-testid="rules-catalog-catalog"]').setValue('card-suspensions')

    await wrapper.find('[data-testid="rules-catalog-print"]').trigger('click')
    expect(openDocument).toHaveBeenCalledWith('rules-catalog', { season: '2026', catalog: 'card-suspensions' })
  })
})
