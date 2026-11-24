// PAGE_SCORING.md §6.5 / §0.9 — keyboard shortcuts: click « change », press the key; one key = one action.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { nextTick } from 'vue'
import ShortcutsModal from '../../components/scoring/ShortcutsModal.vue'
import { SHORTCUT_ACTIONS, SHORTCUT_LOCALES, shortcutDefaults } from '../../utils/scoringShortcuts'
import { bodyButton, bodyButtonByLabel, track } from './modal-helpers'
import { t } from './helpers'

const bindings = shortcutDefaults('fr')

const openModal = async () => {
  // Capture mode listens to the keyboard from the moment `open` turns true (not from the mount).
  const wrapper = track(await mountSuspended(ShortcutsModal, { props: { open: false, bindings } }))
  await wrapper.setProps({ open: true })
  return wrapper
}

const changeButtons = () => [...document.body.querySelectorAll('button')].filter(b => b.textContent?.trim() === t('scoring.shortcuts.change'))
const press = async (key: string) => {
  window.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }))
  await nextTick()
}

describe('ScoringShortcutsModal — display', () => {
  it('renders nothing while closed', async () => {
    track(await mountSuspended(ShortcutsModal, { props: { open: false, bindings } }))
    expect(document.body.textContent).not.toContain(t('scoring.shortcuts.title'))
  })

  it('lists every action with its current key', async () => {
    await openModal()
    expect(document.body.textContent).toContain(t('scoring.shortcuts.title'))
    expect(changeButtons()).toHaveLength(SHORTCUT_ACTIONS.length)
  })

  it('offers one « defaults » link per language, whatever the interface language', async () => {
    await openModal()
    for (const locale of SHORTCUT_LOCALES) {
      expect(bodyButtonByLabel(`${t('scoring.shortcuts.reset')} ${locale.toUpperCase()}`)).not.toBeNull()
    }
  })
})

describe('ScoringShortcutsModal — capture of a key', () => {
  it('asks for a key on the clicked row, then assigns the key pressed', async () => {
    const wrapper = await openModal()
    changeButtons()[1]!.click()
    await nextTick()
    expect(document.body.textContent).toContain(t('scoring.shortcuts.press_key'))

    await press('x')
    expect(wrapper.emitted('set')).toEqual([[SHORTCUT_ACTIONS[1], 'x']])
    expect(document.body.textContent).not.toContain(t('scoring.shortcuts.press_key'))
  })

  it('cancels with Escape, assigning nothing', async () => {
    const wrapper = await openModal()
    changeButtons()[0]!.click()
    await nextTick()
    await press('Escape')
    expect(wrapper.emitted('set')).toBeUndefined()
    expect(document.body.textContent).not.toContain(t('scoring.shortcuts.press_key'))
  })

  it('locks the other rows while one is waiting for its key', async () => {
    await openModal()
    changeButtons()[0]!.click()
    await nextTick()
    const [waiting, ...others] = changeButtons()
    expect(waiting!.disabled).toBe(false)
    expect(others.every(button => button.disabled)).toBe(true)
  })

  it('a second click on the same row cancels the capture', async () => {
    const wrapper = await openModal()
    changeButtons()[2]!.click()
    await nextTick()
    changeButtons()[2]!.click()
    await nextTick()
    await press('y')
    expect(wrapper.emitted('set')).toBeUndefined()
  })

  it('ignores the keyboard when no row is waiting', async () => {
    const wrapper = await openModal()
    await press('z')
    expect(wrapper.emitted('set')).toBeUndefined()
  })

  it('stops listening to the keyboard once closed', async () => {
    const wrapper = await openModal()
    changeButtons()[0]!.click()
    await nextTick()
    await wrapper.setProps({ open: false })
    await press('q')
    expect(wrapper.emitted('set')).toBeUndefined()
  })
})

describe('ScoringShortcutsModal — reset and close', () => {
  it('restores the defaults of the chosen language', async () => {
    const wrapper = await openModal()
    bodyButtonByLabel(`${t('scoring.shortcuts.reset')} EN`)!.click()
    expect(wrapper.emitted('reset')).toEqual([['en']])
  })

  it('closes with the button', async () => {
    const wrapper = await openModal()
    bodyButton(t('common.close'))!.click()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})
