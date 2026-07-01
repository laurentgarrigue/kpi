<script setup lang="ts">
import type { MatchStatus } from '~/types/scoring'

/**
 * Cyclic match-status badge (spec §7.1): a single badge showing the current status,
 * click/Enter advances to the next (ATT → ON → END → ATT). Calqué sur le badge statut
 * de competitions/index.vue (cycleStatus + getStatusColor).
 *
 * Props down / events up: emits `change` with the next status; the page mutates the store.
 */
const props = defineProps<{
  status: MatchStatus
  /** When false, the badge is read-only (no cycling). */
  canCycle?: boolean
}>()

const emit = defineEmits<{ change: [next: MatchStatus] }>()

const { t } = useI18n()

// ATT -> ON -> END -> ATT
const NEXT: Record<MatchStatus, MatchStatus> = { ATT: 'ON', ON: 'END', END: 'ATT' }

// Colors matching the competition status badge (legacy GestionStyle.css equivalents).
const colorClass = computed(() => {
  switch (props.status) {
    case 'ATT': return 'bg-header-600 text-header-50'
    case 'ON': return 'bg-success-500 text-success-50'
    case 'END': return 'bg-primary-800 text-primary-50'
    default: return 'bg-header-600 text-header-50'
  }
})

const cycle = () => {
  if (!props.canCycle) return
  emit('change', NEXT[props.status])
}
</script>

<template>
  <span
    class="px-2 py-1 text-xs font-medium rounded uppercase select-none"
    :class="[colorClass, canCycle ? 'cursor-pointer' : '']"
    :title="canCycle ? t('scoring.status_cycle_hint') : ''"
    :role="canCycle ? 'button' : undefined"
    :tabindex="canCycle ? 0 : undefined"
    @click="cycle"
    @keydown.enter.prevent="cycle"
    @keydown.space.prevent="cycle"
  >
    {{ t('scoring.status.' + status) }}
  </span>
</template>
