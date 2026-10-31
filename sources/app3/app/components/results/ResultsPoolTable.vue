<script setup lang="ts">
import { poolStandings } from '#kpi-layer/utils/results/charts'
import { slotFromLabel } from '#kpi-layer/utils/results/games'
import type { ChartPhase } from '#kpi-layer/utils/results/types'
import { drawSlotMessage } from '~/utils/games'

// Standings of a pool phase (CMP-09, CMP-24): rank, team (logo), points, played, signed goal difference. Teams are
// buttons following their path when `followed` is given. `spread`: a full-width table whose figures take proportional,
// centred columns instead of being packed on the right (CMP-26).
const props = defineProps<{ phase: ChartPhase, competition: string, followed?: number | null, spread?: boolean }>()
const emit = defineEmits<{ follow: [number] }>()
const { t } = useI18n()
const language = useLanguageTag()
const rows = computed(() => poolStandings(props.phase))
const played = computed(() => (props.phase.teams ?? []).some(team => (team.t_pld ?? 0) > 0))
const figure = computed(() => (props.spread ? 'w-[12%] text-center' : 'pl-1.5 text-right'))
const signed = (value: number | null) => (value && value > 0 ? `+${value}` : String(value ?? 0))
function waiting(label: string | null): string {
  const message = drawSlotMessage(label ? slotFromLabel(label) : null, language.value)
  return t(message.key, message.params)
}
</script>

<template>
  <table class="w-full tabular-nums" :class="spread ? 'text-base' : 'text-sm'" :data-spread="spread ? '' : undefined" data-testid="pool-table">
    <caption class="sr-only">{{ phase.libelle }}</caption>
    <thead class="text-[11px] font-semibold text-muted">
      <tr>
        <th scope="col" class="pb-1 text-left"><span class="sr-only">{{ $t('results.col.rank') }}</span></th>
        <th scope="col" class="pb-1 text-left">{{ $t('results.col.team') }}</th>
        <th scope="col" class="pb-1" :class="figure">{{ $t('results.col.points') }}</th>
        <th scope="col" class="pb-1" :class="figure">{{ $t('results.col.played') }}</th>
        <th scope="col" class="whitespace-nowrap pb-1" :class="figure">+/−</th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="(row, index) in rows"
        :key="index"
        class="border-t border-line"
        :class="{ 'bg-sky/20': !('empty' in row) && followed !== undefined && followed !== null && row.t_number === followed }"
        :data-followed="!('empty' in row) && followed !== null && followed !== undefined && row.t_number === followed ? '' : undefined"
      >
        <template v-if="'empty' in row">
          <td class="w-6 py-1.5 pr-1 text-muted">{{ index + 1 }}</td>
          <td class="py-1.5 italic text-muted" colspan="4">—</td>
        </template>
        <template v-else>
          <td class="w-6 py-1.5 pr-1 text-muted">{{ played ? (row.t_clt || index + 1) : index + 1 }}</td>
          <td class="max-w-0 py-1.5" :class="{ 'w-full': !spread }">
            <span v-if="row.placeholder || row.t_number === null" class="block truncate italic text-muted">{{ waiting(row.t_label) }}</span>
            <span v-else class="flex min-w-0 items-center gap-2">
              <ResultsTeamLogo :path="row.t_logo" size="sm" />
              <button
                v-if="followed !== undefined"
                type="button"
                class="truncate text-left hover:underline"
                :class="{ 'font-bold text-kpi-blue-700': row.t_number === followed }"
                :aria-pressed="row.t_number === followed"
                :data-team="row.t_number"
                @click="emit('follow', row.t_number!)"
              >
                {{ row.t_label }}
              </button>
              <ResultsTeamName v-else class="truncate" :label="row.t_label" :number="row.t_number" :competition="competition" />
            </span>
          </td>
          <td class="py-1.5 font-bold" :class="figure">{{ played ? (row.t_pts ?? 0) / 100 : '–' }}</td>
          <td class="py-1.5" :class="figure">{{ played ? (row.t_pld ?? 0) : '–' }}</td>
          <td class="py-1.5" :class="figure">{{ played ? signed(row.t_diff) : '–' }}</td>
        </template>
      </tr>
    </tbody>
  </table>
</template>
