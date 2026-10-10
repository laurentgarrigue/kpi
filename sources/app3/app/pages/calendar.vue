<script setup lang="ts">
import type { GroupSection } from '#kpi-layer/utils/results/types'
import {
  calendarApiPath, entriesByDate, gridRange, lookaheadRange, monthGrid, nextMonthWithEntries, parseFilters, parseMonth,
  reconcileFilters, shiftMonth, shiftYear, type CalendarEntry,
} from '~/utils/calendar'
import { todayInParis } from '~/utils/events'

// Competition calendar (PAGE_CALENDAR.md, CAL-01 to CAL-05): the agenda on every screen, preceded by the month
// grid on large screens.
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()
const language = useLanguageTag()

const today = todayInParis(new Date())
const month = computed(() => parseMonth(route.query.month, today))
const monthLabel = computed(() => new Intl.DateTimeFormat(language.value, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${month.value}-01`)))

// Groups of every section: the group filter keeps only those of the chosen section (CAL-09).
const { data: groups } = await useApiResource<{ sections: GroupSection[] }>('/calendar/groups', { notFoundIsError: false })
const filters = computed(() => reconcileFilters(parseFilters(route.query), groups.value?.sections ?? []))

// One request per page: the whole weeks of the grid, the agenda keeping the gamedays of the month (§ 3).
const { data: entries, error } = await useApiResource<CalendarEntry[]>(() => calendarApiPath(gridRange(month.value), filters.value), { notFoundIsError: false })
const days = computed(() => entriesByDate(entries.value ?? [], month.value))
const weeks = computed(() => monthGrid(entries.value ?? [], month.value))

// Empty month: look for the next month having gamedays (CAL-05).
const { data: ahead } = await useApiResource<CalendarEntry[]>(
  () => (entries.value && days.value.length === 0 ? calendarApiPath(lookaheadRange(month.value), filters.value) : null),
  { notFoundIsError: false },
)
const nextMonth = computed(() => nextMonthWithEntries(ahead.value ?? [], month.value))

const monthLink = (target: string) => ({ path: localePath('/calendar'), query: { ...filters.value, month: target } })
const nextMonthLabel = computed(() => (nextMonth.value
  ? new Intl.DateTimeFormat(language.value, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${nextMonth.value}-01`))
  : ''))

useSeoMeta({
  title: () => t('calendar.pageTitle', { month: monthLabel.value }),
  description: () => t('calendar.description', { month: monthLabel.value }),
})
useHead(() => ({
  script: days.value.length === 0
    ? []
    : [{
        type: 'application/ld+json',
        innerHTML: JSON.stringify(days.value.flatMap(day => day.entries).map(entry => ({
          '@context': 'https://schema.org',
          '@type': 'SportsEvent',
          'name': entry.label,
          'sport': 'Canoe polo',
          'startDate': entry.start,
          'endDate': entry.end,
          'location': { '@type': 'Place', 'name': entry.place },
        }))),
      }],
}))
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-5xl text-kpi-blue-600">{{ $t('calendar.title') }}</h1>
    <div class="flex flex-wrap items-center justify-between gap-4">
      <p class="font-display text-3xl capitalize" data-testid="calendar-month">{{ monthLabel }}</p>
      <nav class="flex flex-wrap gap-2" :aria-label="$t('calendar.title')" data-testid="calendar-nav">
        <NuxtLink :to="monthLink(shiftYear(month, -1))" :aria-label="$t('calendar.previousYear')" :title="$t('calendar.previousYear')" class="rounded border border-line px-2 py-1 hover:bg-kpi-blue-50" data-testid="previous-year"><span aria-hidden="true">«</span><span class="sr-only">{{ $t('calendar.previousYear') }}</span></NuxtLink>
        <NuxtLink :to="monthLink(shiftMonth(month, -1))" :aria-label="$t('calendar.previous')" :title="$t('calendar.previous')" class="rounded border border-line px-2 py-1 hover:bg-kpi-blue-50" data-testid="previous-month"><span aria-hidden="true">‹</span><span class="sr-only">{{ $t('calendar.previous') }}</span></NuxtLink>
        <NuxtLink :to="monthLink(today.slice(0, 7))" class="rounded border border-line px-2 py-1 hover:bg-kpi-blue-50" data-testid="current-month">{{ $t('calendar.today') }}</NuxtLink>
        <NuxtLink :to="monthLink(shiftMonth(month, 1))" :aria-label="$t('calendar.next')" :title="$t('calendar.next')" class="rounded border border-line px-2 py-1 hover:bg-kpi-blue-50" data-testid="next-month"><span aria-hidden="true">›</span><span class="sr-only">{{ $t('calendar.next') }}</span></NuxtLink>
        <NuxtLink :to="monthLink(shiftYear(month, 1))" :aria-label="$t('calendar.nextYear')" :title="$t('calendar.nextYear')" class="rounded border border-line px-2 py-1 hover:bg-kpi-blue-50" data-testid="next-year"><span aria-hidden="true">»</span><span class="sr-only">{{ $t('calendar.nextYear') }}</span></NuxtLink>
      </nav>
    </div>
    <CalendarFilters :month="month" :filters="filters" :sections="groups?.sections ?? []" />

    <p v-if="error" class="text-kpi-red-600" data-testid="calendar-unavailable">{{ $t('common.unavailable') }}</p>
    <div v-else-if="days.length === 0" data-testid="calendar-empty">
      <p>{{ $t('calendar.empty') }}</p>
      <NuxtLink v-if="nextMonth" :to="monthLink(nextMonth)" class="underline" data-testid="calendar-next-with-entries">
        {{ $t('calendar.nextWithEntries', { month: nextMonthLabel }) }}
      </NuxtLink>
    </div>
    <template v-else>
      <div class="hidden lg:block">
        <CalendarMonthGrid :weeks="weeks" :caption="t('calendar.grid')" />
      </div>
      <CalendarAgenda :days="days" />
    </template>
  </div>
</template>
