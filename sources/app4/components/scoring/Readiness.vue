<script setup lang="ts">
import { ROSTER_LIMITS, type ReadinessIssue } from '~/utils/scoringReadiness'

/**
 * Pre-kick-off checks of the game sheet (spec §7.2), from utils/scoringReadiness.
 * Two presentations:
 *  - full (Paramètres view): every point to check, or a "ready" line;
 *  - compact (Déroulement view, before kick-off): one line and a way to the sheet.
 * Warnings only: nothing here blocks the kick-off.
 */
const props = defineProps<{
  issues: ReadinessIssue[]
  teamA: string
  teamB: string
  compact?: boolean
}>()

const emit = defineEmits<{ open: [] }>()

const { t } = useI18n()

const message = (issue: ReadinessIssue): string => {
  const team = issue.team === 'A' ? props.teamA : issue.team === 'B' ? props.teamB : ''
  return t('scoring.readiness.' + issue.code, {
    team,
    count: issue.count ?? 0,
    min: ROSTER_LIMITS.minPlayers,
    max: issue.code === 'too_many_staff' ? ROSTER_LIMITS.maxStaff : ROSTER_LIMITS.maxPlayers,
    numbers: (issue.numbers ?? []).join(', '),
    roles: (issue.roles ?? []).map(r => t('scoring.officials.' + r)).join(', ')
  })
}
</script>

<template>
  <div
    v-if="compact && issues.length"
    class="flex flex-wrap items-center gap-2 rounded-lg border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 px-3 py-2 text-sm text-amber-900 dark:text-amber-100"
  >
    <UIcon name="i-heroicons-clipboard-document-list" class="size-5 shrink-0" />
    <span class="flex-1">{{ t('scoring.readiness.summary', { count: issues.length }) }}</span>
    <UButton size="xs" color="warning" variant="soft" @click="emit('open')">{{ t('scoring.readiness.open') }}</UButton>
  </div>

  <section
    v-else-if="!compact"
    class="rounded-xl border p-4 space-y-2"
    :class="issues.length
      ? 'border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40'
      : 'border-success-300 dark:border-success-700 bg-success-50 dark:bg-success-950/40'"
  >
    <h2 class="flex items-center gap-2 font-semibold" :class="issues.length ? 'text-amber-900 dark:text-amber-100' : 'text-success-800 dark:text-success-200'">
      <UIcon :name="issues.length ? 'i-heroicons-exclamation-triangle' : 'i-heroicons-check-circle'" class="size-5" />
      {{ issues.length ? t('scoring.readiness.title') : t('scoring.readiness.ok') }}
    </h2>
    <ul v-if="issues.length" class="list-disc pl-6 text-sm text-amber-900 dark:text-amber-100 space-y-0.5">
      <li v-for="(issue, i) in issues" :key="i">{{ message(issue) }}</li>
    </ul>
    <p v-if="issues.length" class="text-xs text-amber-800 dark:text-amber-200">{{ t('scoring.readiness.hint') }}</p>
  </section>
</template>
