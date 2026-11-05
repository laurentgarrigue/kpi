<script setup lang="ts">
import type { ResultsGame, Side } from '#kpi-layer/utils/results/types'

// One team of a duel: logo and name, a button that follows its path when `followed` is given (CMP-24), the draw slot
// of a team not assigned yet (CMP-20).
const props = defineProps<{ game: ResultsGame, side: Side, winner: boolean, followed?: number | null }>()
const emit = defineEmits<{ follow: [number] }>()
const { label, number, logo, waiting } = useGameSide(() => props.game, () => props.side)
const followable = computed(() => props.followed !== undefined && number.value !== null)
</script>

<template>
  <div
    class="flex min-w-0 items-center gap-2 rounded-sm"
    :class="{ 'bg-sky/20': followed !== undefined && followed !== null && number === followed }"
    :data-winner="winner || undefined"
  >
    <template v-if="waiting">
      <span class="size-5 shrink-0" aria-hidden="true" />
      <span class="truncate italic text-muted" data-testid="draw-slot">{{ waiting }}</span>
    </template>
    <template v-else>
      <ResultsTeamLogo :path="logo" size="sm" />
      <button
        v-if="followable"
        type="button"
        class="truncate text-left hover:underline"
        :class="{ 'font-bold': winner || number === followed, 'text-kpi-blue-700': number === followed }"
        :aria-pressed="number === followed"
        :data-team="number"
        @click="emit('follow', number!)"
      >
        {{ label }}
      </button>
      <ResultsTeamName
        v-else
        class="truncate"
        :label="label"
        :number="number"
        :competition="game.c_code"
        :season="game.c_season"
        :strong="winner"
      />
    </template>
  </div>
</template>
