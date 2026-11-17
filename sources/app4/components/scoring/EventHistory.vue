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
 * Order: most recent first by default; the arrow next to « Temps » shows which way time
 * flows (↑ = the latest fact at the top) and flips it — a per-device preference.
 *
 * Props down / events up: emits `edit`/`remove`; the page mutates the store.
 */
const props = defineProps<{
  events: ScoringEvent[]
  editingUid?: string | null
  canScore?: boolean
  /** Show only the N most recent facts until "Tout voir"; 0 = always everything. */
  recentCount?: number
  /** Team names, recalled over their column on wide screens (A left, B right). */
  teamA?: string
  teamB?: string
}>()

const emit = defineEmits<{ edit: [e: ScoringEvent]; remove: [e: ScoringEvent] }>()

const { t } = useI18n()

const fmtTime = (t: string): string => {
  const parts = (t || '').split(':')
  return parts.length >= 3 ? parts.slice(1).join(':') : t
}

// Display direction, remembered on this device (storage may be unavailable: private mode…).
const NEWEST_FIRST_KEY = 'kpi.scoring.timelineNewestFirst'
const newestFirst = ref(true)
onMounted(() => {
  try { newestFirst.value = localStorage.getItem(NEWEST_FIRST_KEY) !== '0' } catch { /* default */ }
})
const toggleDirection = () => {
  newestFirst.value = !newestFirst.value
  try { localStorage.setItem(NEWEST_FIRST_KEY, newestFirst.value ? '1' : '0') } catch { /* not kept */ }
}
const directionIcon = computed(() => (newestFirst.value ? 'i-heroicons-arrow-up' : 'i-heroicons-arrow-down'))
const directionTitle = computed(() => t(newestFirst.value ? 'scoring.history_newest_first' : 'scoring.history_oldest_first'))

const showAll = ref(false)
const limited = computed(() => !!props.recentCount && !showAll.value && props.events.length > props.recentCount)
// utils/scoringTimeline: most recent first by default (the fact time is the time LEFT, the
// clock counts down — legacy order: period ↓, then time ↑); the latest N stay the latest.
const sorted = computed(() => timelineView(props.events, {
  newestFirst: newestFirst.value,
  limit: limited.value ? props.recentCount : 0
}))

// Extract ↔ everything: one row on the side of the older facts — under the list when the
// newest are on top, above it otherwise. « … tout voir (N de plus) » makes the extract read
// as one and invites the click; once expanded, « Les N derniers seulement » sits at the same
// place. The chevron points to where the rows appear (expand) or go (collapse).
const canToggleAll = computed(() => !!props.recentCount && props.events.length > props.recentCount)
const toggleBefore = computed(() => canToggleAll.value && !newestFirst.value)
const toggleAfter = computed(() => canToggleAll.value && newestFirst.value)
const toggleIcon = computed(() => (showAll.value === newestFirst.value ? 'i-heroicons-chevron-up' : 'i-heroicons-chevron-down'))
const toggleLabel = computed(() => (showAll.value
  ? t('scoring.history_recent', { n: props.recentCount })
  : t('scoring.history_more', { count: props.events.length - (props.recentCount ?? 0) })))
const TOGGLE_CLASS = 'w-full inline-flex items-center justify-center gap-1 rounded px-2 py-1 text-header-600 dark:text-header-300 hover:bg-header-50 dark:hover:bg-header-800 '
  + 'hover:text-primary-700 dark:hover:text-primary-300 focus-visible:outline-2 focus-visible:outline-primary-500'

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
      <!-- Mobile has no column header: the direction toggle sits in the title bar -->
      <UButton
        class="md:hidden"
        size="xs" variant="ghost" color="neutral"
        :icon="directionIcon"
        :aria-label="directionTitle" :title="directionTitle"
        @click="toggleDirection"
      >{{ t('scoring.time.short') }}</UButton>
    </div>

    <!-- Symmetric table (PC / large tablet) -->
    <table class="hidden md:table w-full text-sm border-collapse">
      <thead>
        <tr class="text-xs text-header-600 dark:text-header-300 border-b border-header-200 dark:border-header-700">
          <!-- Columns mirror the score banner (A left, B right), team names recalled -->
          <th class="py-1 px-3 w-[42%] text-left font-semibold">{{ teamA }}</th>
          <th class="py-1 text-center w-[16%]">
            <button
              type="button"
              class="inline-flex items-center gap-1 rounded px-1 hover:bg-header-100 dark:hover:bg-header-800 focus-visible:outline-2 focus-visible:outline-primary-500"
              :aria-label="directionTitle" :title="directionTitle"
              @click="toggleDirection"
            >
              {{ t('scoring.time.short') }}
              <UIcon :name="directionIcon" class="size-3.5" />
            </button>
          </th>
          <th class="py-1 px-3 w-[42%] text-right font-semibold">{{ teamB }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-if="toggleBefore">
          <td colspan="3" class="py-1 text-center">
            <button type="button" :class="TOGGLE_CLASS" @click="showAll = !showAll"><UIcon :name="toggleIcon" class="size-4" />{{ toggleLabel }}</button>
          </td>
        </tr>
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
            {{ periodDisplayCode(e.period, t('scoring.period.overtime_short')) }} {{ fmtTime(e.tpsJeu) }}
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
        <tr v-if="toggleAfter">
          <td colspan="3" class="py-1 text-center">
            <button type="button" :class="TOGGLE_CLASS" @click="showAll = !showAll"><UIcon :name="toggleIcon" class="size-4" />{{ toggleLabel }}</button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Vertical list (mobile) -->
    <ul class="md:hidden text-sm divide-y divide-header-100 dark:divide-header-800">
      <li v-if="toggleBefore" class="py-1 text-center">
        <button type="button" :class="TOGGLE_CLASS" @click="showAll = !showAll"><UIcon :name="toggleIcon" class="size-4" />{{ toggleLabel }}</button>
      </li>
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
        <span class="scoring-digits w-20">{{ periodDisplayCode(e.period, t('scoring.period.overtime_short')) }} {{ fmtTime(e.tpsJeu) }}</span>
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
      <li v-if="toggleAfter" class="py-1 text-center">
        <button type="button" :class="TOGGLE_CLASS" @click="showAll = !showAll"><UIcon :name="toggleIcon" class="size-4" />{{ toggleLabel }}</button>
      </li>
    </ul>

    <!-- Row actions for the symmetric table (delete; edit is the row click) -->
    <p v-if="sorted.length" class="hidden md:block text-xs text-header-600 dark:text-header-300 mt-2">
      {{ t('scoring.history_table_hint') }}
    </p>
  </div>
</template>
