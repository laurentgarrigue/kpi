// Card reason picker: focus on opening, arrow navigation, Enter/click picks, Esc keeps the reason.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { nextTick } from 'vue'
import CardReasonModal from '../../components/scoring/CardReasonModal.vue'
import { CARD_REASONS } from '../../utils/scoringCardReasons'
import { track } from './modal-helpers'

const buttons = () => [...document.body.querySelectorAll<HTMLButtonElement>('[role="alertdialog"] button')]
const dialog = () => document.body.querySelector<HTMLElement>('[role="alertdialog"]')!

const open = async (modelValue = 'unknown') => {
  const wrapper = track(await mountSuspended(CardReasonModal, { props: { open: false, modelValue } }))
  await wrapper.setProps({ open: true })
  await nextTick()
  await nextTick()
  return wrapper
}

const press = (key: string) => {
  document.activeElement!.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }))
  return nextTick()
}

describe('CardReasonModal', () => {
  it('renders nothing while closed', async () => {
    track(await mountSuspended(CardReasonModal, { props: { open: false, modelValue: 'unknown' } }))
    expect(dialog()).toBeNull()
  })

  it('focuses the current reason on opening', async () => {
    await open('r_un')
    const index = CARD_REASONS.findIndex(r => r.code === 'r_un')
    expect(document.activeElement).toBe(buttons()[index])
  })

  it('falls back to the last choice for an unknown reason', async () => {
    await open('nope')
    expect(document.activeElement).toBe(buttons().at(-1))
  })

  it('moves with the arrows, wrapping around', async () => {
    await open('r_pad')
    await press('ArrowRight')
    expect(document.activeElement).toBe(buttons()[1])
    await press('ArrowDown')
    expect(document.activeElement).toBe(buttons()[4])
    await press('ArrowLeft')
    await press('ArrowUp')
    expect(document.activeElement).toBe(buttons()[0])
    await press('ArrowLeft')
    expect(document.activeElement).toBe(buttons().at(-1))
  })

  it('ignores other keys', async () => {
    await open('r_pad')
    await press('a')
    expect(document.activeElement).toBe(buttons()[0])
  })

  it('picks a reason on click, then closes', async () => {
    const wrapper = await open()
    buttons()[2]!.click()
    expect(wrapper.emitted('update:modelValue')![0]).toEqual([CARD_REASONS[2]!.code])
    expect(wrapper.emitted('close')).toHaveLength(1)
  })

  it('closes on Escape without changing the reason', async () => {
    const wrapper = await open()
    await press('Escape')
    expect(wrapper.emitted('close')).toHaveLength(1)
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
  })

  it('closes when the backdrop is clicked', async () => {
    const wrapper = await open()
    dialog().querySelector<HTMLElement>('.bg-black\\/50')!.click()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
