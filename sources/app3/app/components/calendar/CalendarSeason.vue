<script setup lang="ts">
import type { RouteLocationRaw } from 'vue-router'
import { entrySection, SECTION_STYLES, type CalendarDay } from '~/utils/calendar'

// Season view: the twelve months of the year at a glance; a day with gamedays takes the colour of its first section
// (sections sorted), the labels of its gamedays in its tooltip; the month name opens the month view.
const props = defineProps<{
  months: { month: string, weeks: CalendarDay[][] }[]
  today: string
  monthLink: (month: string) => RouteLocationRaw
}>()
const language = useLanguageTag()
const monthName = (month: string) => new Intl.DateTimeFormat(language.value, { month: 'long', timeZone: 'UTC' }).format(new Date(`${month}-01`))
const MONDAY = '2024-01-01'
const DAY_MS = 86_400_000
const weekdays = computed(() => Array.from({ length: 7 }, (_, index) =>
  new Intl.DateTimeFormat(language.value, { weekday: 'narrow', timeZone: 'UTC' }).format(new Date(Date.parse(MONDAY) + index * DAY_MS))))

const sectionOf = (day: CalendarDay) => Math.min(...day.entries.map(entrySection))
const dayTitle = (day: CalendarDay) => day.entries.map(entry => entry.label).join('\n')
const dayNumber = (date: string) => Number(date.slice(8))
const totals = computed(() => props.months.map(item => new Set(item.weeks.flat().filter(day => day.inMonth).flatMap(day => day.entries.map(entry => entry.id))).size))
</script>

<template>
  <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-testid="calendar-season">
    <section v-for="(item, index) in months" :key="item.month" class="rounded-lg border border-line p-2.5" :data-month="item.month">
      <h3 class="mb-1.5 flex items-baseline justify-between text-base capitalize">
        <NuxtLink :to="monthLink(item.month)" class="underline-offset-2 hover:underline" data-testid="season-month-link">{{ monthName(item.month) }}</NuxtLink>
        <small v-if="totals[index]" class="text-xs font-normal text-muted">{{ totals[index] }}</small>
      </h3>
      <div class="grid grid-cols-7 gap-px text-center text-[0.6875rem] text-muted" aria-hidden="true">
        <span v-for="(weekday, column) in weekdays" :key="column">{{ weekday }}</span>
      </div>
      <div class="grid grid-cols-7 gap-px text-center text-xs tabular-nums">
        <template v-for="week in item.weeks" :key="week[0]!.date">
          <template v-for="day in week" :key="day.date">
            <span v-if="!day.inMonth" aria-hidden="true" />
            <NuxtLink
              v-else-if="day.entries.length > 0"
              :to="monthLink(item.month)"
              :title="dayTitle(day)"
              class="rounded-sm border-b-2 py-0.5 text-ink"
              :class="[SECTION_STYLES[sectionOf(day) as keyof typeof SECTION_STYLES].bar, day.date === today ? 'font-bold ring-1 ring-live' : '']"
              :data-section="sectionOf(day)"
              data-testid="season-day"
            >{{ dayNumber(day.date) }}</NuxtLink>
            <span v-else class="py-0.5 text-muted" :class="day.date === today ? 'rounded-sm font-bold ring-1 ring-live' : ''">{{ dayNumber(day.date) }}</span>
          </template>
        </template>
      </div>
    </section>
  </div>
</template>
