/**
 * Tracks what the keyboard state of the scoring console depends on (PAGE_SCORING.md §0.14):
 * - `windowFocused`: `focus` / `blur` of the window, plus a `document.hasFocus()` check every
 *   second (some focus moves — devtools, an embedded frame — send no `blur`);
 * - `fieldFocused`: the active element is an editable field;
 * - `keyboardLikely`: fine pointer (mouse) or a key already pressed — a tablet without keyboard
 *   has nothing to be warned about.
 */
import { isEditableTarget } from '~/utils/scoringKeyboard'

const FOCUS_CHECK_MS = 1000

export function useKeyboardFocus() {
  const windowFocused = ref(true)
  const fieldFocused = ref(false)
  const keyboardLikely = ref(false)

  const syncWindow = () => { windowFocused.value = document.hasFocus() }
  // focusout fires before the next focusin: read the active element once both have run.
  const syncField = () => { setTimeout(() => { fieldFocused.value = isEditableTarget(document.activeElement) }) }
  const onKeydown = () => { keyboardLikely.value = true }

  let timer: ReturnType<typeof setInterval> | null = null

  onMounted(() => {
    keyboardLikely.value = window.matchMedia?.('(pointer: fine)').matches ?? false
    syncWindow()
    syncField()
    window.addEventListener('focus', syncWindow)
    window.addEventListener('blur', syncWindow)
    document.addEventListener('focusin', syncField)
    document.addEventListener('focusout', syncField)
    window.addEventListener('keydown', onKeydown, { capture: true, passive: true })
    timer = setInterval(syncWindow, FOCUS_CHECK_MS)
  })
  onUnmounted(() => {
    window.removeEventListener('focus', syncWindow)
    window.removeEventListener('blur', syncWindow)
    document.removeEventListener('focusin', syncField)
    document.removeEventListener('focusout', syncField)
    window.removeEventListener('keydown', onKeydown, { capture: true })
    if (timer) clearInterval(timer)
  })

  return { windowFocused, fieldFocused, keyboardLikely }
}
