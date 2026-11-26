// PWA-06 (DOC/specs/PWA_AUTO_UPDATE.md §8) — the « new version » banner (components/UpdatePrompt.vue).
import { describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import UpdatePrompt from '../../components/UpdatePrompt.vue'
import { t } from './helpers'

const needRefresh = ref(false)
const updateApp = vi.fn(async () => {})

mockNuxtImport('usePwa', () => () => ({
  needRefresh: computed(() => needRefresh.value),
  updateApp,
}))

const mountBanner = async (waiting: boolean) => {
  needRefresh.value = waiting
  updateApp.mockClear()
  return mountSuspended(UpdatePrompt)
}

describe('UpdatePrompt', () => {
  it('shows nothing while no new worker is waiting', async () => {
    const wrapper = await mountBanner(false)
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })

  it('announces the new version once a worker is waiting', async () => {
    const wrapper = await mountBanner(true)
    const banner = wrapper.find('[role="status"]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toContain(t('Update.Message'))
  })

  it('applies the update on « update now », and only then', async () => {
    const wrapper = await mountBanner(true)
    expect(updateApp).not.toHaveBeenCalled()
    await wrapper.get('[data-test="update-now"]').trigger('click')
    expect(updateApp).toHaveBeenCalledOnce()
  })

  it('hides the banner on « later » without applying the update', async () => {
    const wrapper = await mountBanner(true)
    await wrapper.get('[data-test="update-later"]').trigger('click')
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(updateApp).not.toHaveBeenCalled()
  })
})
