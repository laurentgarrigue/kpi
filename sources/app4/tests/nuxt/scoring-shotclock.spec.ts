// PAGE_SCORING.md §6.5 — shot clock commands: start/reset 60 s, 40 s after an offensive rebound, stop.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import Shotclock from '../../components/scoring/Shotclock.vue'
import { t } from './helpers'

const base = {
  state: 'IDLE' as const,
  durations: { full: 60, offensiveRebound: 40 },
  offensiveReboundEnabled: true,
  canScore: true,
}

const buttonByText = (wrapper: Awaited<ReturnType<typeof mountSuspended>>, text: string) =>
  wrapper.findAll('button').find(button => button.text().includes(text))!

describe('ScoringShotclock (§6.5)', () => {
  it('starts the full shot clock with its duration', async () => {
    const wrapper = await mountSuspended(Shotclock, { props: base })
    await buttonByText(wrapper, '60 s').trigger('click')
    expect(wrapper.emitted('start')).toEqual([[60]])
  })

  it('starts the shorter clock after an offensive rebound, when the rule is enabled', async () => {
    const wrapper = await mountSuspended(Shotclock, { props: base })
    await buttonByText(wrapper, '40 s').trigger('click')
    expect(wrapper.emitted('start')).toEqual([[40]])
  })

  it('has no 40 s command when the competition does not use the rule', async () => {
    const wrapper = await mountSuspended(Shotclock, { props: { ...base, offensiveReboundEnabled: false } })
    expect(wrapper.text()).not.toContain('40 s')
  })

  it('follows the durations of the competition', async () => {
    const wrapper = await mountSuspended(Shotclock, { props: { ...base, durations: { full: 45, offensiveRebound: 30 } } })
    await buttonByText(wrapper, '45 s').trigger('click')
    expect(wrapper.emitted('start')).toEqual([[45]])
    expect(wrapper.text()).toContain('30 s')
  })

  it('stops only a clock that is not idle', async () => {
    const idle = await mountSuspended(Shotclock, { props: base })
    expect(buttonByText(idle, t('scoring.shotclock.stop')).attributes('disabled')).toBeDefined()

    const running = await mountSuspended(Shotclock, { props: { ...base, state: 'RUNNING' } })
    await buttonByText(running, t('scoring.shotclock.stop')).trigger('click')
    expect(running.emitted('stop')).toHaveLength(1)
  })

  it('locks every command without the right to score, the sound test stays available', async () => {
    const wrapper = await mountSuspended(Shotclock, { props: { ...base, state: 'RUNNING', canScore: false } })
    const disabled = wrapper.findAll('button').filter(button => button.attributes('disabled') !== undefined)
    expect(disabled).toHaveLength(3)
    await wrapper.find(`button[aria-label="${t('scoring.sound_test')}"]`).trigger('click')
    expect(wrapper.emitted('testSound')).toHaveLength(1)
  })

  it('shows the keyboard key of each command when keys are given', async () => {
    const wrapper = await mountSuspended(Shotclock, { props: { ...base, keys: { start60: 'KeyA', start40: 'KeyZ', stop: 'KeyE' } } })
    expect(wrapper.findAll('kbd')).toHaveLength(3)
  })
})
