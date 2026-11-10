/**
 * Copy of sources/app3/app/utils/medals.ts: app2 does not use kpi-layer before phase 6 (CLAUDE.md); to merge then.
 * Match medals: pure display rules (DOC/specs/public/FEATURE_MATCH_MEDALS.md § 7, § 8). Names, explanations and
 * pictograms come from the api2 catalogue (`/medals/catalog`), never from the code: publishing a new element needs
 * no deployment of the site.
 */

export type MedalFamily = 'elan' | 'reaction' | 'endurance' | 'superiorite' | 'finition' | 'defense' | 'esprit' | 'arbitrage' | 'match' | 'analyse'

export interface MedalItem {
  code: string
  type: 'medal' | 'label' | 'stat'
  /** T team, P player, R referee. */
  scopes: string[]
  family: MedalFamily
  data_level: 'S' | 'T'
  name_fr: string
  name_en: string
  explanation_fr: string
  explanation_en: string
  icon: string
  /** Inner markup of the 24 × 24 pictogram, in `currentColor`. */
  icon_svg: string
  surfaces?: ('app2' | 'app3')[]
}

export interface MedalCatalog {
  season: string | null
  seasons: string[]
  items: MedalItem[]
}

export interface MedalName {
  main: string
  /** The English name next to the French one, until the vocabulary settles (§ 1.1); null when identical. */
  english: string | null
}

const ENGLISH = 'en'

/** Family colour (pictogram, counter) and tinted disc; literal classes so that Tailwind generates them. */
export const FAMILY_CLASSES: Record<MedalFamily, { text: string, disc: string }> = {
  elan: { text: 'text-fam-elan', disc: 'bg-fam-elan/15 ring-fam-elan/35' },
  reaction: { text: 'text-fam-reaction', disc: 'bg-fam-reaction/15 ring-fam-reaction/35' },
  endurance: { text: 'text-fam-endurance', disc: 'bg-fam-endurance/15 ring-fam-endurance/35' },
  superiorite: { text: 'text-fam-superiorite', disc: 'bg-fam-superiorite/15 ring-fam-superiorite/35' },
  finition: { text: 'text-fam-finition', disc: 'bg-fam-finition/15 ring-fam-finition/35' },
  defense: { text: 'text-fam-defense', disc: 'bg-fam-defense/15 ring-fam-defense/35' },
  esprit: { text: 'text-fam-esprit', disc: 'bg-fam-esprit/15 ring-fam-esprit/35' },
  arbitrage: { text: 'text-fam-arbitrage', disc: 'bg-fam-arbitrage/15 ring-fam-arbitrage/35' },
  match: { text: 'text-fam-match', disc: 'bg-fam-match/15 ring-fam-match/35' },
  analyse: { text: 'text-fam-analyse', disc: 'bg-fam-analyse/15 ring-fam-analyse/35' },
}

export function medalName(item: MedalItem, locale: string): MedalName {
  if (locale === ENGLISH) {
    return { main: item.name_en, english: null }
  }
  return { main: item.name_fr, english: item.name_fr === item.name_en ? null : item.name_en }
}

/** « Supériorité · Powerplay » in French, « Powerplay » in English: for labels and accessible names. */
export function medalLabel(item: MedalItem, locale: string): string {
  const { main, english } = medalName(item, locale)
  return english ? `${main} · ${english}` : main
}

export function medalExplanation(item: MedalItem, locale: string): string {
  return locale === ENGLISH ? item.explanation_en : item.explanation_fr
}

/** @returns families in the order of their first element in the catalogue */
export function byFamily(items: MedalItem[]): { family: MedalFamily, items: MedalItem[] }[] {
  const groups = new Map<MedalFamily, MedalItem[]>()
  for (const item of items) {
    groups.set(item.family, [...(groups.get(item.family) ?? []), item])
  }
  return [...groups].map(([family, members]) => ({ family, items: members }))
}

/** Timeline statistics carry the counts of their perimeter (§ 3.4); the historical ones (scorers) do not. */
export function isTimelineStat(stat: { games_count?: number }): boolean {
  return stat.games_count !== undefined
}

/** Composed pictograms hold a mask: its id must be unique in the page, the pictogram being drawn many times. */
export function svgWithUniqueIds(svg: string, suffix: string): string {
  return svg.replace(/(id="|url\(#)(m-[a-z_]+)/g, `$1$2-${suffix}`)
}

/** Players of the stats and of the medals come from two tables: they meet on their names, not on a licence number. */
export function playerKey(firstName: string | null | undefined, lastName: string | null | undefined): string {
  return [firstName, lastName].map(part => (part ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase()).join('|')
}
