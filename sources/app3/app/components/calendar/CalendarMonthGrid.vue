<script setup lang="ts">
import { entryLevel, weekSegments, type CalendarDay, type CalendarLevel } from '~/utils/calendar'

// Month view for large screens (Q-P3-4): weeks Monday → Sunday; each gameday is one lightly coloured bar spread
// over as many days as it lasts, wrapping onto the following week when needed. The agenda stays the reference
// view (mobile, screen readers).
const props = defineProps<{ weeks: CalendarDay[][], caption: string }>()
const language = useLanguageTag()
const MONDAY = '2024-01-01'
const DAY_MS = 86_400_000
const weekdays = computed(() => Array.from({ length: 7 }, (_, index) =>
  new Intl.DateTimeFormat(language.value, { weekday: 'short', timeZone: 'UTC' }).format(new Date(Date.parse(MONDAY) + index * DAY_MS))))
const dayNumber = (date: string) => Number(date.slice(8))

// Light background by level, with a darker edge on the side where the bar starts.
const LEVEL_STYLE: Record<CalendarLevel, string> = {
  INT: 'bg-kpi-gold-100 border-kpi-gold-500',
  NAT: 'bg-kpi-blue-100 border-kpi-blue-500',
  REG: 'bg-kpi-green-100 border-kpi-green-600',
}
const DEFAULT_LEVEL: CalendarLevel = 'NAT'
const NUMBER_ROW = '1.5rem'
const MIN_LANE_HEIGHT = '1.75rem'

const rows = computed(() => props.weeks.map((week) => {
  const segments = weekSegments(week)
  const lanes = Math.max(1, ...segments.map(segment => segment.lane + 1))
  return { week, segments, lanes }
}))
</script>

<template>
  <div role="group" :aria-label="caption" class="text-sm" data-testid="calendar-grid">
    <div class="grid grid-cols-7" aria-hidden="true">
      <span v-for="weekday in weekdays" :key="weekday" class="p-1 font-semibold text-ink/70">{{ weekday }}</span>
    </div>
    <div
      v-for="(row, index) in rows"
      :key="index"
      class="grid grid-cols-7"
      :style="{ gridTemplateRows: `${NUMBER_ROW} repeat(${row.lanes}, minmax(${MIN_LANE_HEIGHT}, auto))` }"
      data-testid="calendar-week"
    >
      <!-- Day cells: borders and numbers, behind the bars. -->
      <div
        v-for="(day, column) in row.week"
        :key="day.date"
        class="border border-line px-1 pt-1"
        :class="day.inMonth ? '' : 'bg-kpi-blue-50/50 text-ink/50'"
        :style="{ gridColumn: column + 1, gridRow: `1 / span ${row.lanes + 1}` }"
        :data-date="day.date"
      >
        <time :datetime="day.date" class="text-xs font-semibold">{{ dayNumber(day.date) }}</time>
      </div>
      <div
        v-for="segment in row.segments"
        :key="segment.entry.id"
        class="z-10 mx-0.5 my-0.5 px-1.5 py-0.5"
        :class="[
          LEVEL_STYLE[entryLevel(segment.entry) ?? DEFAULT_LEVEL],
          segment.continuesBefore ? '' : 'rounded-l border-l-4',
          segment.continuesAfter ? '' : 'rounded-r',
        ]"
        :style="{ gridColumn: `${segment.column + 1} / span ${segment.span}`, gridRow: segment.lane + 2 }"
        :data-gameday="segment.entry.id"
        data-testid="calendar-bar"
      >
        <CalendarEntryLinks :entry="segment.entry" compact />
      </div>
    </div>
  </div>
</template>
