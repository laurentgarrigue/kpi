// PAGE_SCORING.md §0.9 — shared event marker: goal = black disc with a ring, card = plain rectangle of its colour.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import EventIcon from '../../components/scoring/EventIcon.vue'

describe('ScoringEventIcon (§0.9)', () => {
  it('draws a goal as a disc holding a ring', async () => {
    const wrapper = await mountSuspended(EventIcon, { props: { code: 'B' } })
    expect(wrapper.classes()).toContain('rounded-full')
    expect(wrapper.find('span').exists()).toBe(true)
  })

  it.each([
    ['V', 'bg-green-500'],
    ['J', 'bg-yellow-400'],
    ['R', 'bg-red-500'],
    ['D', 'bg-black'],
  ] as const)('draws the %s card in its colour (%s)', async (code, colourClass) => {
    const wrapper = await mountSuspended(EventIcon, { props: { code } })
    expect(wrapper.classes()).toContain(colourClass)
    expect(wrapper.classes()).not.toContain('rounded-full')
  })

  it('is decorative: hidden from assistive technologies', async () => {
    const wrapper = await mountSuspended(EventIcon, { props: { code: 'J' } })
    expect(wrapper.attributes('aria-hidden')).toBe('true')
  })

  it('keeps the black card visible on the dark theme with a white outline', async () => {
    const wrapper = await mountSuspended(EventIcon, { props: { code: 'D' } })
    expect(wrapper.classes()).toContain('ring-white')
  })
})
