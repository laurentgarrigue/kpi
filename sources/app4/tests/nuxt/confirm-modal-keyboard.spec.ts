// ConfirmModal keyboard handling: safe default focus, ←/→ switch, Esc cancels.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { nextTick } from 'vue'
import ConfirmModal from '../../components/admin/ConfirmModal.vue'
import { track } from './modal-helpers'

const footerButtons = () => [...document.body.querySelectorAll<HTMLButtonElement>('button')].slice(-2)

const open = async (props: Record<string, unknown> = {}) => {
  const wrapper = track(await mountSuspended(ConfirmModal, {
    props: { open: false, title: 'T', message: 'M', variant: 'info', ...props },
  }))
  await wrapper.setProps({ open: true })
  await nextTick()
  await nextTick()
  return wrapper
}

const press = (key: string) => {
  document.activeElement!.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }))
  return nextTick()
}

describe('ConfirmModal keyboard', () => {
  it('focuses the confirm button on opening', async () => {
    await open()
    expect(document.activeElement).toBe(footerButtons()[1])
  })

  it('focuses « Annuler » for a destructive dialog (danger by default)', async () => {
    await open({ variant: undefined })
    expect(document.activeElement).toBe(footerButtons()[0])
  })

  it('focuses « Annuler » when confirmation is disabled', async () => {
    await open({ disabledConfirm: true })
    expect(document.activeElement).toBe(footerButtons()[0])
  })

  it('switches between the buttons with the arrows', async () => {
    await open()
    await press('ArrowLeft')
    expect(document.activeElement).toBe(footerButtons()[0])
    await press('ArrowRight')
    expect(document.activeElement).toBe(footerButtons()[1])
  })

  it('does not move onto a disabled confirm button', async () => {
    await open({ disabledConfirm: true })
    await press('ArrowRight')
    expect(document.activeElement).toBe(footerButtons()[0])
  })

  it('closes on Escape and ignores other keys', async () => {
    const wrapper = await open()
    await press('a')
    expect(wrapper.emitted('close')).toBeUndefined()
    await press('Escape')
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
