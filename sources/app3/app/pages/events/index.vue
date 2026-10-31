<script setup lang="ts">
import { eventsByMonth, eventsOfYear, eventYears, matchesEventQuery, shortEventDates, splitByToday, todayInParis, type PublicEvent } from '~/utils/events'

// Events of a year (PAGE_EVENT_GROUP.md § 2.2, EVL-*): the page behind the « Events » menu entry, by month, upcoming
// first; year and search in a GET form.
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()
const language = useLanguageTag()
const today = todayInParis(new Date())
const form = useTemplateRef<HTMLFormElement>('form')

const { data: events, error } = await useApiResource<PublicEvent[]>('/events/all', { notFoundIsError: false })
const years = computed(() => eventYears(events.value ?? []))
// Requested year when it has events, otherwise the current one, otherwise the most recent one.
const year = computed(() => {
  const requested = Number(route.query.year)
  const current = Number(today.slice(0, 4))
  return [requested, current].find(candidate => years.value.includes(candidate)) ?? years.value[0] ?? current
})
const query = computed(() => (typeof route.query.q === 'string' ? route.query.q : ''))
const found = computed(() => eventsOfYear(events.value ?? [], year.value).filter(item => matchesEventQuery(item, query.value)))
const split = computed(() => splitByToday(found.value, today))
const next = computed(() => split.value.upcoming[0] ?? null)
const sections = computed(() => [
  { id: 'upcoming', title: null, months: eventsByMonth(split.value.upcoming) },
  { id: 'past', title: split.value.upcoming.length ? t('events.past') : null, months: eventsByMonth(split.value.past) },
].filter(section => section.months.length))

const monthName = (month: string) => new Intl.DateTimeFormat(language.value, { month: 'long', timeZone: 'UTC' }).format(new Date(`${month}-01`))

function apply() {
  if (form.value) {
    const values = Object.fromEntries([...new FormData(form.value)].map(([key, value]) => [key, String(value) || undefined]))
    navigateTo({ path: route.path, query: values }, { replace: true })
  }
}

useSeoMeta({
  title: () => t('events.pageTitle', { year: year.value }),
  description: () => t('events.description', { year: year.value }),
})
</script>

<template>
  <div class="space-y-4">
    <SitePageHeading :title="$t('events.title')" :intro="$t('events.intro')" />
    <p v-if="error" class="text-kpi-red-600" data-testid="events-unavailable">{{ $t('common.unavailable') }}</p>
    <template v-else>
      <form
        ref="form"
        method="get"
        :action="localePath('/events')"
        class="flex flex-wrap items-center gap-x-5 gap-y-3"
        data-testid="events-tools"
        @change="apply"
        @input="apply"
        @submit.prevent="apply"
      >
        <label class="flex items-center gap-2">
          {{ $t('events.season') }}
          <select name="year" class="rounded-md border border-line bg-surface px-2.5 py-1.5">
            <option v-for="item in years" :key="item" :value="item" :selected="item === year">{{ item }}</option>
          </select>
        </label>
        <input
          type="search"
          name="q"
          :value="query"
          :placeholder="$t('events.search')"
          :aria-label="$t('events.searchLabel')"
          class="min-w-[min(20rem,100%)] rounded-md border border-line bg-surface px-2.5 py-1.5"
        >
        <noscript><button type="submit" class="rounded-md border border-line px-3 py-1.5">{{ $t('results.filters.apply') }}</button></noscript>
      </form>

      <p v-if="found.length === 0" class="py-5 text-muted" data-testid="events-empty">{{ $t('events.noMatch') }}</p>
      <div v-else class="pb-10" data-testid="events-list">
        <template v-for="section in sections" :key="section.id">
          <h2 v-if="section.title" class="mt-9 text-[1.625rem] leading-none text-muted">{{ section.title }}</h2>
          <section
            v-for="group in section.months"
            :key="`${section.id}-${group.month}`"
            class="grid gap-x-6 border-t border-line py-4.5 md:grid-cols-[10.625rem_minmax(0,1fr)]"
            :aria-labelledby="`${section.id}-${group.month}`"
            data-testid="events-month"
          >
            <h2 :id="`${section.id}-${group.month}`" class="mt-1.5 mb-1.5 self-start text-[1.75rem] capitalize leading-none text-kpi-blue-700 md:sticky md:top-2 md:mb-0">{{ monthName(group.month) }}</h2>
            <ul>
              <li
                v-for="item in group.events"
                :key="item.id"
                class="[&+li>a]:border-t [&+li>a]:border-line"
                :data-next="item === next ? '' : undefined"
              >
                <NuxtLink
                  :to="localePath(`/events/${item.id}`)"
                  class="group grid grid-cols-[4.875rem_minmax(0,1fr)] gap-3.5 py-2.5 md:grid-cols-[6rem_minmax(0,1fr)]"
                  :class="{ '-ml-2.5 rounded-md bg-wash pl-2.5': item === next }"
                >
                  <span class="font-display text-[1.375rem] leading-tight text-navy tabular-nums dark:text-ink">{{ shortEventDates(item.start, item.end, language) }}</span>
                  <span>
                    <b class="block font-semibold group-hover:underline">
                      {{ item.libelle }}
                      <span v-if="item === next" class="ml-2 inline-block rounded bg-kpi-blue-700 px-1.5 align-[2px] text-xs font-bold text-white dark:bg-kpi-blue-900">{{ $t('events.next') }}</span>
                    </b>
                    <small class="text-sm text-muted">{{ item.place }}</small>
                  </span>
                </NuxtLink>
              </li>
            </ul>
          </section>
        </template>
      </div>
    </template>
  </div>
</template>
