// PAGE_SCORING.md §6.5 and §0.18 — shot clock commands: 60 s with the ball to the left or the right
// team (seen from the table), 40 s after an offensive rebound, stop.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import Shotclock from '../../components/scoring/Shotclock.vue'
import { t } from './helpers'

const base = {
  state: 'IDLE' as const,
  durations: { full: 60, offensiveRebound: 40 },
  offensiveReboundEnabled: true,
  canScore: true,
  left: { team: 'B' as const, name: 'Paris' },
  right: { team: 'A' as const, name: 'Lyon' },
}

const buttonByText = (wrapper: Awaited<ReturnType<typeof mountSuspended>>, text: string) =>
  wrapper.findAll('button').find(button => button.text().includes(text))!
const leftStart = (wrapper: Awaited<ReturnType<typeof mountSuspended>>) => wrapper.find('[data-side="L"]')
const rightStart = (wrapper: Awaited<ReturnType<typeof mountSuspended>>) => wrapper.find('[data-side="R"]')

describe('ScoringShotclock (§6.5, §0.18)', () => {
  it('starts 60 s with the ball to the left team, then to the right team (SIDE-08, SIDE-09)', async () => {
    const wrapper = await mountSuspended(Shotclock, { props: base })
    await leftStart(wrapper).trigger('click')
    await rightStart(wrapper).trigger('click')
    expect(wrapper.emitted('start60')).toEqual([['L'], ['R']])
  })

  it('puts the 60 s of each team on its side, with its letter and its name (SIDE-23)', async () => {
    const wrapper = await mountSuspended(Shotclock, { props: base })
    expect(leftStart(wrapper).text()).toContain('B')
    expect(leftStart(wrapper).text()).toContain('60')
    expect(leftStart(wrapper).attributes('title')).toBe(t('scoring.shotclock.start60_left', { team: 'B — Paris' }))
    expect(rightStart(wrapper).text()).toContain('A')
    expect(rightStart(wrapper).attributes('title')).toBe(t('scoring.shotclock.start60_right', { team: 'A — Lyon' }))
  })

  it('starts the shorter clock after an offensive rebound, when the rule is enabled', async () => {
    const wrapper = await mountSuspended(Shotclock, { props: base })
    await buttonByText(wrapper, '40 s').trigger('click')
    expect(wrapper.emitted('start40')).toHaveLength(1)
  })

  it('has no 40 s command when the competition does not use the rule', async () => {
    const wrapper = await mountSuspended(Shotclock, { props: { ...base, offensiveReboundEnabled: false } })
    expect(wrapper.text()).not.toContain('40 s')
  })

  it('follows the durations of the competition', async () => {
    const wrapper = await mountSuspended(Shotclock, { props: { ...base, durations: { full: 45, offensiveRebound: 30 } } })
    expect(leftStart(wrapper).text()).toContain('45')
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
    expect(disabled).toHaveLength(4)
    await wrapper.find(`button[aria-label="${t('scoring.sound_test')}"]`).trigger('click')
    expect(wrapper.emitted('testSound')).toHaveLength(1)
  })

  it('shows the keyboard key of each command when keys are given', async () => {
    const wrapper = await mountSuspended(Shotclock, {
      props: { ...base, keys: { start60Left: '/', start60Right: '*', start40: '-', stop: '+' } },
    })
    expect(wrapper.findAll('kbd').map(k => k.text())).toEqual(['/', '*', '-', '+'])
  })
})
