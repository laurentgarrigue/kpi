// PAGE_SCORING.md §0.18 — the sides window: check at kick-off (D15) and confirmed manual swap (D4).
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { nextTick } from 'vue'
import SidesModal from '../../components/scoring/SidesModal.vue'
import { bodyButton, track } from './modal-helpers'
import { t } from './helpers'

const dialog = () => document.body.querySelector<HTMLElement>('[role="alertdialog"]')
const halves = () => [...document.body.querySelectorAll<HTMLElement>('[data-half]')].map(el => el.textContent?.replace(/\s+/g, ' ').trim())

const mount = async (props: Record<string, unknown>) => {
  const wrapper = track(await mountSuspended(SidesModal, {
    props: { open: true, teamA: 'Lyon', teamB: 'Paris', period: 'M1', ...props },
  }))
  await nextTick()
  return wrapper
}

describe('SidesModal — check at kick-off (D15)', () => {
  it('renders nothing while closed', async () => {
    track(await mountSuspended(SidesModal, { props: { open: false, mode: 'check', sides: 'A_LEFT', teamA: 'Lyon', teamB: 'Paris', period: 'M1' } }))
    expect(dialog()).toBeNull()
  })

  it('shows each team with its letter on its side, seen from the table (SIDE-19)', async () => {
    await mount({ mode: 'check', sides: 'A_LEFT' })
    expect(dialog()!.textContent).toContain(t('scoring.sides.check_title'))
    expect(halves()[0]).toContain('A')
    expect(halves()[0]).toContain('Lyon')
    expect(halves()[1]).toContain('B')
    expect(halves()[1]).toContain('Paris')
  })

  it('swaps the preview, then records the sides shown (SIDE-19)', async () => {
    const wrapper = await mount({ mode: 'check', sides: 'A_LEFT' })
    bodyButton(t('scoring.sides.check_swap'))!.click()
    await nextTick()
    expect(halves()[0]).toContain('Paris')
    bodyButton(t('scoring.sides.check_confirm'))!.click()
    expect(wrapper.emitted('confirm')).toEqual([['A_RIGHT']])
  })

  it('closes without recording on cancel', async () => {
    const wrapper = await mount({ mode: 'check', sides: 'A_LEFT' })
    bodyButton(t('scoring.sides.cancel'))!.click()
    expect(wrapper.emitted('confirm')).toBeUndefined()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})

describe('SidesModal — confirmed manual swap (D4)', () => {
  it('names the period and shows the sides after the swap (SIDE-06)', async () => {
    await mount({ mode: 'swap', sides: 'A_RIGHT', period: 'M2', periodLabel: 'Période 2' })
    expect(dialog()!.textContent).toContain(t('scoring.sides.swap_message', { period: 'Période 2' }))
    expect(halves()[0]).toContain('Paris')
    expect(halves()[1]).toContain('Lyon')
    // no preview swap in this mode: the result is what the confirmation applies
    expect(bodyButton(t('scoring.sides.check_swap'))).toBeUndefined()
  })

  it('confirms the swap, or changes nothing on cancel', async () => {
    const confirmed = await mount({ mode: 'swap', sides: 'A_RIGHT', period: 'M2' })
    bodyButton(t('scoring.sides.swap_confirm'))!.click()
    expect(confirmed.emitted('confirm')).toEqual([['A_RIGHT']])
  })
})
