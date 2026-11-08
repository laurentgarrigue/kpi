/**
 * Options of the full-screen displays (PAGE_SCORING.md §0.15): chosen in a dialog before the
 * window opens (its popup has no editable address bar), remembered per device, and handed to
 * the display page in its URL — the display stays autonomous (no interaction, no storage).
 *
 * Each option is a boolean or the theme, and its URL parameter has the option's name; a new
 * option = one entry in DISPLAY_DEFAULTS (and its label in the dialog). Only values that
 * differ from the defaults go into the URL.
 */
export type DisplayKind = 'scoreboard' | 'shotclock'
export type DisplayTheme = 'dark' | 'light'

export interface ScoreboardOptions {
  theme: DisplayTheme
  /** Show the shot clock next to the game clock. */
  shotclock: boolean
}
export interface ShotclockOptions {
  theme: DisplayTheme
  /** Show the small game clock under the shot clock. */
  clock: boolean
}
export interface DisplayOptions {
  scoreboard: ScoreboardOptions
  shotclock: ShotclockOptions
}

export const DISPLAY_DEFAULTS: { readonly [K in DisplayKind]: Readonly<DisplayOptions[K]> } = {
  scoreboard: { theme: 'dark', shotclock: true },
  shotclock: { theme: 'dark', clock: true }
}

type Query = Record<string, unknown>
const THEMES: readonly DisplayTheme[] = ['dark', 'light']

/** Options → URL parameters (defaults left out): theme=light, shotclock=0, clock=0. */
export function displayQuery<K extends DisplayKind>(kind: K, options: DisplayOptions[K]): Record<string, string> {
  const query: Record<string, string> = {}
  const defaults = DISPLAY_DEFAULTS[kind] as Record<string, unknown>
  for (const [key, value] of Object.entries(options)) {
    if (value === defaults[key]) continue
    query[key] = typeof value === 'boolean' ? (value ? '1' : '0') : String(value)
  }
  return query
}

/** URL parameters → options (display pages); unknown values fall back to the defaults. */
export function displayOptionsFromQuery<K extends DisplayKind>(kind: K, query: Query): DisplayOptions[K] {
  const parsed: Record<string, unknown> = {}
  for (const [key, def] of Object.entries(DISPLAY_DEFAULTS[kind])) {
    const raw = query[key]
    if (typeof def === 'boolean') parsed[key] = raw === '0' ? false : raw === '1' ? true : def
    else parsed[key] = THEMES.includes(raw as DisplayTheme) ? raw : def
  }
  return parsed as unknown as DisplayOptions[K]
}

/** Device preference (localStorage) over the defaults; bad values and unknown keys dropped. */
export function mergeStoredDisplayOptions<K extends DisplayKind>(kind: K, stored: Query): DisplayOptions[K] {
  const merged: Record<string, unknown> = {}
  for (const [key, def] of Object.entries(DISPLAY_DEFAULTS[kind])) {
    const value = stored[key]
    const valid = typeof def === 'boolean' ? typeof value === 'boolean' : THEMES.includes(value as DisplayTheme)
    merged[key] = valid ? value : def
  }
  return merged as unknown as DisplayOptions[K]
}
