<script setup lang="ts">
import { normalizeTime } from '#kpi-layer/utils/results/games'
import type { ResultsGame } from '#kpi-layer/utils/results/types'
import { NEW_TAB_ATTRS } from '~/utils/links'
import { gameLink, pdfUrl } from '~/utils/page-links'
import { personName } from '~/utils/people'

// One game of a games list (PAGE_COMPETITION.md § 3.1); the competition is shown in event / group views.
const props = defineProps<{ game: ResultsGame, competitionHref?: string }>()
const context = usePageLinkContext()

const sheet = computed(() => (props.game.g_status === 'ATT' ? null : gameLink(props.game.g_id, context.value)))
const scoreSheet = computed(() => (props.game.g_validation === 'O' ? pdfUrl({ kind: 'gameSheet', game: props.game.g_id }, context.value) : null))
const referees = computed(() => [props.game.r_1, props.game.r_2].map(personName).filter(Boolean).join(', '))
</script>

<template>
  <tr class="border-t border-line align-middle" :data-game="game.g_id">
    <td class="hidden px-2 py-2 text-sm tabular-nums text-ink/70 sm:table-cell">#{{ game.g_number }}</td>
    <td class="px-2 py-2 tabular-nums">{{ normalizeTime(game.g_time) }}</td>
    <td class="hidden px-2 py-2 text-sm sm:table-cell">{{ game.g_pitch }}</td>
    <td v-if="competitionHref" class="hidden px-2 py-2 text-sm sm:table-cell">
      <NuxtLink :to="competitionHref" class="hover:underline" :title="game.c_label ?? undefined">
        {{ game.c_code }}<span class="sr-only"> — {{ game.c_label }}</span>
      </NuxtLink>
    </td>
    <td class="hidden px-2 py-2 text-sm md:table-cell">{{ game.d_phase }}<template v-if="game.g_code"> · {{ game.g_code }}</template></td>
    <td class="px-2 py-2 text-right">
      <ResultsTeamName :label="game.t_a_label" :number="game.t_a_number" :competition="game.c_code" />
    </td>
    <td class="px-2 py-2 text-center">
      <a v-if="sheet" :href="sheet.href" v-bind="NEW_TAB_ATTRS" class="hover:underline" data-testid="game-sheet">
        <ResultsScore :game="game" /><span class="sr-only"> {{ $t('results.gameSheet') }} {{ $t('a11y.newTab') }}</span>
      </a>
      <ResultsScore v-else :game="game" />
    </td>
    <td class="px-2 py-2">
      <ResultsTeamName :label="game.t_b_label" :number="game.t_b_number" :competition="game.c_code" />
    </td>
    <td class="hidden px-2 py-2 text-sm lg:table-cell">{{ referees }}</td>
    <td class="px-2 py-2">
      <ResultsGameStatus :game="game" />
      <a v-if="scoreSheet" :href="scoreSheet" v-bind="NEW_TAB_ATTRS" class="ml-2 text-xs underline" data-testid="score-sheet-pdf">
        PDF<span class="sr-only"> {{ $t('results.scoreSheetPdf') }} {{ $t('a11y.newTab') }}</span>
      </a>
    </td>
  </tr>
</template>
