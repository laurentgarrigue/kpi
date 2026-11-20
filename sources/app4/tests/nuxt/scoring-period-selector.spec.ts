// PAGE_SCORING.md §7.1 / §0.6 / §7.5 — one « next period » action that follows the match type, plus a direct access.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import PeriodSelector from '../../components/scoring/PeriodSelector.vue'
import { t } from './helpers'

const base = { type: 'C' as const, canChange: true, periodOver: true }

const nextButton = (wrapper: Awaited<ReturnType<typeof mountSuspended>>) =>
  wrapper.find(`button[aria-label^="${t('scoring.period_next')}"]`)

describe('ScoringPeriodSelector — next period (§7.1)', () => {
  it('shows the current period on the direct-access button', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...base, period: 'M1' } })
    expect(wrapper.find(`button[title="${t('scoring.period_direct')}"]`).text()).toContain(t('scoring.period.M1'))
  })

  it('invites to choose when no period is set yet', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...base, period: null } })
    expect(wrapper.find(`button[title="${t('scoring.period_direct')}"]`).text()).toBe(t('scoring.period_direct'))
  })

  it('goes from M1 to M2 without confirmation once the clock is at zero', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...base, period: 'M1' } })
    await nextButton(wrapper).trigger('click')
    expect(wrapper.emitted('change')).toEqual([['M2']])
  })

  it('is only offered when the period is over', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...base, period: 'M1', periodOver: false } })
    expect(nextButton(wrapper).exists()).toBe(false)
  })

  it('ends a classification match (type C) after M2: nothing follows', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...base, period: 'M2' } })
    expect(nextButton(wrapper).exists()).toBe(false)
  })

  it('names the next period in its accessible name', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...base, period: 'M1' } })
    expect(nextButton(wrapper).attributes('aria-label')).toContain(t('scoring.period.M2'))
  })

  it('is locked without the right to change period', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...base, period: 'M1', canChange: false } })
    expect(nextButton(wrapper).attributes('disabled')).toBeDefined()
    expect(wrapper.find(`button[title="${t('scoring.period_direct')}"]`).attributes('disabled')).toBeDefined()
  })
})

describe('ScoringPeriodSelector — elimination match, type E (§7.5)', () => {
  const elimination = { ...base, type: 'E' as const }

  it('goes into the first overtime from M2 while the score is level', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...elimination, period: 'M2', scoreLevel: true } })
    await nextButton(wrapper).trigger('click')
    expect(wrapper.emitted('change')).toEqual([['P1']])
  })

  it('does not go on when a team is ahead', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...elimination, period: 'M2', scoreLevel: false } })
    expect(nextButton(wrapper).exists()).toBe(false)
  })

  it('chains the overtimes without limit while the score stays level (golden goal)', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...elimination, period: 'P3', scoreLevel: true } })
    await nextButton(wrapper).trigger('click')
    expect(wrapper.emitted('change')).toEqual([['P4']])
  })

  it('labels an overtime with its number', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...elimination, period: 'P2', scoreLevel: true } })
    expect(wrapper.find(`button[title="${t('scoring.period_direct')}"]`).text()).toContain(t('scoring.period.overtime', { n: 2 }))
  })

  it('never offers anything after the shoot-out', async () => {
    const wrapper = await mountSuspended(PeriodSelector, { props: { ...elimination, period: 'TB', scoreLevel: true } })
    expect(nextButton(wrapper).exists()).toBe(false)
  })
})
