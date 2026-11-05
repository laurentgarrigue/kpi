<script setup lang="ts">
import { shortEventDates } from '~/utils/events'
import { fixtureKey, fixturePath, fixturePlace, fixtureTitle, type Fixture } from '~/utils/fixtures'

// Agenda column of the home page (HOME-13, HOME-03, HOME-05): the other upcoming fixtures, then the latest results.
const LATEST_RESULTS = 7
const props = defineProps<{ after: Fixture[], recent: Fixture[], hasNext: boolean }>()
const { locale } = useI18n()
const localePath = useLocalePath()
const language = useLanguageTag()
const sections = computed(() => [
  { id: 'upcoming-fixtures', title: 'home.after', empty: 'home.noUpcomingFixtures', fixtures: props.after, hidden: props.hasNext && props.after.length === 0 },
  { id: 'recent-fixtures', title: 'home.latestResults', empty: 'home.noRecentFixtures', fixtures: props.recent.slice(0, LATEST_RESULTS), hidden: false },
].filter(section => !section.hidden))
</script>

<template>
  <section aria-labelledby="home-agenda">
    <div class="mb-3.5 flex items-baseline justify-between gap-3">
      <h2 id="home-agenda" class="text-[1.875rem] leading-none text-kpi-blue-700">{{ $t('home.agenda') }}</h2>
      <NuxtLink :to="localePath('/calendar')" class="text-sm underline">{{ $t('home.fullCalendar') }}</NuxtLink>
    </div>
    <section v-for="section in sections" :key="section.id" class="mb-6 last:mb-0" :aria-labelledby="`${section.id}-title`">
      <h3 :id="`${section.id}-title`" class="mb-1 font-sans text-sm font-bold text-muted">{{ $t(section.title) }}</h3>
      <p v-if="section.fixtures.length === 0" class="text-sm" :data-testid="`${section.id}-empty`">{{ $t(section.empty) }}</p>
      <ul v-else :data-testid="section.id">
        <li v-for="fixture in section.fixtures" :key="fixtureKey(fixture)">
          <NuxtLink :to="localePath(fixturePath(fixture))" class="group grid grid-cols-[5.125rem_minmax(0,1fr)] gap-3 border-t border-line py-2.5">
            <span class="font-display text-xl leading-tight text-kpi-blue-700 tabular-nums">{{ shortEventDates(fixture.start, fixture.end, language) }}</span>
            <span>
              <b class="block font-semibold group-hover:underline">{{ fixtureTitle(fixture, locale) }}</b>
              <small class="text-[0.8125rem] text-muted">{{ fixturePlace(fixture) }}</small>
            </span>
          </NuxtLink>
        </li>
      </ul>
    </section>
  </section>
</template>
