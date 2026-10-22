<script setup lang="ts">
import { drawSlot } from '#kpi-layer/utils/results/games'
import type { ResultsGame, Side } from '#kpi-layer/utils/results/types'
import { drawSlotMessage } from '~/utils/games'

// One team of a game: logo and name (link to its page), or the draw slot of a team not assigned yet (CMP-19, CMP-20).
const props = defineProps<{ game: ResultsGame, side: Side, winner: boolean }>()
const { t } = useI18n()
const language = useLanguageTag()
const key = computed(() => (props.side === 'A' ? 'a' : 'b'))
const label = computed(() => props.game[`t_${key.value}_label`])
const slot = computed(() => drawSlot(props.game, props.side))
const waiting = computed(() => {
  if (slot.value || !label.value) {
    const message = drawSlotMessage(slot.value, language.value)
    return t(message.key, message.params)
  }
  return null
})
</script>

<template>
  <span class="flex min-w-0 items-center gap-2.5">
    <template v-if="waiting">
      <span class="truncate italic text-muted" data-testid="draw-slot">{{ waiting }}</span>
    </template>
    <template v-else>
      <ResultsTeamLogo :path="game[`t_${key}_logo`]" />
      <ResultsTeamName
        class="truncate"
        :label="label"
        :number="game[`t_${key}_number`]"
        :competition="game.c_code"
        :season="game.c_season"
        :strong="winner"
      />
    </template>
  </span>
</template>
