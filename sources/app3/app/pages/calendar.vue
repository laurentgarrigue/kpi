<script setup lang="ts">
import type { GroupSection } from '#kpi-layer/utils/results/types'
import {
  calendarApiPath, calendarWeeks, entriesByDate, entrySection, gridRange, lookaheadRange, monthGrid, nextMonthWithEntries,
  parseFilters, parseMonth, parseView, reconcileFilters, shiftMonth, shiftYear, type CalendarEntry, type CalendarView,
} from '~/utils/calendar'
import { todayInParis } from '~/utils/events'

// Competition calendar (PAGE_CALENDAR.md, CAL-01 to CAL-13): by week (default) or the month grid, on every screen.
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()
const language = useLanguageTag()

const today = todayInParis(new Date())
const month = computed(() => parseMonth(route.query.month, today))
const view = computed(() => parseView(route.query.view))
const monthLabel = computed(() => new Intl.DateTimeFormat(language.value, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(`${month.value}-01`)))

// Groups of every section: the group filter keeps only those of the chosen section (CAL-09).
const { data: groups } = await useApiResource<{ sections: GroupSection[] }>('/calendar/groups', { notFoundIsError: false })
const filters = computed(() => reconcileFilters(parseFilters(route.query), groups.value?.sections ?? []))

// One request per page: the whole weeks of the grid, the agenda keeping the gamedays of the month (§ 3).
const { data: entries, error } = await useApiResource<CalendarEntry[]>(() => calendarApiPath(gridRange(month.value), filters.value), { notFoundIsError: false })
const days = computed(() => entriesByDate(entries.value ?? [], month.value))
const weeks = computed(() => monthGrid(entries.value ?? [], month.value))
const weekBlocks = computed(() => calendarWeeks(entries.value ?? [], month.value, today))
const sectionsShown = computed(() => [...new Set((entries.value ?? []).map(entrySection))].sort((a, b) => a - b))

// Empty month: look for the next month having gamedays (CAL-05).
const { data: ahead } = await useApiResource<CalendarEntry[]>(
  () => (entries.value && days.value.length === 0 ? calendarApiPath(lookaheadRange(month.value), filters.value) : null),
  { notFoundIsError: false },
)
const nextMonth = computed(() => nextMonthWithEntries(ahead.value ?? [], month.value))

// The month view is kept by the navigation; the week view is the default, without parameter (CAL-13).
const viewQuery = (target: CalendarView) => (target === 'month' ? { view: target } : {})
const monthLink = (target: string) => ({ path: localePath('/calendar'), query: { ...filters.value, month: target, ...viewQuery(view.value) } })
const viewLink = (target: CalendarView) => ({ path: localePath('/calendar'), query: { ...filters.value, month: month.value, ...viewQuery(target) } })
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
  <div class="space-y-4">
    <SitePageHeading :title="$t('calendar.title')" />
    <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
      <nav class="flex flex-wrap items-center gap-2" :aria-label="$t('calendar.title')" data-testid="calendar-nav">
        <NuxtLink :to="monthLink(shiftYear(month, -1))" :title="$t('calendar.previousYear')" class="grid size-9 place-items-center rounded-md border border-line hover:bg-wash" data-testid="previous-year"><span aria-hidden="true">«</span><span class="sr-only">{{ $t('calendar.previousYear') }}</span></NuxtLink>
        <NuxtLink :to="monthLink(shiftMonth(month, -1))" :title="$t('calendar.previous')" class="grid size-9 place-items-center rounded-md border border-line hover:bg-wash" data-testid="previous-month"><span aria-hidden="true">‹</span><span class="sr-only">{{ $t('calendar.previous') }}</span></NuxtLink>
        <h2 class="min-w-[7.5ch] px-1.5 text-center text-[1.625rem] leading-none capitalize md:text-[2.125rem] text-navy dark:text-ink" aria-live="polite" data-testid="calendar-month">{{ monthLabel }}</h2>
        <NuxtLink :to="monthLink(shiftMonth(month, 1))" :title="$t('calendar.next')" class="grid size-9 place-items-center rounded-md border border-line hover:bg-wash" data-testid="next-month"><span aria-hidden="true">›</span><span class="sr-only">{{ $t('calendar.next') }}</span></NuxtLink>
        <NuxtLink :to="monthLink(shiftYear(month, 1))" :title="$t('calendar.nextYear')" class="grid size-9 place-items-center rounded-md border border-line hover:bg-wash" data-testid="next-year"><span aria-hidden="true">»</span><span class="sr-only">{{ $t('calendar.nextYear') }}</span></NuxtLink>
        <NuxtLink :to="monthLink(today.slice(0, 7))" class="ml-1 text-sm underline" data-testid="current-month">{{ $t('calendar.today') }}</NuxtLink>
      </nav>
      <nav :aria-label="$t('calendar.views')" data-testid="calendar-views">
        <ul class="flex overflow-hidden rounded-md border border-line text-sm">
          <li v-for="item in (['week', 'month'] as const)" :key="item" class="border-l border-line first:border-l-0">
            <NuxtLink
              :to="viewLink(item)"
              :aria-current="item === view ? 'page' : undefined"
              class="block px-3.5 py-1.5"
              :class="item === view ? 'bg-navy text-white' : 'hover:bg-wash'"
            >
              {{ $t(item === 'week' ? 'calendar.byWeek' : 'calendar.monthView') }}
            </NuxtLink>
          </li>
        </ul>
      </nav>
    </div>
    <CalendarFilters :month="month" :view="view" :filters="filters" :sections="groups?.sections ?? []" />

    <p v-if="error" class="text-kpi-red-600" data-testid="calendar-unavailable">{{ $t('common.unavailable') }}</p>
    <div v-else-if="days.length === 0" class="py-6 text-muted" data-testid="calendar-empty">
      <p>{{ $t('calendar.empty') }}</p>
      <NuxtLink v-if="nextMonth" :to="monthLink(nextMonth)" class="underline" data-testid="calendar-next-with-entries">
        {{ $t('calendar.nextWithEntries', { month: nextMonthLabel }) }}
      </NuxtLink>
    </div>
    <CalendarWeeks v-else-if="view === 'week'" :weeks="weekBlocks" />
    <div v-else class="space-y-3 pb-10">
      <p :aria-label="$t('calendar.legend')" class="flex flex-wrap gap-x-4.5 gap-y-1.5" data-testid="calendar-legend">
        <CalendarSectionBadge v-for="section in sectionsShown" :key="section" :section="section" />
      </p>
      <CalendarMonthGrid :weeks="weeks" :caption="t('calendar.grid')" :today="today" />
    </div>
  </div>
</template>
