<script setup lang="ts">
import type { ScoringEvent } from '~/types/scoring'

/**
 * Event history (spec §7.1). Two presentations of the same data:
 *
 *  - **Symmetric table** (md+ : PC / large tablet) — reproduces the control PDF / FMV3 layout:
 *      Team A events on the LEFT, Team B on the RIGHT, period + time in the CENTER.
 *      Per legacy fm3_C.js: A row = [V][J][R][name][goal] | time | (blank);
 *      B row = (blank) | time | [goal][name][V][J][R]. Sorted period ↓ then time ↑.
 *  - **Vertical list** (mobile) — compact single column.
 *
 * Props down / events up: emits `edit`/`remove`; the page mutates the store.
 */
const props = defineProps<{
  events: ScoringEvent[]
  editingUid?: string | null
  canScore?: boolean
}>()

const emit = defineEmits<{ edit: [e: ScoringEvent]; remove: [e: ScoringEvent] }>()

const { t } = useI18n()

// Period order for sorting (most recent period first), matching the legacy ORDER BY.
const PERIOD_ORDER: Record<string, number> = { TB: 5, P2: 4, P1: 3, M2: 2, M1: 1 }

/** Display a game time as MM:SS (drop a leading 00: hours segment if present). */
const fmtTime = (t: string): string => {
  const parts = (t || '').split(':')
  return parts.length >= 3 ? parts.slice(1).join(':') : t
}

const timeToSeconds = (t: string): number => {
  const parts = fmtTime(t || '0:0').split(':').map(Number)
  const [m, s] = parts
  return (m || 0) * 60 + (s || 0)
}

// period DESC, time ASC (as in ReportController / fm3 query)
const sorted = computed(() =>
  [...props.events].sort((a, b) => {
    const pd = (PERIOD_ORDER[b.period] ?? 0) - (PERIOD_ORDER[a.period] ?? 0)
    if (pd !== 0) return pd
    return timeToSeconds(a.tpsJeu) - timeToSeconds(b.tpsJeu)
  })
)

const playerLabel = (e: ScoringEvent): string => {
  if (e.player === '0' || !e.nom) return t('scoring.team') + ' ' + e.team
  return `${e.nom.toUpperCase()} ${e.prenom ?? ''}`.trim()
}

const onRowClick = (e: ScoringEvent) => {
  if (props.canScore) emit('edit', e)
}
</script>

<template>
  <div>
    <h3 class="font-semibold mb-2">{{ t('scoring.history') }}</h3>

    <!-- Symmetric table (PC / large tablet) -->
    <table class="hidden md:table w-full text-sm border-collapse">
      <thead>
        <tr class="text-xs text-header-600 dark:text-header-300 border-b border-header-200 dark:border-header-700">
          <!-- Columns mirror the score banner (A left, B right): no team names repeated -->
          <th class="py-1 w-[42%]" />
          <th class="py-1 text-center w-[16%]">{{ t('scoring.time.short') }}</th>
          <th class="py-1 w-[42%]" />
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(e, i) in sorted" :key="e.uid ?? i"
          class="border-b border-header-100 dark:border-header-800 align-middle"
          :class="[
            editingUid === e.uid
              ? 'bg-primary-100 dark:bg-primary-900/40 ring-2 ring-inset ring-primary-500'
              : 'hover:bg-header-50 dark:hover:bg-header-800',
            canScore ? 'cursor-pointer' : ''
          ]"
          @click="onRowClick(e)"
        >
          <!-- LEFT: team A -->
          <td class="py-1 pr-2">
            <div v-if="e.team === 'A'" class="group flex items-center gap-2 justify-start">
              <UButton
                size="2xs" variant="ghost" color="error" icon="i-heroicons-trash"
                class="opacity-0 group-hover:opacity-100"
                :disabled="!canScore" @click.stop="emit('remove', e)"
              />
              <ScoringEventIcon :code="e.code" />
              <span class="font-mono w-7 text-center">#{{ e.number }}</span>
              <span class="truncate">{{ playerLabel(e) }}</span>
              <span v-if="e.reason" class="text-header-600 dark:text-header-300 text-xs">({{ t('scoring.reason.' + e.reason) }})</span>
            </div>
          </td>

          <!-- CENTER: period + time -->
          <td class="py-1 text-center whitespace-nowrap scoring-digits text-header-900 dark:text-header-50">
            {{ e.period }} {{ fmtTime(e.tpsJeu) }}
          </td>

          <!-- RIGHT: team B (mirrored) -->
          <td class="py-1 pl-2">
            <div v-if="e.team === 'B'" class="group flex items-center gap-2 justify-end">
              <span v-if="e.reason" class="text-header-600 dark:text-header-300 text-xs">({{ t('scoring.reason.' + e.reason) }})</span>
              <span class="truncate">{{ playerLabel(e) }}</span>
              <span class="font-mono w-7 text-center">#{{ e.number }}</span>
              <ScoringEventIcon :code="e.code" />
              <UButton
                size="2xs" variant="ghost" color="error" icon="i-heroicons-trash"
                class="opacity-0 group-hover:opacity-100"
                :disabled="!canScore" @click.stop="emit('remove', e)"
              />
            </div>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Vertical list (mobile) -->
    <ul class="md:hidden text-sm divide-y divide-header-100 dark:divide-header-800">
      <li
        v-for="(e, i) in sorted" :key="e.uid ?? i"
        class="flex items-center gap-2 py-1"
        :class="editingUid === e.uid ? 'bg-primary-100 dark:bg-primary-900/40 ring-2 ring-inset ring-primary-500' : ''"
      >
        <!-- Team letter pushed to its side (A far left, B far right, as on the board); the
             empty slot on the other side keeps every row aligned. -->
        <span class="w-6 shrink-0">
          <UBadge v-if="e.team === 'A'" size="xs" color="primary">A</UBadge>
        </span>
        <ScoringEventIcon :code="e.code" />
        <span class="scoring-digits w-20">{{ e.period }} {{ fmtTime(e.tpsJeu) }}</span>
        <span class="scoring-digits w-8 text-center">#{{ e.number }}</span>
        <span class="flex-1 truncate">
          {{ playerLabel(e) }}
          <span v-if="e.reason" class="text-header-600 dark:text-header-300">({{ t('scoring.reason.' + e.reason) }})</span>
        </span>
        <UButton
          size="xs" variant="ghost" icon="i-heroicons-pencil-square"
          :disabled="!canScore" @click="emit('edit', e)"
        />
        <UButton
          size="xs" variant="ghost" color="error" icon="i-heroicons-trash"
          :disabled="!canScore" @click="emit('remove', e)"
        />
        <span class="w-6 shrink-0 text-right">
          <UBadge v-if="e.team === 'B'" size="xs" color="neutral">B</UBadge>
        </span>
      </li>
    </ul>

    <!-- Row actions for the symmetric table (delete; edit is the row click) -->
    <p v-if="sorted.length" class="hidden md:block text-xs text-header-600 dark:text-header-300 mt-2">
      {{ t('scoring.history_table_hint') }}
    </p>
  </div>
</template>
