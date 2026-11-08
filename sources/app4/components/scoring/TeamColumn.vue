<script setup lang="ts">
import type { ScoringPlayer, TeamSide } from '~/types/scoring'
import type { PlayerTally } from '~/utils/scoringRoster'

/**
 * One team's side of the "Déroulement" view: its roster, to pick the player of the next
 * fact. Each row carries the player's goals and current card, so the operator sees who is
 * already carded before the progression alert ever opens (spec §7.3). Running penalties
 * live on the board, next to the clocks they follow. Left/right of the entry panel on wide
 * screens (no team name: the board above already says which side is which).
 *
 * On mobile both teams sit side by side with truncated names; tapping the team name above
 * the column asks the page to widen this side for a few seconds (`expand`).
 */
const props = defineProps<{
  team: TeamSide
  teamName: string
  players: ScoringPlayer[]
  /** Goals and cards per player matric (utils/scoringRoster). */
  tallies: Map<string, PlayerTally>
  /** matric of the selected player on THIS team (0 = team-level fact), null if none. */
  selectedMatric: number | null
  /** Players matching the number being typed in the entry panel (both teams). */
  candidateMatrics?: Set<number>
  /** Exclusion marker per player matric: R = replaced at penalty end, D = out to the end. */
  excludedCard: Map<string, 'R' | 'D'>
  showRoster?: boolean
  /** Mobile: this side is the widened one (its team name was just tapped). */
  expanded?: boolean
  canScore?: boolean
}>()

const emit = defineEmits<{
  select: [player: ScoringPlayer]
  'select-team-fact': []
  reload: []
  expand: []
}>()

const { t } = useI18n()

const TEAM_FACT_MATRIC = 0
const exclusionOf = (p: ScoringPlayer) => props.excludedCard.get(String(p.matric))
const tallyOf = (p: ScoringPlayer) => props.tallies.get(String(p.matric))
</script>

<template>
  <div class="space-y-2 min-w-0">
    <!-- Mobile only: the team name, which widens this side for a moment -->
    <button
      type="button"
      class="md:hidden w-full flex items-center gap-1 rounded-md px-2 py-1.5 text-left text-sm font-semibold bg-header-100 dark:bg-header-800"
      :aria-expanded="expanded"
      @click="emit('expand')"
    >
      <span class="flex-1 min-w-0 truncate">{{ teamName }}</span>
      <UIcon name="i-heroicons-arrows-pointing-out" class="size-4 shrink-0 opacity-60" />
    </button>

    <template v-if="showRoster">
      <!-- Empty game sheet: say so, offer to reload the present players, and keep a
           team-level fact possible so the fact buttons are not dead. -->
      <div
        v-if="!players.length"
        class="flex flex-wrap items-center gap-2 px-3 py-2 rounded-lg border border-dashed border-header-300 dark:border-header-600 text-sm text-header-600 dark:text-header-300"
      >
        <span class="flex-1">{{ t('scoring.roster_empty') }}</span>
        <UButton
          size="xs"
          variant="ghost"
          icon="i-heroicons-arrow-path"
          :disabled="!canScore"
          @click="emit('reload')"
        >{{ t('scoring.settings.reload_players') }}</UButton>
        <UButton
          size="xs"
          :variant="selectedMatric === TEAM_FACT_MATRIC ? 'solid' : 'outline'"
          :disabled="!canScore"
          @click="emit('select-team-fact')"
        >{{ t('scoring.team_fact') }}</UButton>
      </div>

      <ul class="space-y-1">
        <li v-for="p in players" :key="p.matric">
          <button
            type="button"
            class="roster-row w-full flex items-center gap-3 max-md:gap-1 px-2 py-2 rounded-lg text-left text-sm border transition-colors"
            :class="selectedMatric === p.matric
              ? 'border-primary-500 bg-primary-100 dark:bg-primary-900/50 ring-2 ring-inset ring-primary-500'
              : candidateMatrics?.has(p.matric)
                ? 'border-amber-400 bg-amber-50 dark:bg-amber-950/40 ring-1 ring-inset ring-amber-400'
                : 'border-header-200 dark:border-header-700 hover:bg-header-50 dark:hover:bg-header-800'"
            :aria-pressed="selectedMatric === p.matric"
            :disabled="!canScore"
            @click="emit('select', p)"
          >
            <!-- Staff have no meaningful number (0): the status takes its place -->
            <span class="w-12 max-md:w-9 shrink-0 text-center">
              <UBadge v-if="p.capitaine === 'E'" size="xs" variant="soft" color="neutral">{{ t('scoring.settings.status_E') }}</UBadge>
              <span v-else class="scoring-digits text-base">{{ p.numero }}</span>
            </span>
            <span class="flex-1 min-w-0 truncate" :class="exclusionOf(p) ? 'opacity-60' : ''">
              {{ p.nom.toUpperCase() }} {{ p.prenom }}
              <UBadge v-if="p.capitaine === 'C'" size="xs" variant="soft" class="ml-1 max-md:ml-0 align-middle">Cap.</UBadge>
            </span>
            <span v-if="tallyOf(p)?.goals" class="inline-flex items-center gap-0.5 shrink-0" :title="t('scoring.roster_goals', { n: tallyOf(p)!.goals })">
              <ScoringEventIcon code="B" />
              <span v-if="tallyOf(p)!.goals > 1" class="scoring-digits text-xs">×{{ tallyOf(p)!.goals }}</span>
            </span>
            <!-- Every card, in match order (an R/D card is also the exclusion marker) -->
            <span v-if="tallyOf(p)?.cards.length" class="inline-flex items-center gap-0.5 shrink-0">
              <ScoringEventIcon v-for="(card, i) in tallyOf(p)!.cards" :key="i" :code="card" />
            </span>
          </button>
        </li>
      </ul>
    </template>
  </div>
</template>

<style scoped>
.roster-row:focus-visible {
  outline: 2px solid var(--ui-primary);
  outline-offset: 2px;
}
</style>
