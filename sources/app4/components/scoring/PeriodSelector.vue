<script setup lang="ts">
import type { Period, MatchType } from '~/types/scoring'

/**
 * Period selector (spec §7.1 / §0.6): a single "next period" action that advances by
 * match type, plus a direct-access dropdown (escape hatch for post-match correction).
 *
 * - type C (classement): M1 → M2 → (end, draw allowed)
 * - type E (elimination): M1 → M2 → P1 → P2 → … — overtimes are UNBOUNDED (golden goal:
 *   the advance stays available while the score is level); TB only when the competition
 *   enables the shootout (spec §7.5).
 *
 * Changing period resets the clock to the new period duration → confirmation modal.
 * Props down / events up: emits `change` with the chosen period; the page mutates the store.
 */
const props = defineProps<{
  period: Period | null
  type: MatchType
  /** When false, the selector is read-only. */
  canChange?: boolean
  /** Score currently level — conditions the E-type advance into (further) overtime. */
  scoreLevel?: boolean
  /** Shootout allowed by the competition (spec §7.5 — tournament option). */
  shootoutEnabled?: boolean
}>()

const emit = defineEmits<{ change: [period: Period] }>()

const { t } = useI18n()

/** Human label of a period — P{n} share a single parameterized i18n key (unbounded). */
const periodLabel = (p: Period): string => {
  const n = overtimeIndex(p)
  return n !== null ? t('scoring.period.overtime', { n }) : t('scoring.period.' + p)
}

const currentLabel = computed(() => (props.period ? periodLabel(props.period) : '—'))

// The next period, from the shared pure rules (utils/scoringRules — mirror of api2).
const nextP = computed<Period | null>(() => {
  if (!props.period) return 'M1'
  return nextPeriodFor(props.type, props.period, props.scoreLevel ?? true, props.shootoutEnabled ?? false)
})

// Direct-access menu: M1/M2, every overtime up to the current one +1 (type E), TB when
// enabled — and always the current period, so odd legacy values stay selectable.
const directItems = computed(() => {
  const list: Period[] = ['M1', 'M2']
  if (props.type === 'E') {
    const maxN = Math.max(overtimeIndex(props.period ?? 'M1') ?? 0, 0) + 1
    for (let n = 1; n <= maxN; n++) list.push(`P${n}`)
  }
  if (props.shootoutEnabled || props.period === 'TB') list.push('TB')
  if (props.period && !list.includes(props.period)) list.push(props.period)
  return list.map(p => ({ label: periodLabel(p), value: p }))
})

// Confirmation modal state — holds the period we are about to switch to.
const pending = ref<Period | null>(null)
const confirmOpen = computed(() => pending.value !== null)

const requestChange = (p: Period | null) => {
  if (!props.canChange || !p || p === props.period) return
  pending.value = p
}

const confirm = () => {
  if (pending.value) emit('change', pending.value)
  pending.value = null
}
const cancel = () => { pending.value = null }
</script>

<template>
  <div class="flex items-center gap-2">
    <!-- Current period badge -->
    <UBadge color="neutral" variant="soft" class="uppercase">{{ currentLabel }}</UBadge>

    <!-- Advance to next period (unbounded overtimes for type E while level) -->
    <UButton
      v-if="nextP"
      size="xs"
      icon="i-heroicons-forward"
      :disabled="!canChange"
      @click="requestChange(nextP)"
    >
      {{ t('scoring.period_next') }} — {{ periodLabel(nextP) }}
    </UButton>

    <!-- Direct access (escape hatch) — pick any period of the sequence -->
    <USelect
      :model-value="period ?? undefined"
      :items="directItems"
      value-key="value"
      :disabled="!canChange"
      size="xs"
      :placeholder="t('scoring.period_direct')"
      class="w-40"
      @update:model-value="requestChange($event as Period)"
    />

    <!-- Confirmation: changing period resets the clock -->
    <AdminConfirmModal
      :open="confirmOpen"
      variant="warning"
      :title="t('scoring.period_confirm_title')"
      :message="t('scoring.period_confirm_message', { period: pending ? periodLabel(pending) : '' })"
      :confirm-text="t('scoring.edit.save')"
      :cancel-text="t('scoring.edit.cancel')"
      @confirm="confirm"
      @close="cancel"
    />
  </div>
</template>
