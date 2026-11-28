// PAGE_SCORING.md §0.19 T6 — the shot log: totals per team and the last shots and saves, each removable.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import ShotLog from '../../components/scoring/ShotLog.vue'
import type { ScoringShot } from '../../types/scoring'
import { t } from './helpers'

const shot = (uid: string, kind: ScoringShot['kind'], team: ScoringShot['team'], overrides: Partial<ScoringShot> = {}): ScoringShot => ({
  uid, kind, team, period: 'M1', tpsJeu: '06:42', player: null, number: null, ...overrides,
})
const base = { teamA: 'Lyon', teamB: 'Paris', canScore: true }
const rows = (wrapper: Awaited<ReturnType<typeof mountSuspended>>) => wrapper.findAll('li')

describe('ScoringShotLog (§0.19)', () => {
  it('gives the totals of each team (SHOT-10)', async () => {
    const shots = [shot('1', 'SHOT', 'A'), shot('2', 'SHOT', 'A'), shot('3', 'SHOT', 'A'), shot('4', 'SAVE', 'B')]
    const wrapper = await mountSuspended(ShotLog, { props: { ...base, shots } })
    const totals = wrapper.find('[data-totals="A"]').text()
    expect(totals).toContain(t('scoring.shots.shots_total', { n: 3 }))
    expect(wrapper.find('[data-totals="B"]').text()).toContain(t('scoring.shots.saves_total', { n: 1 }))
  })

  it('lists the newest first, with its team letter and number, the newest highlighted', async () => {
    const shots = [shot('new', 'SAVE', 'B', { number: 1 }), shot('old', 'SHOT', 'A')]
    const wrapper = await mountSuspended(ShotLog, { props: { ...base, shots, highlightUid: 'new' } })
    expect(rows(wrapper)[0]!.text()).toContain(t('scoring.shots.save'))
    expect(rows(wrapper)[0]!.text()).toContain('B')
    expect(rows(wrapper)[0]!.text()).toContain('1')
    expect(rows(wrapper)[0]!.attributes('data-highlight')).toBe('true')
    expect(rows(wrapper)[1]!.attributes('data-highlight')).toBeUndefined()
  })

  it('marks a shot without team (SHOT-04)', async () => {
    const wrapper = await mountSuspended(ShotLog, { props: { ...base, shots: [shot('1', 'SHOT', '')] } })
    expect(rows(wrapper)[0]!.text()).toContain(t('scoring.shots.unknown_team'))
  })

  it('removes a line with its bin (SHOT-11)', async () => {
    const wrapper = await mountSuspended(ShotLog, { props: { ...base, shots: [shot('x', 'SHOT', 'A')] } })
    await wrapper.find(`button[aria-label="${t('scoring.shots.remove')}"]`).trigger('click')
    expect(wrapper.emitted('remove')).toEqual([['x']])
  })

  it('offers no bin without the right to score', async () => {
    const wrapper = await mountSuspended(ShotLog, { props: { ...base, canScore: false, shots: [shot('x', 'SHOT', 'A')] } })
    expect(wrapper.find(`button[aria-label="${t('scoring.shots.remove')}"]`).exists()).toBe(false)
  })

  it('shows the « n° ? » prompt while a number may still be typed (SHOT-05)', async () => {
    const wrapper = await mountSuspended(ShotLog, { props: { ...base, shots: [shot('x', 'SHOT', 'A')], numberPrompt: { uid: 'x', digits: '2', unknown: null } } })
    expect(wrapper.find('[data-number-prompt]').text()).toContain(t('scoring.shots.number_prompt'))
    expect(wrapper.find('[data-number-prompt]').text()).toContain('2')
  })

  it('says when the number typed is not on the roster (SHOT-08)', async () => {
    const wrapper = await mountSuspended(ShotLog, { props: { ...base, shots: [shot('x', 'SHOT', 'A')], numberPrompt: { uid: 'x', digits: '', unknown: 23 } } })
    expect(wrapper.text()).toContain(t('scoring.shots.number_unknown', { n: 23 }))
  })
})
