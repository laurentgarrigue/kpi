// PAGE_SCORING.md §7.6 — match closure: score check, end time, officials' comments, lock.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { nextTick } from 'vue'
import CloseMatchModal from '../../components/scoring/CloseMatchModal.vue'
import { bodyButton, track } from './modal-helpers'
import { t } from './helpers'

const base = {
  open: false,
  teamA: 'Lyon',
  teamB: 'Paris',
  score: { A: 3, B: 2 },
  goals: { A: 3, B: 2 },
  comments: '',
}

const open = async (props: Record<string, unknown> = {}) => {
  // The form is primed each time `open` turns true, not at the mount.
  const wrapper = track(await mountSuspended(CloseMatchModal, { props: { ...base, ...props } }))
  await wrapper.setProps({ open: true })
  await nextTick()
  return wrapper
}

const timeInput = () => document.body.querySelector<HTMLInputElement>('input[type="time"]')!
const textarea = () => document.body.querySelector<HTMLTextAreaElement>('textarea')!
const type = async (el: HTMLInputElement | HTMLTextAreaElement, value: string) => {
  el.value = value
  el.dispatchEvent(new Event('input', { bubbles: true }))
  await nextTick()
}
const confirmButton = () => bodyButton(t('scoring.close.confirm'))!

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] })
  vi.setSystemTime(new Date(2026, 10, 20, 9, 5))
})
afterEach(() => vi.useRealTimers())

describe('ScoringCloseMatchModal — end time (§0.9)', () => {
  it('is pre-filled with the current time', async () => {
    await open()
    expect(timeInput().value).toBe('09:05')
  })

  it('keeps the end time already recorded when a closed match is reopened', async () => {
    await open({ recordedEndTime: '17:42:10' })
    expect(timeInput().value).toBe('17:42')
  })

  it('treats the legacy 00:00 blank as « none recorded »', async () => {
    await open({ recordedEndTime: '00:00:00' })
    expect(timeInput().value).toBe('09:05')
  })

  it('cannot be confirmed with an empty end time', async () => {
    const wrapper = await open()
    await type(timeInput(), '')
    expect(confirmButton().disabled).toBe(true)
    confirmButton().click()
    expect(wrapper.emitted('confirm')).toBeUndefined()
  })
})

describe('ScoringCloseMatchModal — score check', () => {
  it('shows the displayed score and the recorded goals of each team', async () => {
    await open()
    const cells = [...document.body.querySelectorAll('tbody td')].map(td => td.textContent?.trim())
    expect(cells).toEqual(['Lyon', '3', '3', 'Paris', '2', '2'])
    expect(document.body.textContent).not.toContain(t('scoring.close.mismatch'))
  })

  it('warns when score and goals differ, and aligns in one click', async () => {
    const wrapper = await open({ goals: { A: 4, B: 2 } })
    expect(document.body.textContent).toContain(t('scoring.close.mismatch'))
    bodyButton(t('scoring.close.align'))!.click()
    expect(wrapper.emitted('align')).toHaveLength(1)
  })
})

describe('ScoringCloseMatchModal — confirmation', () => {
  it('sends the end time, the trimmed comments and no lock by default', async () => {
    const wrapper = await open({ canLock: true })
    await type(timeInput(), '18:30')
    await type(textarea(), '  Réserve du capitaine A  ')
    confirmButton().click()
    expect(wrapper.emitted('confirm')).toEqual([[{ endTime: '18:30', comments: 'Réserve du capitaine A', lock: false }]])
  })

  it('starts the comments from the ones already recorded', async () => {
    await open({ comments: 'Déjà saisi' })
    expect(textarea().value).toBe('Déjà saisi')
  })

  it('offers the lock only to the profiles allowed to validate', async () => {
    await open({ canLock: false })
    expect(document.body.querySelector('[role="checkbox"], input[type="checkbox"]')).toBeNull()
  })

  it('locks the match after the closure when the box is ticked', async () => {
    const wrapper = await open({ canLock: true })
    document.body.querySelector<HTMLElement>('[role="checkbox"], input[type="checkbox"]')!.click()
    await nextTick()
    confirmButton().click()
    expect(wrapper.emitted('confirm')![0]![0]).toMatchObject({ lock: true })
  })

  it('does not confirm twice while the closure is being recorded', async () => {
    const wrapper = await open({ loading: true })
    confirmButton().click()
    expect(wrapper.emitted('confirm')).toBeUndefined()
  })

  it('closes with Cancel', async () => {
    const wrapper = await open()
    bodyButton(t('scoring.edit.cancel'))!.click()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
