<script setup lang="ts">
import type { MatchStatus } from '~/types/scoring'

/**
 * Match-status badge (spec §7.1). It shows the current status and gives the kick-off
 * (ATT → ON) in one click — the only status change cheap enough for a single tap.
 *
 * Closing (ON → END) and reopening (END → ON) are deliberately NOT here: closing
 * consolidates the result into kp_* and asks for the end time and comments (spec §7.6),
 * reopening undoes a closure. Both go through their own confirmed dialog, owned by the
 * page. (The former ATT → ON → END → ATT cycle closed a match on a stray tap.)
 *
 * Props down / events up: emits `kickoff`; the page mutates the store.
 */
const props = defineProps<{
  status: MatchStatus
  /** When false, the badge is read-only. */
  canKickoff?: boolean
}>()

const emit = defineEmits<{ kickoff: [] }>()

const { t } = useI18n()

// Colors matching the competition status badge (legacy GestionStyle.css equivalents).
const COLOR_CLASS: Record<MatchStatus, string> = {
  ATT: 'bg-header-600 text-header-50',
  ON: 'bg-success-500 text-success-50',
  END: 'bg-primary-800 text-primary-50'
}

const clickable = computed(() => props.canKickoff && props.status === 'ATT')

const kickoff = () => {
  if (clickable.value) emit('kickoff')
}
</script>

<template>
  <span
    class="px-2.5 py-1 text-sm font-medium rounded-md select-none"
    :class="[COLOR_CLASS[status], clickable ? 'cursor-pointer ring-1 ring-white/40' : '']"
    :title="clickable ? t('scoring.status_kickoff_hint') : ''"
    :role="clickable ? 'button' : undefined"
    :tabindex="clickable ? 0 : undefined"
    @click="kickoff"
    @keydown.enter.prevent="kickoff"
    @keydown.space.prevent="kickoff"
  >
    {{ t('scoring.status.' + status) }}
  </span>
</template>
