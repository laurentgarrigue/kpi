<script setup lang="ts">
import type { ScoringEvent, ScoringEventCode } from '~/types/scoring'

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
  teamAName?: string | null
  teamBName?: string | null
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

const codeLabel = (code: ScoringEventCode): string =>
  t('scoring.event.' + ({ B: 'goal', V: 'card_green', J: 'card_yellow', R: 'card_red', D: 'card_red_def' }[code]))

// Visual token for an event (emoji avoids shipping the legacy PNGs).
const tokenFor = (code: ScoringEventCode): string =>
  ({ B: '🥅', V: '🟢', J: '🟡', R: '🔴', D: '🟥' }[code])

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
    <p class="text-xs text-header-500 mb-2">{{ t('scoring.edit.edit_hint') }}</p>

    <!-- Symmetric table (PC / large tablet) -->
    <table class="hidden md:table w-full text-sm border-collapse">
      <thead>
        <tr class="text-xs text-header-500 border-b border-header-200">
          <th class="py-1 text-left w-[42%]">{{ teamAName }}</th>
          <th class="py-1 text-center w-[16%]">{{ t('scoring.time.label') }}</th>
          <th class="py-1 text-right w-[42%]">{{ teamBName }}</th>
        </tr>
      </thead>
      <tbody>
        <tr
          v-for="(e, i) in sorted" :key="e.uid ?? i"
          class="border-b border-header-100 align-middle"
          :class="[
            editingUid === e.uid ? 'bg-primary-50' : 'hover:bg-header-50',
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
              <span class="text-base leading-none">{{ tokenFor(e.code) }}</span>
              <span class="font-mono w-7 text-center">#{{ e.number }}</span>
              <span class="truncate">{{ playerLabel(e) }}</span>
              <span v-if="e.reason" class="text-header-400 text-xs">({{ t('scoring.reason.' + e.reason) }})</span>
            </div>
          </td>

          <!-- CENTER: period + time -->
          <td class="py-1 text-center whitespace-nowrap font-mono text-header-600">
            {{ e.period }} {{ fmtTime(e.tpsJeu) }}
          </td>

          <!-- RIGHT: team B (mirrored) -->
          <td class="py-1 pl-2">
            <div v-if="e.team === 'B'" class="group flex items-center gap-2 justify-end">
              <span v-if="e.reason" class="text-header-400 text-xs">({{ t('scoring.reason.' + e.reason) }})</span>
              <span class="truncate">{{ playerLabel(e) }}</span>
              <span class="font-mono w-7 text-center">#{{ e.number }}</span>
              <span class="text-base leading-none">{{ tokenFor(e.code) }}</span>
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
    <ul class="md:hidden text-sm divide-y divide-header-100">
      <li
        v-for="(e, i) in sorted" :key="e.uid ?? i"
        class="flex items-center gap-2 py-1"
        :class="editingUid === e.uid ? 'bg-primary-50' : ''"
      >
        <span class="text-base leading-none">{{ tokenFor(e.code) }}</span>
        <span class="font-mono w-20">{{ e.period }} {{ fmtTime(e.tpsJeu) }}</span>
        <UBadge size="xs" :color="e.team === 'A' ? 'primary' : 'neutral'">{{ e.team }}</UBadge>
        <span class="font-mono w-8 text-center">#{{ e.number }}</span>
        <span class="flex-1 truncate">
          {{ playerLabel(e) }}
          <span class="text-header-500">· {{ codeLabel(e.code) }}</span>
          <span v-if="e.reason" class="text-header-400">({{ t('scoring.reason.' + e.reason) }})</span>
        </span>
        <UButton
          size="xs" variant="ghost" icon="i-heroicons-pencil-square"
          :disabled="!canScore" @click="emit('edit', e)"
        />
        <UButton
          size="xs" variant="ghost" color="error" icon="i-heroicons-trash"
          :disabled="!canScore" @click="emit('remove', e)"
        />
      </li>
    </ul>

    <!-- Row actions for the symmetric table (delete; edit is the row click) -->
    <p v-if="sorted.length" class="hidden md:block text-xs text-header-400 mt-2">
      {{ t('scoring.history_table_hint') }}
    </p>
  </div>
</template>
