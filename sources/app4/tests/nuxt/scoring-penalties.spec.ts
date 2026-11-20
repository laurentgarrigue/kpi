// PAGE_SCORING.md §7.4 and §0.15 — active penalties of one team: countdowns, removal, expired penalties.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import Penalties from '../../components/scoring/Penalties.vue'
import type { PenaltyClock } from '../../composables/usePenalties'
import { t } from './helpers'

const penalty = (overrides: Partial<PenaltyClock> = {}): PenaltyClock => ({
  id: 'p1', team: 'A', slot: 1, playerId: 'x', playerNumber: 7, cardCode: 'J', remainingMs: 90_000, running: true, ...overrides,
})

describe('ScoringPenalties (§7.4)', () => {
  it('renders nothing when the team has no penalty', async () => {
    const wrapper = await mountSuspended(Penalties, { props: { penalties: [] } })
    expect(wrapper.find('[aria-label]').exists()).toBe(false)
  })

  it('shows the sanctioned player number and the remaining time as mm:ss', async () => {
    const wrapper = await mountSuspended(Penalties, { props: { penalties: [penalty()] } })
    expect(wrapper.text()).toContain('7')
    expect(wrapper.text()).toContain('01:30')
  })

  it('rounds the countdown up to the next second', async () => {
    const wrapper = await mountSuspended(Penalties, { props: { penalties: [penalty({ remainingMs: 59_100 })] } })
    expect(wrapper.text()).toContain('01:00')
  })

  it('shows a question mark when the player is unknown', async () => {
    const wrapper = await mountSuspended(Penalties, { props: { penalties: [penalty({ playerNumber: null })] } })
    expect(wrapper.text()).toContain('?')
  })

  it('lists the penalties by slot, not by arrival', async () => {
    const wrapper = await mountSuspended(Penalties, {
      props: { penalties: [penalty({ id: 'b', slot: 2, playerNumber: 9 }), penalty({ id: 'a', slot: 1, playerNumber: 4 })] },
    })
    const numbers = wrapper.findAll('.scoring-digits').filter(el => /^\d+$/.test(el.text())).map(el => el.text())
    expect(numbers).toEqual(['4', '9'])
  })

  it('removes a penalty by its cross, with the right to score', async () => {
    const wrapper = await mountSuspended(Penalties, { props: { penalties: [penalty({ id: 'p9' })], canScore: true } })
    await wrapper.find(`button[aria-label="${t('scoring.penalty.remove')}"]`).trigger('click')
    expect(wrapper.emitted('remove')).toEqual([['p9']])
  })

  it('locks the removal without the right to score', async () => {
    const wrapper = await mountSuspended(Penalties, { props: { penalties: [penalty()], canScore: false } })
    expect(wrapper.find(`button[aria-label="${t('scoring.penalty.remove')}"]`).attributes('disabled')).toBeDefined()
  })
})

describe('ScoringPenalties — expired penalty (§0.15)', () => {
  it('stays at 00:00 and blinks until the cross removes it', async () => {
    const wrapper = await mountSuspended(Penalties, { props: { penalties: [penalty({ remainingMs: 0, running: false })] } })
    expect(wrapper.text()).toContain('00:00')
    expect(wrapper.find('.penalty-expired').exists()).toBe(true)
  })

  it('does not blink while the clock is still running', async () => {
    const wrapper = await mountSuspended(Penalties, { props: { penalties: [penalty()] } })
    expect(wrapper.find('.penalty-expired').exists()).toBe(false)
  })

  it('offers no fine adjust on an expired penalty, even with the clock stopped', async () => {
    const expired = await mountSuspended(Penalties, {
      props: { penalties: [penalty({ remainingMs: 0, running: false })], canAdjust: true },
    })
    expect(expired.find(`button[aria-label="${t('scoring.penalty.adjust')}"]`).exists()).toBe(false)

    const live = await mountSuspended(Penalties, { props: { penalties: [penalty({ running: false })], canAdjust: true } })
    expect(live.find(`button[aria-label="${t('scoring.penalty.adjust')}"]`).exists()).toBe(true)
  })
})
