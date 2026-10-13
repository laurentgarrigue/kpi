<script setup lang="ts">
import type { GameStatus, ResultsGame } from '#kpi-layer/utils/results/types'

// Game status, never conveyed by colour only (CMP-05). `icon`: compact form of the pitch grid, an icon whose label
// stays read by screen readers and shown on hover (CMP-17).
const props = defineProps<{ game: ResultsGame, icon?: boolean }>()
const { t } = useI18n()

const ICONS: Record<GameStatus, string> = {
  ATT: 'i-heroicons-clock',
  ON: 'i-heroicons-play-circle-solid',
  END: 'i-heroicons-check-circle',
}
const label = computed(() => t(`results.status.${props.game.g_status}`))
const period = computed(() => (props.game.g_status === 'ON' && props.game.g_period ? props.game.g_period : null))
const title = computed(() => [label.value, period.value].filter(Boolean).join(' · '))
</script>

<template>
  <span
    v-if="icon"
    class="flex shrink-0 items-center gap-0.5 text-xs font-semibold"
    :class="{ 'text-kpi-red-700': game.g_status === 'ON', 'text-ink/60': game.g_status !== 'ON' }"
    :data-status="game.g_status"
    :title="title"
  >
    <UIcon :name="ICONS[game.g_status]" class="size-4" :class="{ 'animate-pulse': game.g_status === 'ON' }" aria-hidden="true" data-testid="status-icon" />
    <span class="sr-only">{{ label }}</span>
    <span v-if="period">{{ period }}</span>
  </span>
  <span
    v-else
    class="text-xs font-semibold uppercase"
    :class="{ 'text-kpi-red-700': game.g_status === 'ON', 'text-ink/70': game.g_status !== 'ON' }"
    :data-status="game.g_status"
  >
    {{ label }}<template v-if="period"> · {{ period }}</template>
  </span>
</template>
