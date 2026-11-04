<script setup lang="ts">
import { defaultPitchDay, gameDates, isLive, normalizeTime, sortGames } from '#kpi-layer/utils/results/games'
import type { ResultsGame } from '#kpi-layer/utils/results/types'
import { todayInParis } from '~/utils/events'

/**
 * « Pitches » tab (CMP-07): one row per time, one card per game in the column of its pitch. Days and pitches are
 * links (`day=`, `pitch=`), two lists of a GET form on phones; one pitch chosen, or a small screen: one column.
 */
const props = defineProps<{ games: ResultsGame[], showCompetition?: boolean }>()
const emit = defineEmits<{ refresh: [] }>()
const route = useRoute()
const now = useMinuteClock()
const language = useLanguageTag()
const form = useTemplateRef<HTMLFormElement>('form')

const dates = computed(() => gameDates(props.games))
const day = computed(() => {
  const requested = route.query.day
  return typeof requested === 'string' && dates.value.includes(requested) ? requested : defaultPitchDay(dates.value, todayInParis(now.value))
})
const ofDay = computed(() => sortGames(props.games.filter(game => game.g_date === day.value)))
const pitches = computed(() => [...new Set(ofDay.value.map(game => game.g_pitch ?? ''))]
  .sort((a, b) => (Number.parseInt(a, 10) || 0) - (Number.parseInt(b, 10) || 0) || a.localeCompare(b)))
const pitch = computed(() => (typeof route.query.pitch === 'string' && pitches.value.includes(route.query.pitch) ? route.query.pitch : null))
const slots = computed(() => {
  const byTime = new Map<string, ResultsGame[]>()
  for (const game of ofDay.value.filter(item => pitch.value === null || item.g_pitch === pitch.value)) {
    const time = normalizeTime(game.g_time)
    byTime.set(time, [...(byTime.get(time) ?? []), game])
  }
  return [...byTime].map(([time, games]) => ({ time, games }))
})
const column = (game: ResultsGame) => (pitch.value === null ? pitches.value.indexOf(game.g_pitch ?? '') + 2 : 2)
const link = (query: Record<string, string | undefined>) => ({ path: route.path, query: { ...route.query, ...query } })

function dayLabel(date: string, long = false): string {
  return new Intl.DateTimeFormat(language.value, { weekday: 'long', day: 'numeric', ...(long ? { month: 'long' } : {}), timeZone: 'UTC' }).format(new Date(date))
}
function apply() {
  if (form.value) {
    navigateTo({ path: route.path, query: { ...route.query, ...Object.fromEntries([...new FormData(form.value)].map(([key, value]) => [key, String(value) || undefined])) } })
  }
}

useAutoRefresh(() => isLive(props.games, now.value), () => emit('refresh'))
</script>

<template>
  <div class="space-y-2">
    <p v-if="games.length === 0" data-testid="no-games">{{ $t('results.noGames') }}</p>
    <template v-else>
      <div class="flex flex-wrap items-center justify-between gap-x-6 gap-y-3 pt-1 pb-2">
        <nav :aria-label="$t('results.filters.day')" class="max-[600px]:hidden" data-testid="pitch-days">
          <ul class="flex flex-wrap gap-2">
            <li v-for="date in dates" :key="date">
              <NuxtLink
                :to="link({ day: date })"
                :aria-current="date === day ? 'true' : undefined"
                class="inline-block rounded-full border px-3 py-1 text-sm capitalize"
                :class="date === day ? 'border-navy bg-navy text-white' : 'border-line hover:bg-wash'"
              >
                {{ dayLabel(date) }}
              </NuxtLink>
            </li>
          </ul>
        </nav>
        <nav v-if="pitches.length > 1" :aria-label="$t('results.pitches')" class="ml-auto max-[600px]:hidden" data-testid="pitch-pitches">
          <ul class="flex flex-wrap justify-end gap-2">
            <li v-for="item in [null, ...pitches]" :key="item ?? 'all'">
              <NuxtLink
                :to="link({ day: day ?? undefined, pitch: item ?? undefined })"
                :aria-current="item === pitch ? 'true' : undefined"
                class="inline-block rounded-full border px-3 py-1 text-sm"
                :class="item === pitch ? 'border-navy bg-navy text-white' : 'border-line hover:bg-wash'"
              >
                {{ item === null ? $t('results.allPitches') : $t('results.pitch', { pitch: item }) }}
              </NuxtLink>
            </li>
          </ul>
        </nav>
        <form
          ref="form"
          method="get"
          :action="route.path"
          class="hidden w-full grid-cols-2 gap-2.5 max-[600px]:grid"
          data-testid="pitch-selects"
          @change="apply"
          @submit.prevent="apply"
        >
          <label class="grid gap-1 text-[0.8125rem] text-muted">
            {{ $t('results.filters.day') }}
            <select name="day" class="rounded-md border border-line bg-surface px-2.5 py-2 text-[0.9375rem] text-ink">
              <option v-for="date in dates" :key="date" :value="date" :selected="date === day" class="capitalize">{{ dayLabel(date, true) }}</option>
            </select>
          </label>
          <label class="grid gap-1 text-[0.8125rem] text-muted">
            {{ $t('results.col.pitch') }}
            <select name="pitch" class="rounded-md border border-line bg-surface px-2.5 py-2 text-[0.9375rem] text-ink">
              <option value="">{{ $t('results.allPitches') }}</option>
              <option v-for="item in pitches" :key="item" :value="item" :selected="item === pitch">{{ $t('results.pitch', { pitch: item }) }}</option>
            </select>
          </label>
          <noscript><button type="submit" class="rounded-md border border-line px-3 py-1 text-sm">{{ $t('results.filters.apply') }}</button></noscript>
        </form>
      </div>

      <div
        :style="{ '--cols': pitch === null ? pitches.length : 1 }"
        :data-single="pitch !== null ? '' : undefined"
        data-testid="pitch-board"
      >
        <div
          v-if="pitch === null"
          class="sticky top-0 z-10 grid grid-cols-[4rem_repeat(var(--cols),minmax(0,1fr))] gap-3 border-b-2 border-navy bg-surface pt-2.5 pb-2 max-[820px]:hidden"
          aria-hidden="true"
          data-testid="pitch-heads"
        >
          <span />
          <span v-for="item in pitches" :key="item" class="font-display text-[1.375rem] leading-none text-kpi-blue-700">{{ $t('results.pitch', { pitch: item }) }}</span>
        </div>
        <section
          v-for="slot in slots"
          :key="slot.time"
          class="grid items-start gap-3 border-b border-line py-3 max-[820px]:grid-cols-[3.25rem_minmax(0,1fr)] max-[820px]:gap-2"
          :class="pitch === null ? 'grid-cols-[4rem_repeat(var(--cols),minmax(0,1fr))]' : 'grid-cols-[3.5rem_minmax(0,1fr)]'"
          :aria-label="slot.time"
          data-testid="pitch-slot"
        >
          <span class="pt-2 font-bold tabular-nums" data-testid="pitch-time">{{ slot.time }}</span>
          <ResultsPitchCard
            v-for="game in slot.games"
            :key="game.g_id"
            :game="game"
            :show-competition="showCompetition"
            :with-pitch="pitch !== null"
            class="max-[820px]:col-start-2!"
            :style="{ gridColumn: column(game) }"
          />
        </section>
      </div>
    </template>
  </div>
</template>
