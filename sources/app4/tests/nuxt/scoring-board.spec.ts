// PAGE_SCORING.md §6.4/§6.5/§7.4 — the console's display panel, a replica of the pitch scoreboard.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import Board from '../../components/scoring/Board.vue'
import type { PenaltyClock } from '../../composables/usePenalties'
import { t } from './helpers'

const base = { teamA: 'Lyon', teamB: 'Paris', scoreA: 3, scoreB: 2, clock: '8:15', clockRunning: true }

const penalty = (overrides: Partial<PenaltyClock> = {}): PenaltyClock => ({
  id: 'p1', team: 'A', slot: 1, playerId: 'x', playerNumber: 7, cardCode: 'J', remainingMs: 90_000, running: true, ...overrides,
})

describe('ScoringBoard — scores and clocks', () => {
  it('shows the team names and their scores', async () => {
    const wrapper = await mountSuspended(Board, { props: base })
    const names = wrapper.findAll('.board-team').map(el => el.text())
    const scores = wrapper.findAll('.board-score').map(el => el.text())
    expect(names).toEqual(['Lyon', 'Paris'])
    expect(scores).toEqual(['3', '2'])
  })

  it('shows the game clock green while it runs, red when it is stopped', async () => {
    const running = await mountSuspended(Board, { props: base })
    expect(running.find('.board-clock').classes()).toContain('text-green-700')

    const stopped = await mountSuspended(Board, { props: { ...base, clockRunning: false } })
    expect(stopped.find('.board-clock').classes()).toContain('text-red-700')
  })

  it('has no clock after the match: a separator stands between the scores', async () => {
    const wrapper = await mountSuspended(Board, { props: { ...base, clock: null } })
    expect(wrapper.find('.board-clock').exists()).toBe(false)
    expect(wrapper.text()).toContain('–')
  })

  it('shows the shot clock under the game clock, dimmed according to its state', async () => {
    const wrapper = await mountSuspended(Board, { props: { ...base, shotclock: '45', shotclockState: 'RUNNING' } })
    expect(wrapper.text()).toContain(t('scoring.shotclock.short'))
    expect(wrapper.text()).toContain('45')
  })

  it('shows nothing of the shot clock when there is none', async () => {
    const wrapper = await mountSuspended(Board, { props: base })
    expect(wrapper.text()).not.toContain(t('scoring.shotclock.short'))
  })

  it('shows the inter-period break instead of the shot clock', async () => {
    const wrapper = await mountSuspended(Board, { props: { ...base, shotclock: '45', breakDisplay: '2:59' } })
    expect(wrapper.text()).toContain(t('scoring.break.title'))
    expect(wrapper.text()).toContain('2:59')
    expect(wrapper.text()).not.toContain(t('scoring.shotclock.short'))
  })

  it('renders the state line (period, status) given as a slot', async () => {
    const wrapper = await mountSuspended(Board, { props: base, slots: { state: () => 'M1 · ON' } })
    expect(wrapper.text()).toContain('M1 · ON')
  })
})

describe('ScoringBoard — corrections of the clocks', () => {
  it('opens the game-clock adjust only when the console allows it and the clock is correctable', async () => {
    const open = await mountSuspended(Board, { props: { ...base, canOpenClockAdjust: true, clockAdjustable: true } })
    expect(open.find(`button[aria-label="${t('scoring.timer.adjust')}"]`).exists()).toBe(true)

    const running = await mountSuspended(Board, { props: { ...base, canOpenClockAdjust: true, clockAdjustable: false } })
    expect(running.find(`button[aria-label="${t('scoring.timer.adjust')}"]`).exists()).toBe(false)

    const post = await mountSuspended(Board, { props: { ...base, canOpenClockAdjust: false, clockAdjustable: true } })
    expect(post.find(`button[aria-label="${t('scoring.timer.adjust')}"]`).exists()).toBe(false)
  })

  it('opens the shot-clock adjust only when it is suspended', async () => {
    const props = { ...base, shotclock: '45', canOpenClockAdjust: true }
    const suspended = await mountSuspended(Board, { props: { ...props, shotclockAdjustable: true } })
    expect(suspended.find(`button[aria-label="${t('scoring.shotclock.adjust')}"]`).exists()).toBe(true)
    const running = await mountSuspended(Board, { props: { ...props, shotclockAdjustable: false } })
    expect(running.find(`button[aria-label="${t('scoring.shotclock.adjust')}"]`).exists()).toBe(false)
  })
})

describe('ScoringBoard — penalties (§7.4)', () => {
  it('shows the running penalties under the score of their team only', async () => {
    const wrapper = await mountSuspended(Board, { props: { ...base, penaltiesA: [penalty({ playerNumber: 7 })], penaltiesB: [] } })
    const penalties = wrapper.findAllComponents({ name: 'ScoringPenalties' })
    expect(penalties).toHaveLength(1)
    expect(penalties[0]!.text()).toContain('7')
  })

  it('relays the removal of a penalty to the page', async () => {
    const wrapper = await mountSuspended(Board, { props: { ...base, penaltiesB: [penalty({ id: 'pb' })], canScore: true } })
    await wrapper.find(`button[aria-label="${t('scoring.penalty.remove')}"]`).trigger('click')
    expect(wrapper.emitted('remove-penalty')).toEqual([['pb']])
  })
})

describe('ScoringBoard — score-only mode', () => {
  const scoreOnly = { ...base, scoreOnly: true, canScore: true }
  const keys = (wrapper: Awaited<ReturnType<typeof mountSuspended>>) => wrapper.findAll('.board-key')

  it('has no ±1 keys in the normal mode', async () => {
    const wrapper = await mountSuspended(Board, { props: base })
    expect(keys(wrapper)).toHaveLength(0)
  })

  it('adds and removes a goal for each team', async () => {
    const wrapper = await mountSuspended(Board, { props: scoreOnly })
    // team A: −1 then +1 ; team B: +1 then −1 (mirrored)
    expect(keys(wrapper).map(k => k.text())).toEqual(['−1', '+1', '+1', '−1'])
    for (const key of keys(wrapper)) await key.trigger('click')
    expect(wrapper.emitted('bump')).toEqual([['A', -1], ['A', 1], ['B', 1], ['B', -1]])
  })

  it('locks the keys without the right to score', async () => {
    const wrapper = await mountSuspended(Board, { props: { ...scoreOnly, canScore: false } })
    expect(keys(wrapper).every(k => k.attributes('disabled') !== undefined)).toBe(true)
  })
})

describe('ScoringBoard — pitch sides (§0.18)', () => {
  it('puts B on the left and A on the right when A starts on the right, scores and penalties included (SIDE-02)', async () => {
    const wrapper = await mountSuspended(Board, {
      props: { ...base, sides: 'A_RIGHT', penaltiesA: [penalty({ playerNumber: 7 })], penaltiesB: [] },
    })
    expect(wrapper.findAll('.board-team').map(el => el.text())).toEqual(['Paris', 'Lyon'])
    expect(wrapper.findAll('.board-score').map(el => el.text())).toEqual(['2', '3'])
    // A's penalty follows A, now on the right
    expect(wrapper.findAll('.board-side')[1]!.text()).toContain('7')
  })

  it('shows the letter A or B of each team next to its name (SIDE-23)', async () => {
    const wrapper = await mountSuspended(Board, { props: { ...base, sides: 'A_RIGHT' } })
    expect(wrapper.findAll('.board-letter').map(el => el.text())).toEqual(['B', 'A'])
  })

  it('shows no letter on request (public scoreboard look)', async () => {
    const wrapper = await mountSuspended(Board, { props: { ...base, showLetters: false } })
    expect(wrapper.findAll('.board-letter')).toHaveLength(0)
  })

  it('keeps the real team of the ±1 keys whatever the sides', async () => {
    const wrapper = await mountSuspended(Board, { props: { ...base, sides: 'A_RIGHT', scoreOnly: true, canScore: true } })
    for (const key of wrapper.findAll('.board-key')) await key.trigger('click')
    expect(wrapper.emitted('bump')).toEqual([['B', -1], ['B', 1], ['A', 1], ['A', -1]])
  })

  it('shows the change-of-ends notice given by the page (SIDE-22)', async () => {
    const wrapper = await mountSuspended(Board, { props: { ...base, sidesNotice: 'Changement de côté — M2' } })
    expect(wrapper.find('[role="status"]').text()).toContain('Changement de côté — M2')
  })
})
