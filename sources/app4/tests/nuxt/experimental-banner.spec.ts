// Component test (project `nuxt`): the « experimental preprod » banner (components/ExperimentalBanner.vue).
import { describe, expect, it } from 'vitest'
import { ref } from 'vue'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import ExperimentalBanner from '../../components/ExperimentalBanner.vue'

const state = {
  flag: ref<{ branch: string, sha: string, deployed_at: string, expires_at: string } | null>(null),
  isExperimental: ref(false),
  hoursLeft: ref(0),
}

mockNuxtImport('useExperimentalFlag', () => () => state)

describe('ExperimentalBanner', () => {
  it('shows nothing on a standard preprod (no flag)', async () => {
    state.flag.value = null
    state.isExperimental.value = false
    const wrapper = await mountSuspended(ExperimentalBanner)
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
  })

  it('shows the deployed branch and the short sha when a feature branch is deployed', async () => {
    state.flag.value = { branch: 'feature/scoring', sha: 'abcdef1234567', deployed_at: '', expires_at: '' }
    state.isExperimental.value = true
    state.hoursLeft.value = 3
    const wrapper = await mountSuspended(ExperimentalBanner)
    const banner = wrapper.find('[role="status"]')
    expect(banner.exists()).toBe(true)
    expect(banner.text()).toContain('feature/scoring')
    expect(banner.text()).toContain('abcdef1')
    expect(banner.text()).not.toContain('abcdef12')
  })
})
