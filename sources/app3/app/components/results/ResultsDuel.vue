<script setup lang="ts">
import { winnerSide } from '#kpi-layer/utils/results/games'
import type { ResultsGame, Side } from '#kpi-layer/utils/results/types'
import { gameLink } from '~/utils/page-links'

/**
 * A game as two stacked teams with their scores on a narrow score column (PAGE_COMPETITION.md § 2.5): progress
 * (CMP-24) and pitches. `followed` / `follow`: highlight of a team's path.
 * `untagged`: no `data-game`, when the duel sits in a card that carries it (pitches).
 */
const props = defineProps<{ game: ResultsGame, followed?: number | null, untagged?: boolean }>()
const emit = defineEmits<{ follow: [number] }>()
const context = usePageLinkContext()
const winner = computed(() => winnerSide(props.game))
const sheet = computed(() => (props.game.g_status === 'ATT' ? null : gameLink(props.game.g_id, context.value).href))
const numberOf = (side: Side) => (side === 'A' ? props.game.t_a_number : props.game.t_b_number)
const isFollowed = computed(() => props.followed !== null && props.followed !== undefined
  && (numberOf('A') === props.followed || numberOf('B') === props.followed))
</script>

<template>
  <div class="grid grid-cols-[minmax(0,1fr)_2.5rem]" :data-game="untagged ? undefined : game.g_id" :data-followed="isFollowed ? '' : undefined">
    <div class="grid min-w-0 gap-1 py-1 pr-2 text-sm">
      <ResultsDuelTeam
        v-for="side in (['A', 'B'] as const)"
        :key="side"
        :game="game"
        :side="side"
        :winner="winner === side"
        :followed="followed"
        @follow="emit('follow', $event)"
      />
    </div>
    <ResultsScoreCell :game="game" :href="sheet" stacked class="mb-1.5 overflow-hidden rounded-md" />
  </div>
</template>
