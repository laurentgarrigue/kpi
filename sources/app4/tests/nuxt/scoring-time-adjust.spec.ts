// PAGE_SCORING.md §6.4/§6.5/§7.4 — fine adjust of a countdown, one system for game clock, shot clock and penalties.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import TimeAdjust from '../../components/scoring/TimeAdjust.vue'

const slot = { default: () => '9:52' }

describe('ScoringTimeAdjust', () => {
  it('makes the countdown a button that opens the adjust', async () => {
    const wrapper = await mountSuspended(TimeAdjust, { props: { display: '9:52', label: 'Corriger' }, slots: slot })
    const trigger = wrapper.find('button')
    expect(trigger.exists()).toBe(true)
    expect(trigger.attributes('aria-label')).toBe('Corriger')
    expect(trigger.text()).toBe('9:52')
  })

  it('is a plain display, with no popover, when the clock is running (canAdjust false)', async () => {
    const wrapper = await mountSuspended(TimeAdjust, {
      props: { display: '9:52', canAdjust: false, lockedHint: 'Arrêtez le chrono' }, slots: slot,
    })
    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.text()).toBe('9:52')
    expect(wrapper.attributes('title')).toBe('Arrêtez le chrono')
  })

  it('is a plain display without hint when disabled (post-match, no right to score)', async () => {
    const wrapper = await mountSuspended(TimeAdjust, {
      props: { display: '9:52', disabled: true, lockedHint: 'Arrêtez le chrono' }, slots: slot,
    })
    expect(wrapper.find('button').exists()).toBe(false)
    expect(wrapper.attributes('title')).toBeUndefined()
  })

  it('applies the trigger class to the countdown in both modes', async () => {
    const adjustable = await mountSuspended(TimeAdjust, { props: { display: '1', triggerClass: 'chip' }, slots: slot })
    const locked = await mountSuspended(TimeAdjust, { props: { display: '1', canAdjust: false, triggerClass: 'chip' }, slots: slot })
    expect(adjustable.find('button').classes()).toContain('chip')
    expect(locked.classes()).toContain('chip')
  })
})
