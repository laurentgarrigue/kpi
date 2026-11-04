import type { MaybeRefOrGetter } from 'vue'
import { drawSlot } from '#kpi-layer/utils/results/games'
import type { ResultsGame, Side } from '#kpi-layer/utils/results/types'
import { drawSlotMessage } from '~/utils/games'

/**
 * One side of a game: its team (label, number, logo), or the text of the draw slot of a team not assigned yet
 * (« 1er poule B », « Vainqueur n° 222 », CMP-20).
 */
export function useGameSide(game: MaybeRefOrGetter<ResultsGame>, side: MaybeRefOrGetter<Side>) {
  const { t } = useI18n()
  const language = useLanguageTag()
  const prefix = computed(() => (toValue(side) === 'A' ? 't_a_' : 't_b_') as 't_a_' | 't_b_')
  const label = computed(() => toValue(game)[`${prefix.value}label`])
  const number = computed(() => toValue(game)[`${prefix.value}number`])
  const logo = computed(() => toValue(game)[`${prefix.value}logo`])
  const waiting = computed(() => {
    const slot = drawSlot(toValue(game), toValue(side))
    if (!slot && label.value) {
      return null
    }
    const message = drawSlotMessage(slot, language.value)
    return t(message.key, message.params)
  })
  return { label, number, logo, waiting }
}
