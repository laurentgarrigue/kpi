<script setup lang="ts">
import { gameDisplayStatus } from '~/utils/gameStatus'
import { pitchDays } from '~/utils/pitchGrid'
import type { Game } from '~/types/games'

// « Pitches » view of the games page (PAGE_MATCHS.md § 2.7): one time × pitch grid per date,
// built from the games already filtered by the page. A cell is framed in red when its slot is a
// problem: several games in it, or a game of it is part of a pitch double-booking (`conflictIds`).
const props = defineProps<{
  games: Game[]
  conflictIds: Set<number>
  minInterval: number
  showReferees: boolean
  showShotclock: boolean
}>()
const emit = defineEmits<{ open: [game: Game] }>()

const { t, locale } = useI18n()
const { bracketLabels } = useBracketDisplay()

const days = computed(() => pitchDays(props.games))

const dayLabel = (date: string) => date
  ? new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  : t('games.pitch_view_no_date')
const pitchLabel = (pitch: string) => pitch ? `${t('games.field.terrain')} ${pitch}` : t('games.pitch_view_no_pitch')

const isProblemSlot = (cell: Game[] | undefined) =>
  !!cell && (cell.length > 1 || cell.some(game => props.conflictIds.has(game.id)))

const hasScore = (game: Game) => gameDisplayStatus(game.statut, game.validation) !== 'ATT' || !!(game.scoreA || game.scoreB)

interface Line { text: string, placeholder: boolean }
/** Assigned name, else the bracket placeholder (« (1A) »), else nothing. */
const line = (value: string | null, placeholder: string | null | undefined): Line | null =>
  value ? { text: value, placeholder: false } : placeholder ? { text: `(${placeholder})`, placeholder: true } : null
const teamLine = (game: Game, side: 'A' | 'B'): Line =>
  line(side === 'A' ? game.equipeA : game.equipeB, bracketLabels(game.libelle)[side === 'A' ? 'teamA' : 'teamB'])
  ?? { text: t('games.team_undefined'), placeholder: true }
const refereeLines = (game: Game) => {
  const labels = bracketLabels(game.libelle)
  return [line(game.arbitrePrincipal, labels.refereePrincipal), line(game.arbitreSecondaire, labels.refereeSecondaire)]
}
</script>

<template>
  <div class="space-y-4" data-testid="pitch-view">
    <p v-if="games.length === 0" class="p-4 text-sm text-header-600 dark:text-header-300 bg-white dark:bg-header-900 rounded-lg shadow">
      {{ t('games.no_results') }}
    </p>

    <section
      v-for="day in days"
      :key="day.date"
      class="bg-white dark:bg-header-900 rounded-lg shadow"
    >
      <h2 class="px-3 py-2 text-sm font-semibold capitalize text-header-900 dark:text-header-50 border-b border-header-200 dark:border-header-700">
        {{ dayLabel(day.date) }}
      </h2>
      <div class="overflow-x-auto">
        <table class="min-w-full table-fixed text-xs" data-testid="pitch-grid">
          <thead class="bg-header-50 dark:bg-header-800">
            <tr>
              <th scope="col" class="w-16 px-2 py-2 text-left font-medium text-header-900 dark:text-header-50">{{ t('games.field.time') }}</th>
              <th
                v-for="pitch in day.pitches"
                :key="pitch"
                scope="col"
                class="min-w-44 px-2 py-2 text-left font-medium text-header-900 dark:text-header-50"
              >{{ pitchLabel(pitch) }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="row in day.rows" :key="row.time" class="border-t border-header-200 dark:border-header-700 align-top">
              <th scope="row" class="px-2 py-2 text-left font-semibold tabular-nums text-header-900 dark:text-header-50">{{ row.time || '—' }}</th>
              <td v-for="pitch in day.pitches" :key="pitch" class="px-1 py-1">
                <div
                  v-if="row.cells[pitch]"
                  class="space-y-1 rounded"
                  :class="isProblemSlot(row.cells[pitch]) ? 'ring-2 ring-danger-500 p-0.5' : ''"
                  :data-problem="isProblemSlot(row.cells[pitch]) || undefined"
                  :title="isProblemSlot(row.cells[pitch]) ? t('games.pitch_view_problem', { minutes: minInterval }) : undefined"
                >
                  <button
                    v-for="g in row.cells[pitch]"
                    :key="g.id"
                    type="button"
                    class="block w-full text-left rounded border p-1.5 hover:bg-header-50 dark:hover:bg-header-800"
                    :class="[
                      conflictIds.has(g.id) || row.cells[pitch]!.length > 1 ? 'border-danger-400 bg-danger-50 dark:bg-danger-950' : 'border-header-200 dark:border-header-700',
                      g.validation === 'O' ? 'bg-amber-50/50 dark:bg-amber-950/40' : '',
                    ]"
                    :data-game="g.id"
                    @click="emit('open', g)"
                  >
                    <span class="flex items-center justify-between gap-2 text-[10px] text-primary-700 dark:text-primary-300 font-semibold">
                      <span class="truncate">{{ [g.codeCompetition, g.phase].filter(Boolean).join(' - ') }}</span>
                      <span class="flex shrink-0 items-center gap-1 text-header-600 dark:text-header-300 tabular-nums">
                        <span v-if="g.numeroOrdre">#{{ g.numeroOrdre }}</span>
                        <UIcon v-if="g.validation === 'O'" name="heroicons:lock-closed" class="w-3 h-3 text-amber-600 dark:text-amber-400" />
                      </span>
                    </span>
                    <span
                      v-for="side in (['A', 'B'] as const)"
                      :key="side"
                      class="flex items-baseline justify-between gap-2 text-header-900 dark:text-header-50"
                    >
                      <span
                        class="truncate font-medium"
                        :class="teamLine(g, side).placeholder ? 'text-orange-400 italic' : ''"
                        :title="teamLine(g, side).text"
                      >{{ teamLine(g, side).text }}</span>
                      <span v-if="hasScore(g)" class="shrink-0 font-bold tabular-nums">{{ side === 'A' ? g.scoreA : g.scoreB }}</span>
                    </span>
                    <span
                      v-if="showReferees || showShotclock"
                      class="mt-1 block space-y-0.5 border-t border-header-100 dark:border-header-800 pt-1 text-[10px] text-header-700 dark:text-header-300"
                    >
                      <template v-if="showReferees">
                        <span
                          v-for="(referee, index) in refereeLines(g)"
                          :key="index"
                          class="flex gap-1"
                        >
                          <UIcon name="heroicons:flag" class="w-3 h-3 shrink-0 mt-px" :title="t(index === 0 ? 'games.field.referee_1' : 'games.field.referee_2')" />
                          <span
                            class="truncate"
                            :class="referee?.placeholder ? 'text-orange-400 italic' : ''"
                            :title="referee?.text"
                          >{{ referee?.text ?? '-' }}</span>
                        </span>
                      </template>
                      <span v-if="showShotclock" class="flex gap-1">
                        <UIcon name="heroicons:clock" class="w-3 h-3 shrink-0 mt-px" :title="t('games.field.shotclock')" />
                        <span class="truncate" :title="g.timeshoot ?? undefined">{{ g.timeshoot || '-' }}</span>
                      </span>
                    </span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>
