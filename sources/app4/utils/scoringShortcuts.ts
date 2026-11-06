import type { ScoringEventCode } from '~/types/scoring'

/**
 * Keyboard shortcuts of the scoring console (PAGE_SCORING.md §6.5, §0.12): the actions, their
 * default keys and the merge with the preferences saved on the device. Pure — the keydown
 * wiring lives in composables/useScoringShortcuts.
 *
 * Clock keys are the same in every language. Fact keys follow the interface language
 * (initial of the fact), always rebindable. No digit is ever a fact key: once a fact is
 * armed, digits type the player's number.
 */

export type ShortcutAction =
  | 'gameClockToggle'
  | 'shotclockStart60'
  | 'shotclockStart40'
  | 'shotclockStop'
  | 'shotclockPlus'
  | 'shotclockMinus'
  | 'factGoal'
  | 'factGreen'
  | 'factYellow'
  | 'factRed'
  | 'factBlack'

/** Fact actions → stored fact code (D = black card, code kept for storage compatibility). */
const FACT_ACTIONS: Partial<Record<ShortcutAction, ScoringEventCode>> = {
  factGoal: 'B',
  factGreen: 'V',
  factYellow: 'J',
  factRed: 'R',
  factBlack: 'D'
}

export const SHORTCUT_ACTIONS: ShortcutAction[] = [
  'gameClockToggle',
  'shotclockStart60',
  'shotclockStart40',
  'shotclockStop',
  'shotclockPlus',
  'shotclockMinus',
  'factGoal',
  'factGreen',
  'factYellow',
  'factRed',
  'factBlack'
]

type Bindings = Record<ShortcutAction, string>

/** KeyboardEvent.key values — ' ' is the space bar. */
const CLOCK_KEYS = {
  gameClockToggle: ' ',
  shotclockStart60: 'Enter',
  shotclockStart40: '.',
  shotclockStop: '0',
  shotclockPlus: '+',
  shotclockMinus: '-'
} as const

const FACT_KEYS: Record<string, Pick<Bindings, 'factGoal' | 'factGreen' | 'factYellow' | 'factRed' | 'factBlack'>> = {
  // But, Vert, Jaune, Rouge, Noir
  fr: { factGoal: 'b', factGreen: 'v', factYellow: 'j', factRed: 'r', factBlack: 'n' },
  // Goal, grEen, Yellow, Red, blacK
  en: { factGoal: 'g', factGreen: 'e', factYellow: 'y', factRed: 'r', factBlack: 'k' }
}
const DEFAULT_LOCALE = 'fr'

/** Default bindings for an interface language (French when the language is unknown). */
export function shortcutDefaults(locale: string): Bindings {
  return { ...CLOCK_KEYS, ...(FACT_KEYS[locale] ?? FACT_KEYS[DEFAULT_LOCALE]!) }
}

/** Saved preferences over the language defaults; unknown actions and bad values are dropped. */
export function mergeStoredBindings(stored: Record<string, unknown>, locale: string): Bindings {
  const merged = shortcutDefaults(locale)
  for (const action of SHORTCUT_ACTIONS) {
    const key = stored[action]
    if (typeof key === 'string') merged[action] = key
  }
  return merged
}

/** Fact code armed by a shortcut action, or null when the action is not a fact. */
export function factCodeOfAction(action: string): ScoringEventCode | null {
  return FACT_ACTIONS[action as ShortcutAction] ?? null
}

/** Human label of a key for the settings UI. */
export function shortcutKeyLabel(key: string): string {
  if (key === ' ') return 'Espace'
  if (key === '') return '—'
  return key.length === 1 ? key.toUpperCase() : key
}
