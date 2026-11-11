<script setup lang="ts">
import { FAMILY_CLASSES, medalExplanation, medalName } from '~/utils/medals'
import { NEW_TAB_ATTRS } from '~/utils/links'
import { gameLink } from '~/utils/page-links'

/**
 * « Game medals » of a team page (FEATURE_MATCH_MEDALS.md § 9.2, MED-49, maquette `equipe-medailles`): the medals of
 * the season published on app3, one card per medal with its games (towards their score sheet). Hidden without medal.
 */
interface TeamMedalGame { id: number, side: 'A' | 'B', competition: string, number: number | null, date: string, team_a: { label: string } | null, team_b: { label: string } | null, score_a: number, score_b: number }
interface TeamMedal { code: string, count: number, games: TeamMedalGame[] }

const props = defineProps<{ number: number, seasons: string[] }>()
const route = useRoute()
const { locale } = useI18n()
const localePath = useLocalePath()
const context = usePageLinkContext()
const season = computed(() => (typeof route.query.medals === 'string' && props.seasons.includes(route.query.medals) ? route.query.medals : props.seasons[0] ?? null))
const { data } = await useApiResource<{ medals: TeamMedal[] }>(() => (season.value ? `/team/${props.number}/medals?season=${season.value}` : null), { notFoundIsError: false })
const { byCode } = await useMedalCatalog(season)
const medals = computed(() => (data.value?.medals ?? []).filter(medal => byCode.value.has(medal.code)))

const opponent = (game: TeamMedalGame) => (game.side === 'A' ? game.team_b : game.team_a)?.label ?? '—'
const score = (game: TeamMedalGame) => (game.side === 'A' ? `${game.score_a}-${game.score_b}` : `${game.score_b}-${game.score_a}`)
</script>

<template>
  <section v-if="medals.length" aria-labelledby="match-medals-title" class="border-t border-line pt-6" data-testid="team-match-medals">
    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-2">
      <h2 id="match-medals-title" class="text-[1.875rem] leading-none text-kpi-blue-700">{{ $t('medals.team.title') }}</h2>
      <nav v-if="seasons.length > 1" :aria-label="$t('medals.team.seasons')" class="ml-auto flex flex-wrap gap-2">
        <NuxtLink
          v-for="item in seasons"
          :key="item"
          :to="{ query: { ...route.query, medals: item } }"
          :aria-current="item === season ? 'page' : undefined"
          class="rounded-full border px-3 py-0.5 text-sm"
          :class="item === season ? 'border-navy bg-navy text-white' : 'border-line hover:bg-wash'"
        >
          {{ item }}
        </NuxtLink>
      </nav>
    </div>
    <p class="mt-1 mb-4 text-sm text-muted">
      {{ $t('medals.team.intro') }} <NuxtLink :to="localePath('/medals')" class="underline">{{ $t('medals.howLink') }}</NuxtLink>
    </p>
    <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <li v-for="medal in medals" :key="medal.code" class="grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1 rounded-lg border border-line p-3.5" :data-medal="medal.code" data-testid="team-medal">
        <MedalShield :item="byCode.get(medal.code)!" :size="56" />
        <span class="leading-tight">
          <b>{{ medalName(byCode.get(medal.code)!, locale).main }}</b><span v-if="medalName(byCode.get(medal.code)!, locale).english" class="text-muted italic"> · {{ medalName(byCode.get(medal.code)!, locale).english }}</span>
        </span>
        <span class="font-display text-5xl leading-none tabular-nums" :class="FAMILY_CLASSES[byCode.get(medal.code)!.family].text" data-testid="team-medal-count">{{ medal.count }}</span>
        <span class="col-span-3 text-sm text-muted">{{ medalExplanation(byCode.get(medal.code)!, locale) }}</span>
        <details class="col-span-3 text-sm">
          <summary class="cursor-pointer text-kpi-blue-700">{{ $t('medals.team.games', { n: medal.games.length }, medal.games.length) }}</summary>
          <ul class="mt-1.5">
            <li v-for="game in medal.games" :key="game.id" class="border-t border-line">
              <a :href="gameLink(game.id, context).href" v-bind="NEW_TAB_ATTRS" class="flex justify-between gap-2 py-1 hover:underline" data-testid="team-medal-game">
                <span>{{ $t('medals.team.against', { phase: game.competition, opponent: opponent(game) }) }}</span>
                <span class="font-display text-lg tabular-nums">{{ score(game) }}</span>
              </a>
            </li>
          </ul>
        </details>
      </li>
    </ul>
  </section>
</template>
