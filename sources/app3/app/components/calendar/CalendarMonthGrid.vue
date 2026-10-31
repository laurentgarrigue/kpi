<script setup lang="ts">
import { entrySection, gamedayCompetitionPath, SECTION_STYLES, weekSegments, type CalendarDay } from '~/utils/calendar'

// Month view (CAL-02, CAL-13): weeks Monday → Sunday; each gameday is one bar coloured by section spread over its days,
// dashed on the left when it continues the previous week; today marked; days outside the month and weekends shaded.
const props = defineProps<{ weeks: CalendarDay[][], caption: string, today: string }>()
const language = useLanguageTag()
const localePath = useLocalePath()
const MONDAY = '2024-01-01'
const DAY_MS = 86_400_000
const SATURDAY = 5
const weekdays = computed(() => Array.from({ length: 7 }, (_, index) =>
  new Intl.DateTimeFormat(language.value, { weekday: 'short', timeZone: 'UTC' }).format(new Date(Date.parse(MONDAY) + index * DAY_MS))))
const dayNumber = (date: string) => Number(date.slice(8))

const NUMBER_ROW = '1.75rem'
const MIN_LANE_HEIGHT = '1.5rem'

const rows = computed(() => props.weeks.map((week) => {
  const segments = weekSegments(week)
  const lanes = Math.max(1, ...segments.map(segment => segment.lane + 1))
  return { week, segments, lanes }
}))
</script>

<template>
  <div role="group" :aria-label="caption" class="overflow-hidden rounded-lg border border-line text-[0.6875rem] md:text-[0.8125rem]" data-testid="calendar-grid">
    <div class="grid grid-cols-7 bg-wash font-semibold text-muted" aria-hidden="true">
      <span v-for="weekday in weekdays" :key="weekday" class="px-1 py-1.5 text-center md:px-2 md:text-left">{{ weekday }}</span>
    </div>
    <div
      v-for="(row, index) in rows"
      :key="index"
      class="grid min-h-16 grid-cols-7 border-t border-line md:min-h-24"
      :style="{ gridTemplateRows: `${NUMBER_ROW} repeat(${row.lanes}, minmax(${MIN_LANE_HEIGHT}, auto))` }"
      data-testid="calendar-week"
    >
      <!-- Day cells: borders and numbers, behind the bars. -->
      <div
        v-for="(day, column) in row.week"
        :key="day.date"
        class="border-l border-line px-1 pt-1 first:border-l-0 md:px-1.5"
        :class="day.inMonth ? (column >= SATURDAY ? 'bg-wash/40' : '') : 'bg-wash/70 text-muted/50'"
        :style="{ gridColumn: column + 1, gridRow: `1 / span ${row.lanes + 1}` }"
        :data-date="day.date"
      >
        <time
          :datetime="day.date"
          class="tabular-nums"
          :class="day.date === today ? 'inline-grid size-6 place-items-center rounded-full bg-live font-semibold text-white' : 'text-muted'"
          :data-today="day.date === today ? '' : undefined"
        >{{ dayNumber(day.date) }}</time>
      </div>
      <div
        v-for="segment in row.segments"
        :key="segment.entry.id"
        class="z-10 m-px border-l-3 px-1 py-0.5 leading-tight [overflow-wrap:anywhere] md:mx-0.5 md:px-1.5"
        :class="[
          SECTION_STYLES[entrySection(segment.entry)].bar,
          segment.continuesBefore ? 'border-dashed' : 'rounded-l',
          segment.continuesAfter ? '' : 'rounded-r',
        ]"
        :style="{ gridColumn: `${segment.column + 1} / span ${segment.span}`, gridRow: segment.lane + 2 }"
        :data-gameday="segment.entry.id"
        :data-section="entrySection(segment.entry)"
        data-testid="calendar-bar"
      >
        <NuxtLink :to="localePath(gamedayCompetitionPath(segment.entry))" :title="segment.entry.label" class="text-ink hover:underline" data-testid="calendar-label">{{ segment.entry.label }}</NuxtLink>
      </div>
    </div>
  </div>
</template>
