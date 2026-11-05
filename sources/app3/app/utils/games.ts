import type { DrawSlot } from '#kpi-layer/utils/results/games'

const FRENCH_FIRST = '1er'
const ENGLISH_SUFFIXES: Record<string, string> = { one: 'st', two: 'nd', few: 'rd', other: 'th' }

/** « 1er », « 2e » / « 1st », « 2nd »: rank of a team in a pool (CMP-20). */
export function ordinal(rank: number, language: string): string {
  if (language.startsWith('fr')) {
    return rank === 1 ? FRENCH_FIRST : `${rank}e`
  }
  return `${rank}${ENGLISH_SUFFIXES[new Intl.PluralRules('en', { type: 'ordinal' }).select(rank)]}`
}

export interface Message {
  key: string
  params: Record<string, string | number>
}

/** i18n message of a team not assigned yet (« 1er poule B », « Vainqueur n° 222 »), CMP-20. */
export function drawSlotMessage(slot: DrawSlot | null, language: string): Message {
  switch (slot?.kind) {
    case 'pool':
      return { key: 'results.draw.pool', params: { rank: ordinal(slot.rank, language), pool: slot.pool } }
    case 'winner':
    case 'loser':
    case 'team':
      return { key: `results.draw.${slot.kind}`, params: { number: slot.game } }
    case 'text':
      return { key: 'results.draw.text', params: { text: slot.text } }
    default:
      return { key: 'results.draw.unknown', params: {} }
  }
}
