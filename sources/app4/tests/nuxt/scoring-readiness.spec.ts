// PAGE_SCORING.md §7.2 — pre-kick-off checks of the game sheet. Warnings only, never blocking.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import Readiness from '../../components/scoring/Readiness.vue'
import type { ReadinessIssue } from '../../utils/scoringReadiness'
import { t } from './helpers'

const issues: ReadinessIssue[] = [
  { code: 'too_few_players', team: 'A', count: 4 },
  { code: 'no_captain', team: 'B' },
]
const teams = { teamA: 'Lyon', teamB: 'Paris' }

describe('ScoringReadiness — full presentation', () => {
  it('shows a « ready » line when there is nothing to check', async () => {
    const wrapper = await mountSuspended(Readiness, { props: { issues: [], ...teams } })
    expect(wrapper.text()).toContain(t('scoring.readiness.ok'))
    expect(wrapper.find('ul').exists()).toBe(false)
  })

  it('lists every point to check, with the team names', async () => {
    const wrapper = await mountSuspended(Readiness, { props: { issues, ...teams } })
    const items = wrapper.findAll('li').map(li => li.text())
    expect(items).toHaveLength(2)
    expect(items[0]).toContain('Lyon')
    expect(items[1]).toContain('Paris')
    expect(wrapper.text()).toContain(t('scoring.readiness.hint'))
  })

  it('names the missing officials by their role', async () => {
    const wrapper = await mountSuspended(Readiness, {
      props: { issues: [{ code: 'missing_officials', roles: ['arbitrePrincipal'] } as ReadinessIssue], ...teams },
    })
    const role = t('scoring.officials.arbitrePrincipal')
    expect(role).not.toContain('scoring.officials') // guards against a missing key
    expect(wrapper.find('li').text()).toContain(role)
  })
})

describe('ScoringReadiness — compact presentation', () => {
  it('shows nothing when there is nothing to check', async () => {
    const wrapper = await mountSuspended(Readiness, { props: { issues: [], compact: true, ...teams } })
    expect(wrapper.find('section, button').exists()).toBe(false)
  })

  it('sums the points up on one line and opens the sheet on demand', async () => {
    const wrapper = await mountSuspended(Readiness, { props: { issues, compact: true, ...teams } })
    expect(wrapper.text()).toContain(t('scoring.readiness.summary', { count: 2 }))
    const open = wrapper.findAll('button').find(b => b.text() === t('scoring.readiness.open'))!
    await open.trigger('click')
    expect(wrapper.emitted('open')).toHaveLength(1)
  })

  it('folds into a chip carrying the count, and unfolds again', async () => {
    const wrapper = await mountSuspended(Readiness, { props: { issues, compact: true, ...teams } })
    await wrapper.find(`button[aria-label="${t('scoring.readiness.collapse')}"]`).trigger('click')
    const chip = wrapper.find(`button[title="${t('scoring.readiness.expand')}"]`)
    expect(chip.text()).toBe('2')
    expect(wrapper.text()).not.toContain(t('scoring.readiness.summary', { count: 2 }))

    await chip.trigger('click')
    expect(wrapper.text()).toContain(t('scoring.readiness.summary', { count: 2 }))
  })
})
