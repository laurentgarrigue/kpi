<script setup lang="ts">
import { dateTile, formatEventDates } from '~/utils/events'
import { NEW_TAB_ATTRS } from '~/utils/links'
import { fixturePath, fixturePlace, fixtureTitle, liveUrl, relativeWhen, type Fixture, type GroupFixture } from '~/utils/fixtures'
import { eventIcsUrl } from '~/utils/page-links'

// The next fixture opens the home page (HOME-11, HOME-12): when, name, place and dates in large figures; the group days
// held on its very dates; follow it live while on, or see its programme.
const props = defineProps<{ fixture: Fixture, programme: GroupFixture[], today: string }>()
const { locale } = useI18n()
const localePath = useLocalePath()
const language = useLanguageTag()
const { app2BaseUrl, api2BaseUrl } = useRuntimeConfig().public

const when = computed(() => relativeWhen(props.fixture.start ?? props.today, props.fixture.end, props.today))
const live = computed(() => when.value.kind === 'now')
const tile = computed(() => dateTile(props.fixture.start, props.fixture.end, language.value))
const month = computed(() => new Intl.DateTimeFormat(language.value, { month: 'long', timeZone: 'UTC' }).format(new Date(props.fixture.end ?? props.fixture.start ?? props.today)))
const where = computed(() => [fixturePlace(props.fixture), formatEventDates(props.fixture.start, props.fixture.end, language.value)].filter(Boolean).join(', '))
</script>

<template>
  <section
    class="grid items-end gap-x-10 gap-y-3 border-b border-line pt-6.5 pb-8.5 lg:grid-cols-[minmax(0,1fr)_auto]"
    aria-labelledby="home-next"
    :data-live="live ? '' : undefined"
    data-testid="home-next"
  >
    <div>
      <p class="mb-1.5 inline-flex items-center gap-2 font-bold" :class="live ? 'text-live' : 'text-kpi-blue-700'" data-testid="home-next-when">
        <span v-if="live" class="size-2.5 animate-pulse rounded-full bg-live motion-reduce:animate-none" aria-hidden="true" />
        {{ when.kind === 'days' ? $t('home.when.days', { n: when.days }) : $t(`home.when.${when.kind}`) }}
      </p>
      <h2 id="home-next" class="text-[3.25rem] leading-[0.95] text-navy lg:text-[4.5rem] dark:text-ink">{{ fixtureTitle(fixture, locale) }}</h2>
      <p class="mt-2.5 text-lg">{{ where }}</p>
      <p v-if="programme.length" class="mt-3.5 flex flex-wrap items-center gap-2 text-sm text-muted" data-testid="home-programme">
        {{ $t('home.programme') }}
        <NuxtLink
          v-for="item in programme"
          :key="`${item.season}-${item.code}`"
          :to="localePath(fixturePath(item))"
          class="rounded-full border border-line px-3 py-1 text-ink hover:bg-wash"
        >
          {{ fixtureTitle(item, locale) }}
        </NuxtLink>
      </p>
      <div class="mt-5.5 flex flex-wrap gap-2.5">
        <template v-if="live">
          <a :href="liveUrl(fixture, app2BaseUrl)" v-bind="NEW_TAB_ATTRS" class="inline-flex items-center gap-2 rounded-md bg-live px-4.5 py-2.5 font-semibold text-white" data-testid="live-cta">
            <span class="size-2 rounded-full bg-white" aria-hidden="true" />{{ $t('results.followLive') }}<span class="sr-only"> {{ $t('a11y.newTab') }}</span>
          </a>
          <NuxtLink :to="localePath(fixturePath(fixture))" class="inline-flex items-center rounded-md border border-line px-4.5 py-2.5 font-semibold hover:bg-wash" data-testid="home-next-link">{{ $t('home.gamesAndResults') }}</NuxtLink>
        </template>
        <template v-else>
          <NuxtLink :to="localePath(fixturePath(fixture))" class="inline-flex items-center rounded-md bg-navy px-4.5 py-2.5 font-semibold text-white hover:bg-kpi-blue-700" data-testid="home-next-link">{{ $t('home.seeProgramme') }}</NuxtLink>
          <a v-if="fixture.kind === 'event'" :href="eventIcsUrl(api2BaseUrl, fixture.id)" class="inline-flex items-center rounded-md border border-line px-4.5 py-2.5 font-semibold hover:bg-wash" data-testid="home-next-ics">{{ $t('home.addToCalendar') }}</a>
        </template>
      </div>
    </div>
    <p v-if="tile" class="-order-1 flex items-baseline gap-2.5 font-display leading-[0.85] text-navy lg:order-none lg:block lg:text-right dark:text-kpi-blue-500" aria-hidden="true">
      <span class="text-[4rem] tabular-nums lg:block lg:text-[9.375rem]">{{ tile.days }}</span>
      <span class="text-[1.625rem] text-kpi-blue-500 lg:block lg:text-[2.125rem]">{{ month }}</span>
    </p>
  </section>
</template>
