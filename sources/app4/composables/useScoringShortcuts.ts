/**
 * Configurable keyboard shortcuts of the scoring console (spec §6.5, decisions §0.8/§0.9).
 *
 * The bindings are a per-device/user preference, persisted in localStorage. Defaults:
 * Espace = game clock start/stop · Entrée = shotclock start/reset 60 s · `.` = start/reset
 * 40 s · `0` = shotclock stop (back to "--") · `+`/`−` = shotclock ±1 s.
 *
 * Shortcuts are neutralized while the focus is in an editable field, and can be globally
 * disabled through the `enabled` ref (post-match mode, capture modal open…).
 */

export type ShortcutAction =
  | 'gameClockToggle'
  | 'shotclockStart60'
  | 'shotclockStart40'
  | 'shotclockStop'
  | 'shotclockPlus'
  | 'shotclockMinus'

export const SHORTCUT_ACTIONS: ShortcutAction[] = [
  'gameClockToggle',
  'shotclockStart60',
  'shotclockStart40',
  'shotclockStop',
  'shotclockPlus',
  'shotclockMinus'
]

/** Default bindings (KeyboardEvent.key values — ' ' is the space bar). */
export const SHORTCUT_DEFAULTS: Record<ShortcutAction, string> = {
  gameClockToggle: ' ',
  shotclockStart60: 'Enter',
  shotclockStart40: '.',
  shotclockStop: '0',
  shotclockPlus: '+',
  shotclockMinus: '-'
}

const STORAGE_KEY = 'kpi.scoring.shortcuts'

/** Human label of a key for the settings UI. */
export function shortcutKeyLabel(key: string): string {
  if (key === ' ') return 'Espace'
  if (key === '') return '—'
  return key.length === 1 ? key.toUpperCase() : key
}

const isEditableTarget = (target: EventTarget | null): boolean => {
  if (!(target instanceof HTMLElement)) return false
  const tag = target.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable
}

export function useScoringShortcuts(
  handlers: Partial<Record<ShortcutAction, () => void>>,
  options: { enabled?: Ref<boolean> } = {}
) {
  const bindings = ref<Record<ShortcutAction, string>>({ ...SHORTCUT_DEFAULTS })

  const load = () => {
    if (typeof window === 'undefined') return
    try {
      const raw = window.localStorage.getItem(STORAGE_KEY)
      if (raw) bindings.value = { ...SHORTCUT_DEFAULTS, ...JSON.parse(raw) }
    } catch { /* corrupted storage → defaults */ }
  }

  const persist = () => {
    if (typeof window === 'undefined') return
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(bindings.value))
  }

  /** Rebind an action; the key is stolen from any action that used it (one key = one action). */
  const setBinding = (action: ShortcutAction, key: string) => {
    for (const a of SHORTCUT_ACTIONS) {
      if (a !== action && bindings.value[a] === key) bindings.value[a] = ''
    }
    bindings.value[action] = key
    persist()
  }

  const resetDefaults = () => {
    bindings.value = { ...SHORTCUT_DEFAULTS }
    persist()
  }

  const onKeydown = (event: KeyboardEvent) => {
    if (options.enabled && !options.enabled.value) return
    if (event.repeat || isEditableTarget(event.target)) return
    const action = SHORTCUT_ACTIONS.find(a => bindings.value[a] !== '' && bindings.value[a] === event.key)
    if (!action) return
    const handler = handlers[action]
    if (!handler) return
    event.preventDefault()
    handler()
  }

  onMounted(() => {
    load()
    window.addEventListener('keydown', onKeydown)
  })
  onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown)
  })

  return { bindings, setBinding, resetDefaults }
}
