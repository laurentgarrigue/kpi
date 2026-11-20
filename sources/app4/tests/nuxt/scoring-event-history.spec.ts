// PAGE_SCORING.md §7.1 — event history: symmetric table (md+) and vertical list (mobile), same data.
import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import EventHistory from '../../components/scoring/EventHistory.vue'
import type { ScoringEvent } from '../../types/scoring'
import { t } from './helpers'

const NEWEST_FIRST_KEY = 'kpi.scoring.timelineNewestFirst'

const event = (overrides: Partial<ScoringEvent> = {}): ScoringEvent => ({
  uid: 'e1', code: 'B', period: 'M1', tpsJeu: '00:08:00', team: 'A', player: '101',
  number: 7, reason: '', nom: 'Dupont', prenom: 'Jean', ...overrides,
})

// Time is the time LEFT: 08:00 is older than 02:00 in the same period.
const OLD = event({ uid: 'old', tpsJeu: '00:08:00', team: 'A', nom: 'Dupont', prenom: 'Jean' })
const MID = event({ uid: 'mid', tpsJeu: '00:05:00', team: 'B', nom: 'Martin', prenom: 'Luc', number: 9 })
const NEW = event({ uid: 'new', tpsJeu: '00:02:00', team: 'A', nom: 'Durand', prenom: 'Paul', number: 11 })

const tableRows = (w: Awaited<ReturnType<typeof mountSuspended>>) => w.findAll('table tbody tr').filter(r => r.findAll('td').length === 3 && r.find('td[colspan]').exists() === false)
const listRows = (w: Awaited<ReturnType<typeof mountSuspended>>) => w.findAll('ul > li').filter(li => li.classes().includes('flex'))
const toggleButtons = (w: Awaited<ReturnType<typeof mountSuspended>>) => w.findAll('table td[colspan] button')

beforeEach(() => localStorage.removeItem(NEWEST_FIRST_KEY))
afterEach(() => localStorage.removeItem(NEWEST_FIRST_KEY))

describe('ScoringEventHistory — order and direction', () => {
  it('shows the most recent fact first by default', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [OLD, NEW, MID] } })
    expect(tableRows(w).map(r => r.text())).toEqual([
      expect.stringContaining('DURAND'), expect.stringContaining('MARTIN'), expect.stringContaining('DUPONT'),
    ])
  })

  it('flips the order with the time header and remembers it on the device', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [OLD, NEW, MID] } })
    await w.find('table thead button').trigger('click')
    expect(tableRows(w)[0]!.text()).toContain('DUPONT')
    expect(localStorage.getItem(NEWEST_FIRST_KEY)).toBe('0')
  })

  it('restores the remembered direction at mount', async () => {
    localStorage.setItem(NEWEST_FIRST_KEY, '0')
    const w = await mountSuspended(EventHistory, { props: { events: [OLD, NEW] } })
    expect(tableRows(w)[0]!.text()).toContain('DUPONT')
  })

  it('titles the direction button with the way time flows', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [OLD] } })
    const button = w.find('table thead button')
    expect(button.attributes('title')).toBe(t('scoring.history_newest_first'))
    await button.trigger('click')
    expect(button.attributes('title')).toBe(t('scoring.history_oldest_first'))
  })
})

describe('ScoringEventHistory — symmetric table', () => {
  it('puts team A on the left and team B on the right', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [OLD, MID], teamA: 'Lyon', teamB: 'Paris' } })
    const heads = w.findAll('table thead th')
    expect(heads[0]!.text()).toBe('Lyon')
    expect(heads[2]!.text()).toBe('Paris')
    const [bRow, aRow] = tableRows(w)
    const bCells = bRow!.findAll('td')
    expect(bCells[0]!.text()).toBe('')
    expect(bCells[2]!.text()).toContain('MARTIN')
    expect(aRow!.findAll('td')[0]!.text()).toContain('DUPONT')
    expect(aRow!.findAll('td')[2]!.text()).toBe('')
  })

  it('shows the time without the hours part', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [OLD] } })
    expect(tableRows(w)[0]!.findAll('td')[1]!.text()).toContain('08:00')
    expect(tableRows(w)[0]!.findAll('td')[1]!.text()).not.toContain('00:08:00')
  })

  it('names the team for a team-level event', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [event({ player: '0', nom: undefined })] } })
    expect(tableRows(w)[0]!.text()).toContain(`${t('scoring.team')} A`)
  })

  it('spells out the reason of a card', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [event({ code: 'J', reason: 'r_pad' })] } })
    expect(tableRows(w)[0]!.text()).toContain(`(${t('scoring.reason.r_pad')})`)
  })

  it('edits a row on click when scoring is allowed, and not otherwise', async () => {
    const allowed = await mountSuspended(EventHistory, { props: { events: [OLD], canScore: true } })
    await tableRows(allowed)[0]!.trigger('click')
    expect(allowed.emitted('edit')![0]![0]).toMatchObject({ uid: 'old' })

    const readOnly = await mountSuspended(EventHistory, { props: { events: [OLD], canScore: false } })
    await tableRows(readOnly)[0]!.trigger('click')
    expect(readOnly.emitted('edit')).toBeUndefined()
  })

  it('removes without editing when the trash button is clicked', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [OLD], canScore: true } })
    await tableRows(w)[0]!.find('button').trigger('click')
    expect(w.emitted('remove')![0]![0]).toMatchObject({ uid: 'old' })
    expect(w.emitted('edit')).toBeUndefined()
  })

  it('disables the trash button without the scoring right', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [OLD], canScore: false } })
    expect(tableRows(w)[0]!.find('button').attributes('disabled')).toBeDefined()
  })

  it('highlights the row being edited', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [OLD, NEW], editingUid: 'old' } })
    const [newRow, oldRow] = tableRows(w)
    expect(oldRow!.classes()).toContain('ring-2')
    expect(newRow!.classes()).not.toContain('ring-2')
  })

  it('shows the click hint only when there are facts', async () => {
    const empty = await mountSuspended(EventHistory, { props: { events: [] } })
    expect(empty.text()).not.toContain(t('scoring.history_table_hint'))
    const full = await mountSuspended(EventHistory, { props: { events: [OLD] } })
    expect(full.text()).toContain(t('scoring.history_table_hint'))
  })
})

describe('ScoringEventHistory — vertical list (mobile)', () => {
  it('lists the facts with the team letter on its own side', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [OLD, MID] } })
    const [bItem, aItem] = listRows(w)
    expect(aItem!.text()).toContain('A')
    expect(aItem!.text()).toContain('DUPONT Jean')
    expect(bItem!.text()).toContain('MARTIN Luc')
    expect(bItem!.text()).toContain('B')
  })

  it('emits edit and remove from the row buttons', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [OLD], canScore: true } })
    const buttons = listRows(w)[0]!.findAll('button')
    await buttons[0]!.trigger('click')
    await buttons[1]!.trigger('click')
    expect(w.emitted('edit')![0]![0]).toMatchObject({ uid: 'old' })
    expect(w.emitted('remove')![0]![0]).toMatchObject({ uid: 'old' })
  })

  it('disables both buttons without the scoring right', async () => {
    const w = await mountSuspended(EventHistory, { props: { events: [OLD], canScore: false } })
    for (const b of listRows(w)[0]!.findAll('button')) expect(b.attributes('disabled')).toBeDefined()
  })
})

describe('ScoringEventHistory — recent extract (§7.1)', () => {
  const events = [OLD, MID, NEW]

  it('shows everything when no recent count is given', async () => {
    const w = await mountSuspended(EventHistory, { props: { events } })
    expect(tableRows(w)).toHaveLength(3)
    expect(toggleButtons(w)).toHaveLength(0)
  })

  it('keeps only the most recent facts, with a toggle announcing how many are hidden', async () => {
    const w = await mountSuspended(EventHistory, { props: { events, recentCount: 2 } })
    expect(tableRows(w)).toHaveLength(2)
    expect(tableRows(w)[1]!.text()).toContain('MARTIN')
    expect(toggleButtons(w)[0]!.text()).toContain(t('scoring.history_more', { count: 1 }))
  })

  it('expands to everything, then goes back to the last N', async () => {
    const w = await mountSuspended(EventHistory, { props: { events, recentCount: 2 } })
    await toggleButtons(w)[0]!.trigger('click')
    expect(tableRows(w)).toHaveLength(3)
    expect(toggleButtons(w)[0]!.text()).toContain(t('scoring.history_recent', { n: 2 }))
    await toggleButtons(w)[0]!.trigger('click')
    expect(tableRows(w)).toHaveLength(2)
  })

  it('offers no toggle when everything already fits', async () => {
    const w = await mountSuspended(EventHistory, { props: { events, recentCount: 5 } })
    expect(toggleButtons(w)).toHaveLength(0)
  })

  it('moves the toggle above the list when the oldest are on top', async () => {
    localStorage.setItem(NEWEST_FIRST_KEY, '0')
    const w = await mountSuspended(EventHistory, { props: { events, recentCount: 2 } })
    const rows = w.findAll('table tbody tr')
    expect(rows[0]!.find('td[colspan]').exists()).toBe(true)
    expect(rows.at(-1)!.find('td[colspan]').exists()).toBe(false)
  })

  it('keeps the latest N whichever way they are shown', async () => {
    localStorage.setItem(NEWEST_FIRST_KEY, '0')
    const w = await mountSuspended(EventHistory, { props: { events, recentCount: 2 } })
    const text = tableRows(w).map(r => r.text()).join(' ')
    expect(text).not.toContain('DUPONT')
  })
})
