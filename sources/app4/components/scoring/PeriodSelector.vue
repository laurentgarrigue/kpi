<script setup lang="ts">
import type { Period, MatchType } from '~/types/scoring'

/**
 * Period selector (spec §7.1): instead of showing every period as a row of buttons, expose
 * a single "next period" action that advances by match type, plus a direct-access dropdown
 * (escape hatch, useful for post-match correction).
 *
 * - type C (classement): M1 → M2 → (end)
 * - type E (elimination): M1 → M2 → P1 → P2 → TB
 *   (TB stays available as the legacy last step; unbounded "golden goal" overtime is a later
 *    lot — spec §7.5 — so the codes M1/M2/P1/P2/TB are kept for now.)
 *
 * Changing period resets the clock to the new period duration → confirmation modal.
 * Props down / events up: emits `change` with the chosen period; the page mutates the store.
 */
const props = defineProps<{
  period: Period | null
  type: MatchType
  /** When false, the selector is read-only. */
  canChange?: boolean
}>()

const emit = defineEmits<{ change: [period: Period] }>()

const { t } = useI18n()

// Ordered period progression per match type.
const SEQUENCE: Record<MatchType, Period[]> = {
  C: ['M1', 'M2'],
  E: ['M1', 'M2', 'P1', 'P2', 'TB']
}

const sequence = computed(() => SEQUENCE[props.type] ?? SEQUENCE.C)

const currentLabel = computed(() => (props.period ? t('scoring.period.' + props.period) : '—'))

// The next period in the sequence (null when already at the last one).
const nextPeriod = computed<Period | null>(() => {
  const seq = sequence.value
  const idx = props.period ? seq.indexOf(props.period) : -1
  return idx >= 0 && idx < seq.length - 1 ? seq[idx + 1] : (idx < 0 ? seq[0] : null)
})

// Direct-access menu: all periods of the sequence.
const directItems = computed(() =>
  sequence.value.map(p => ({ label: t('scoring.period.' + p), value: p }))
)

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

    <!-- Advance to next period -->
    <UButton
      v-if="nextPeriod"
      size="xs"
      icon="i-heroicons-forward"
      :disabled="!canChange"
      @click="requestChange(nextPeriod)"
    >
      {{ t('scoring.period_next') }} — {{ t('scoring.period.' + nextPeriod) }}
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
      :message="t('scoring.period_confirm_message', { period: pending ? t('scoring.period.' + pending) : '' })"
      :confirm-text="t('scoring.edit.save')"
      :cancel-text="t('scoring.edit.cancel')"
      @confirm="confirm"
      @close="cancel"
    />
  </div>
</template>
