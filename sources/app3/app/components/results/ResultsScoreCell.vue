<script setup lang="ts">
import { displayStatus, gamePeriod, hasScore, winnerSide } from '#kpi-layer/utils/results/games'
import type { ResultsGame } from '#kpi-layer/utils/results/types'
import { NEW_TAB_ATTRS } from '~/utils/links'

/**
 * Score block of the score column (PAGE_COMPETITION.md § 2.5, CMP-19): Agency FB, white on navy, the losing score
 * dimmed, red while the game is on. Only « in progress » (with its period) and « provisional » are flagged.
 * `stacked`: scores one above the other (mobile games list, pitches, progress).
 */
const props = defineProps<{ game: ResultsGame, href?: string | null, stacked?: boolean }>()
const { t } = useI18n()

const status = computed(() => displayStatus(props.game))
const played = computed(() => hasScore(props.game) && props.game.g_status !== 'ATT')
const winner = computed(() => winnerSide(props.game))
const period = computed(() => (props.game.g_status === 'ON' ? gamePeriod(props.game.g_period) : null))
const periodText = (short: boolean) => (period.value
  ? t(`results.period.${period.value.kind}${short ? 'Short' : ''}`, { n: period.value.number ?? '' })
  : '')
const flag = computed(() => {
  if (status.value === 'ON') {
    return { status: t('results.status.ON'), period: periodText(false), long: periodText(false) || t('results.status.ON'), short: periodText(true) }
  }
  if (status.value === 'PROVISIONAL') {
    return { status: t('results.status.PROVISIONAL'), period: '', long: t('results.status.PROVISIONAL'), short: t('results.provisionalShort') }
  }
  return null
})
const coefficients = computed(() => (props.game.g_coef_a !== 1 || props.game.g_coef_b !== 1)
  ? `×${props.game.g_coef_a} / ×${props.game.g_coef_b}`
  : null)
const a11y = computed(() => (played.value
  ? t('results.scoreLabel', { score: t('results.scoreA11y', { a: props.game.g_score_a, b: props.game.g_score_b }) })
  : t('results.notPlayed')))
const dim = (side: 'A' | 'B') => (winner.value && winner.value !== side ? 'opacity-50' : '')
</script>

<template>
  <span
    class="flex flex-col self-stretch text-white"
    :class="status === 'ON' ? 'bg-live' : 'bg-spine'"
    data-testid="score-cell"
  >
    <span
      v-if="flag"
      class="px-1 py-0.5 text-center text-[11px] font-semibold leading-tight"
      :class="status === 'ON' ? 'bg-live' : 'text-kpi-gold-300'"
      :data-status="status"
      data-testid="game-flag"
    >
      <span class="sr-only">{{ [flag.status, flag.period].filter(Boolean).join(' · ') }}</span>
      <span aria-hidden="true">
        <span v-if="status === 'ON'" class="mr-1 inline-block size-1.5 animate-pulse rounded-full bg-white align-middle motion-reduce:animate-none" />
        <span :class="stacked ? 'hidden' : 'hidden md:inline'">{{ flag.long }}</span>
        <span :class="stacked ? '' : 'md:hidden'">{{ flag.short }}</span>
      </span>
    </span>
    <component
      :is="href ? 'a' : 'span'"
      :href="href ?? undefined"
      v-bind="href ? NEW_TAB_ATTRS : {}"
      class="group/score flex flex-1 items-center justify-center font-display tabular-nums leading-none"
      :class="[stacked ? 'flex-col py-1.5 text-[26px]' : 'flex-col py-1.5 text-[26px] md:flex-row md:gap-2.5 md:py-0 md:text-3xl', { 'opacity-90': !played }]"
      :data-testid="href ? 'game-sheet' : undefined"
    >
      <span class="sr-only">{{ a11y }}<template v-if="href">, {{ $t('results.gameSheet') }} {{ $t('a11y.newTab') }}</template></span>
      <template v-if="played">
        <span class="min-w-[1ch] text-center leading-snug group-hover/score:underline" :class="dim('A')" data-side="A" aria-hidden="true">{{ game.g_score_a }}</span>
        <span class="text-xl opacity-40" :class="stacked ? 'hidden' : 'hidden md:inline'" aria-hidden="true">–</span>
        <span class="min-w-[1ch] text-center leading-snug group-hover/score:underline" :class="dim('B')" data-side="B" aria-hidden="true">{{ game.g_score_b }}</span>
      </template>
      <template v-else>
        <span class="text-lg leading-snug opacity-45" aria-hidden="true">–</span>
        <span class="text-lg leading-snug opacity-45" :class="stacked ? '' : 'md:ml-3'" aria-hidden="true">–</span>
      </template>
    </component>
    <span v-if="coefficients" class="pb-0.5 text-center text-[11px]">{{ coefficients }}</span>
  </span>
</template>
