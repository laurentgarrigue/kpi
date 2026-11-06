<script setup lang="ts">
import type { MatchClosure, TeamSide } from '~/types/scoring'

/**
 * Match closure (spec §7.6) — the only way to set the status to "Terminé", because closing
 * consolidates the live state into kp_* (results, PDF, rankings). In one dialog:
 *   1. the score check: displayed score vs recorded goals, with a one-click alignment;
 *   2. the end time, pre-filled with the current time (decision §0.9), editable;
 *   3. the officials' comments (captains' reservations, referee report);
 *   4. the lock, for the profiles allowed to validate.
 *
 * Props down / events up: the page records the closure.
 */
const props = defineProps<{
  open: boolean
  teamA: string
  teamB: string
  score: Record<TeamSide, number>
  goals: Record<TeamSide, number>
  comments: string
  canLock?: boolean
  loading?: boolean
}>()

const emit = defineEmits<{
  close: []
  confirm: [closure: MatchClosure]
  align: []
}>()

const { t } = useI18n()

const endTime = ref('')
const commentsDraft = ref('')
const lock = ref(false)

const nowHHMM = (): string => {
  const d = new Date()
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

// Re-prime the form each time the dialog opens: the end time is "now", not when the page loaded.
watch(() => props.open, (open) => {
  if (!open) return
  endTime.value = nowHHMM()
  commentsDraft.value = props.comments
  lock.value = false
})

const mismatch = computed(() => props.score.A !== props.goals.A || props.score.B !== props.goals.B)
const endTimeValid = computed(() => /^([01]\d|2[0-3]):[0-5]\d$/.test(endTime.value))

const confirm = () => {
  if (!endTimeValid.value || props.loading) return
  emit('confirm', { endTime: endTime.value, comments: commentsDraft.value.trim(), lock: lock.value && !!props.canLock })
}
</script>

<template>
  <AdminConfirmModal
    :open="open"
    variant="info"
    :title="t('scoring.close.title')"
    :confirm-text="t('scoring.close.confirm')"
    :cancel-text="t('scoring.edit.cancel')"
    :loading="loading"
    :disabled-confirm="!endTimeValid"
    @confirm="confirm"
    @close="emit('close')"
  >
    <div class="space-y-4 text-sm">
      <!-- 1. Score check -->
      <table class="w-full">
        <thead>
          <tr class="text-xs text-header-600 dark:text-header-300">
            <th />
            <th class="font-normal text-center w-20">{{ t('scoring.close.score') }}</th>
            <th class="font-normal text-center w-20">{{ t('scoring.close.goals') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="side in (['A', 'B'] as TeamSide[])" :key="side">
            <td class="py-1 truncate">{{ side === 'A' ? teamA : teamB }}</td>
            <td class="py-1 text-center scoring-digits text-lg">{{ score[side] }}</td>
            <td
              class="py-1 text-center scoring-digits text-lg"
              :class="score[side] !== goals[side] ? 'text-amber-600 dark:text-amber-400' : ''"
            >{{ goals[side] }}</td>
          </tr>
        </tbody>
      </table>
      <div v-if="mismatch" class="flex flex-wrap items-center gap-2 rounded-md bg-amber-50 dark:bg-amber-950/40 p-2 text-amber-800 dark:text-amber-200">
        <UIcon name="i-heroicons-exclamation-triangle" class="size-4 shrink-0" />
        <span class="flex-1">{{ t('scoring.close.mismatch') }}</span>
        <UButton size="xs" color="warning" variant="soft" @click="emit('align')">{{ t('scoring.close.align') }}</UButton>
      </div>

      <!-- 2. End time -->
      <UFormField :label="t('scoring.close.end_time')" name="endTime">
        <UInput v-model="endTime" type="time" class="w-32" />
      </UFormField>

      <!-- 3. Officials' comments -->
      <UFormField :label="t('scoring.close.comments')" name="comments">
        <UTextarea v-model="commentsDraft" :rows="3" class="w-full" :placeholder="t('scoring.close.comments_placeholder')" />
      </UFormField>

      <!-- 4. Lock -->
      <UCheckbox
        v-if="canLock"
        v-model="lock"
        :label="t('scoring.close.lock')"
        :description="t('scoring.close.lock_hint')"
      />
    </div>
  </AdminConfirmModal>
</template>
