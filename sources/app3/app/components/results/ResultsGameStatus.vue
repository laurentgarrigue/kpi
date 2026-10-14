<script setup lang="ts">
import { displayStatus, gamePeriod, type DisplayStatus } from '#kpi-layer/utils/results/games'
import type { ResultsGame } from '#kpi-layer/utils/results/types'

// Game status, never conveyed by colour only (CMP-05): pending, in progress (with its period), provisional (finished,
// not locked yet) or finished, as in app2. `icon`: compact form of the pitch grid, a coloured icon whose label stays
// read by screen readers and shown on hover, with the abbreviated period (CMP-17).
const props = defineProps<{ game: ResultsGame, icon?: boolean }>()
const { t } = useI18n()

const STYLES: Record<DisplayStatus, { icon: string, colour: string }> = {
  ATT: { icon: 'i-heroicons-clock', colour: 'text-ink/60' },
  ON: { icon: 'i-heroicons-play-circle-solid', colour: 'text-kpi-blue-600' },
  PROVISIONAL: { icon: 'i-heroicons-ellipsis-horizontal-circle-solid', colour: 'text-orange-600' },
  END: { icon: 'i-heroicons-check-circle-solid', colour: 'text-kpi-green-600' },
}
const status = computed(() => displayStatus(props.game))
const label = computed(() => t(`results.status.${status.value}`))
const period = computed(() => (props.game.g_status === 'ON' ? gamePeriod(props.game.g_period) : null))
const periodText = (short: boolean) => (period.value
  ? t(`results.period.${period.value.kind}${short ? 'Short' : ''}`, { n: period.value.number ?? '' })
  : null)
const title = computed(() => [label.value, periodText(false)].filter(Boolean).join(' · '))
</script>

<template>
  <span
    v-if="icon"
    class="flex shrink-0 items-center gap-0.5 text-xs font-semibold"
    :class="STYLES[status].colour"
    :data-status="status"
    :title="title"
  >
    <UIcon :name="STYLES[status].icon" class="size-5" :class="[STYLES[status].colour, { 'animate-pulse': status === 'ON' }]" aria-hidden="true" data-testid="status-icon" />
    <span class="sr-only">{{ label }}</span>
    <span v-if="period" data-testid="status-period">{{ periodText(true) }}</span>
  </span>
  <span v-else class="text-xs font-semibold uppercase" :class="STYLES[status].colour" :data-status="status">{{ title }}</span>
</template>
