<script setup lang="ts">
import { formatEventDates } from '~/utils/events'
import type { CalendarEntry } from '~/utils/calendar'

// Agenda: gamedays grouped by start date, an ordered list per date (PAGE_CALENDAR.md § 2.4 and § 4).
defineProps<{ days: { date: string, entries: CalendarEntry[] }[] }>()
const language = useLanguageTag()
const longDate = (date: string) => new Intl.DateTimeFormat(language.value, { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(date))
</script>

<template>
  <div class="space-y-6" data-testid="calendar-agenda">
    <section v-for="day in days" :key="day.date" :aria-labelledby="`day-${day.date}`" data-testid="calendar-day">
      <h2 :id="`day-${day.date}`" class="mb-2 border-b border-line text-2xl text-kpi-blue-600">
        <time :datetime="day.date">{{ longDate(day.date) }}</time>
      </h2>
      <ol class="space-y-3">
        <li v-for="entry in day.entries" :key="entry.id" class="flex flex-col gap-1 sm:flex-row sm:gap-4" :data-gameday="entry.id">
          <time :datetime="entry.start" class="w-40 shrink-0 text-sm tabular-nums">{{ formatEventDates(entry.start, entry.end, language) }}</time>
          <CalendarEntryLinks :entry="entry" />
        </li>
      </ol>
    </section>
  </div>
</template>
