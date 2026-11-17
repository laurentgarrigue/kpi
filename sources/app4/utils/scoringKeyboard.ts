/**
 * Does the keyboard reach the scoring console? (PAGE_SCORING.md §0.14). Pure — the focus
 * tracking lives in composables/useKeyboardFocus.
 *
 * - `window`: the console window has lost the focus (address bar, scoreboard window, OBS…):
 *   nothing typed reaches it. Takes precedence over everything else.
 * - `off`: shortcuts disabled by the console (dialog, card-reason list, no entry allowed).
 * - `field`: the focus is in an editable field, where keys type text.
 * - `ready`: every shortcut answers.
 */
export type KeyboardState = 'ready' | 'field' | 'off' | 'window'

export function keyboardState(input: { windowFocused: boolean, fieldFocused: boolean, enabled: boolean }): KeyboardState {
  if (!input.windowFocused) return 'window'
  if (!input.enabled) return 'off'
  if (input.fieldFocused) return 'field'
  return 'ready'
}

/** Amber frame + "click in the console" pill: lost window focus, on a device with a keyboard. */
export function keyboardFrameShown(state: KeyboardState, keyboardLikely: boolean): boolean {
  return state === 'window' && keyboardLikely
}

/** Keys printed on the buttons are shown as live only when they answer. */
export function keyboardKeysActive(state: KeyboardState): boolean {
  return state === 'ready'
}

/** Inputs, text areas, selects and contenteditable: keys there type text, not shortcuts. */
export function isEditableTarget(target: EventTarget | null): boolean {
  if (typeof HTMLElement === 'undefined' || !(target instanceof HTMLElement)) return false
  const tag = target.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable
}
