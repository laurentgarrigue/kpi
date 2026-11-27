import type { GameFormData } from '~/types/games'

/** Défaut d'écart entre deux matchs (minutes), repris par le formulaire de création. */
export const DEFAULT_GAME_INTERVAL_MINUTES = 40

/** Ce qu'il faut savoir d'un match pour proposer le suivant. */
export interface GameContinuationSource {
  idJournee: number | null
  dateMatch: string | null
  heureMatch: string | null
  numeroOrdre: number | null
  terrain: string | null
  type: string | null
}

/** « HH:MM » + `minutes`, sur 24 h ; chaîne vide si l'heure source est absente ou illisible. */
export const addMinutesToTime = (time: string | null, minutes: number): string => {
  const match = /^(\d{1,2}):(\d{2})/.exec(time ?? '')
  if (!match) return ''
  const total = Number(match[1]) * 60 + Number(match[2]) + minutes
  const hours = Math.floor(total / 60) % 24
  return `${String(hours).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`
}

/** Champs du formulaire de création qui prolongent `source` : même journée, date, terrain, N° + 1, heure + intervalle. */
export const continuationOf = (
  source: GameContinuationSource,
  intervalle: number = DEFAULT_GAME_INTERVAL_MINUTES,
): Pick<GameFormData, 'idJournee' | 'dateMatch' | 'heureMatch' | 'numeroOrdre' | 'terrain' | 'type' | 'intervalle'> => ({
  idJournee: source.idJournee,
  dateMatch: source.dateMatch ?? '',
  heureMatch: addMinutesToTime(source.heureMatch, intervalle),
  numeroOrdre: source.numeroOrdre != null ? source.numeroOrdre + 1 : null,
  terrain: source.terrain ?? '',
  type: source.type || 'C',
  intervalle,
})
