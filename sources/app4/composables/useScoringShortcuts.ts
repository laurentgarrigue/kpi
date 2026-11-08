/**
 * Configurable keyboard shortcuts of the scoring console (spec §6.5, decisions §0.8/§0.9).
 *
 * The bindings are a per-device/user preference, persisted in localStorage. Defaults
 * (utils/scoringShortcuts): Espace = game clock start/stop · `+` = shotclock start/reset
 * 60 s · `-` = start/reset 40 s · `*` = shotclock stop (back to "--") · one letter per fact,
 * after the interface language (FR B V J R N, EN G E Y R K). The defaults of either language
 * can be restored whatever the interface language.
 *
 * Shortcuts are neutralized while the focus is in an editable field, and can be globally
 * disabled through the `enabled` ref (post-match mode, capture modal open…).
 */

import { SHORTCUT_ACTIONS, mergeStoredBindings, shortcutDefaults, type ShortcutAction } from '~/utils/scoringShortcuts'

// v2 (2026-11-08): the shot clock left `Entrée` / `.` / `0`; bindings saved before kept
// `Entrée` on the 60 s start, so they are dropped rather than merged.
const STORAGE_KEY = 'kpi.scoring.shortcuts.v2'

const isEditableTarget = (target: EventTarget | null): boolean => {
  if (!(target instanceof HTMLElement)) return false
  const tag = target.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target.isContentEditable
}

export function useScoringShortcuts(
  handlers: Partial<Record<ShortcutAction, () => void>>,
  options: { enabled?: Ref<boolean>, locale?: Ref<string> } = {}
) {
  const locale = () => options.locale?.value ?? 'fr'
  const bindings = ref<Record<ShortcutAction, string>>(shortcutDefaults(locale()))

  const load = () => {
    if (typeof window === 'undefined') return
    try {
      const raw = window.localStorage.getItem(STORAGE_KEY)
      bindings.value = mergeStoredBindings(raw ? JSON.parse(raw) : {}, locale())
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

  /** Restore the defaults of a language (the interface language when omitted). */
  const resetDefaults = (defaultsLocale: string = locale()) => {
    bindings.value = shortcutDefaults(defaultsLocale)
    persist()
  }

  const onKeydown = (event: KeyboardEvent) => {
    if (options.enabled && !options.enabled.value) return
    if (event.repeat || isEditableTarget(event.target)) return
    // Browser/OS combinations (Ctrl+N, Cmd+…) keep their meaning.
    if (event.ctrlKey || event.metaKey || event.altKey) return
    // A letter works whatever the case (Caps Lock, Shift).
    const pressed = event.key.length === 1 ? event.key.toLowerCase() : event.key
    const action = SHORTCUT_ACTIONS.find(a => {
      const bound = bindings.value[a]
      return bound !== '' && (bound.length === 1 ? bound.toLowerCase() : bound) === pressed
    })
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
