import { afterEach } from 'vitest'
import type { VueWrapper } from '@vue/test-utils'

/**
 * Modals render through <Teleport to="body">: their content is NOT inside `wrapper.element`, so tests
 * query `document.body`. Wrappers must be unmounted, otherwise the teleported markup leaks into the next test.
 */
const mounted: VueWrapper[] = []

export const track = <T extends VueWrapper>(wrapper: T): T => {
  mounted.push(wrapper)
  return wrapper
}

afterEach(() => {
  for (const wrapper of mounted.splice(0)) wrapper.unmount()
})

export const bodyButton = (text: string): HTMLButtonElement | undefined =>
  [...document.body.querySelectorAll('button')].find(b => b.textContent?.trim() === text)

export const bodyButtonByLabel = (label: string): HTMLButtonElement | null =>
  document.body.querySelector(`button[aria-label="${label}"]`)
