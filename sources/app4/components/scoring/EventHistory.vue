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
 * During play only the most recent facts are shown (`recentCount`), the rest one click
 * away: the full timeline pushed the entry zone off a tablet screen (spec §7.1 — history
 * on demand). Post-match, where the timeline IS the work, everything is shown.
 *
 * Props down / events up: emits `edit`/`remove`; the page mutates the store.
 */
const props = defineProps<{
  events: ScoringEvent[]
  editingUid?: string | null
  canScore?: boolean
  /** Show only the N most recent facts until "Tout voir"; 0 = always everything. */
  recentCount?: number
}>()

const emit = defineEmits<{ edit: [e: ScoringEvent]; remove: [e: ScoringEvent] }>()

const { t } = useI18n()

// Chronological rank of a period: M1 < M2 < P1 < P2 < … (unbounded, spec §0.6) < TB.
const SHOOTOUT_RANK = Number.MAX_SAFE_INTEGER
const periodRank = (p: string): number => {
  if (p === 'TB') return SHOOTOUT_RANK
  const overtime = overtimeIndex(p)
  return overtime !== null ? 2 + overtime : p === 'M2' ? 2 : 1
}

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

const showAll = ref(false)
const limited = computed(() => !!props.recentCount && !showAll.value && props.events.length > props.recentCount)

// The N latest facts in game order (period, then time) — what "recent" means at the table.
const visible = computed(() => {
  if (!limited.value) return props.events
  const chronological = [...props.events].sort((a, b) =>
    periodRank(a.period) - periodRank(b.period) || timeToSeconds(a.tpsJeu) - timeToSeconds(b.tpsJeu))
  return chronological.slice(-props.recentCount!)
})

// period DESC, time ASC (as in ReportController / fm3 query)
const sorted = computed(() =>
  [...visible.value].sort((a, b) =>
    periodRank(b.period) - periodRank(a.period) || timeToSeconds(a.tpsJeu) - timeToSeconds(b.tpsJeu))
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
    <div class="flex items-center gap-2 mb-2">
      <h3 class="font-semibold flex-1">{{ t('scoring.history') }}</h3>
      <UButton
        v-if="recentCount && events.length > recentCount"
        size="xs"
        variant="ghost"
        :icon="showAll ? 'i-heroicons-chevron-up' : 'i-heroicons-chevron-down'"
        @click="showAll = !showAll"
      >{{ showAll ? t('scoring.history_recent', { n: recentCount }) : t('scoring.history_all', { count: events.length }) }}</UButton>
    </div>

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
