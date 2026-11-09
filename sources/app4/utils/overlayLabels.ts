/**
 * Labels of the video overlay (PAGE_INCRUSTATION.md §8). Pure presentation.
 *
 * Two score-banner formats, chosen by the `skin` URL parameter:
 *  - `club`  (`skin=club|clubs`): full team names;
 *  - `inter` (`skin=inter|nations`, default): nations, first 3 characters of the team name
 *    upper-cased — what the legacy `live/score.php` showed ("France Women" → "FRA").
 */
export type OverlayTeamFormat = 'club' | 'inter'

const CLUB_SKINS = new Set(['club', 'clubs'])
const NATION_CODE_LENGTH = 3

export function overlayTeamFormat(skin: unknown): OverlayTeamFormat {
  return CLUB_SKINS.has(String(skin ?? '')) ? 'club' : 'inter'
}

export function overlayTeamLabel(name: string | null, format: OverlayTeamFormat): string {
  const label = (name ?? '').trim()
  return format === 'club' ? label : label.slice(0, NATION_CODE_LENGTH).toUpperCase()
}

/** "Category - Phase" (legacy `categ - phase`), skipping whichever is missing. */
export function overlayCategoryLine(categorie: string | null, phase: string | null): string {
  return [categorie, phase].map(part => (part ?? '').trim()).filter(Boolean).join(' - ')
}
