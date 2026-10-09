<script setup lang="ts">
import { entryLevel, type CalendarDay } from '~/utils/calendar'

// Month view for large screens (Q-P3-4): a table Monday → Sunday; a gameday spanning several days appears on
// each of them, in full on its first day and on Mondays. The agenda stays the reference view (mobile, screen readers).
defineProps<{ weeks: CalendarDay[][], caption: string }>()
const language = useLanguageTag()
const MONDAY = '2024-01-01'
const weekdays = computed(() => Array.from({ length: 7 }, (_, index) =>
  new Intl.DateTimeFormat(language.value, { weekday: 'short', timeZone: 'UTC' }).format(new Date(Date.parse(MONDAY) + index * 86_400_000))))
const dayNumber = (date: string) => Number(date.slice(8))
const LEVEL_BORDER = { INT: 'border-kpi-gold-500', NAT: 'border-kpi-blue-500', REG: 'border-kpi-green-600' } as const
</script>

<template>
  <table class="w-full table-fixed border-collapse text-sm" data-testid="calendar-grid">
    <caption class="sr-only">{{ caption }}</caption>
    <thead>
      <tr>
        <th v-for="weekday in weekdays" :key="weekday" scope="col" class="p-1 text-left font-semibold text-ink/70">{{ weekday }}</th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="(week, index) in weeks" :key="index">
        <td v-for="(day, weekday) in week" :key="day.date" class="h-28 border border-line p-1 align-top" :class="day.inMonth ? '' : 'bg-kpi-blue-50/50 text-ink/50'" :data-date="day.date">
          <time :datetime="day.date" class="text-xs font-semibold">{{ dayNumber(day.date) }}</time>
          <ul class="mt-1 space-y-1">
            <li v-for="entry in day.entries" :key="entry.id" class="border-l-4 pl-1" :class="LEVEL_BORDER[entryLevel(entry) ?? 'NAT']">
              <CalendarEntryLinks v-if="entry.start === day.date || weekday === 0" :entry="entry" compact />
              <span v-else class="text-xs text-ink/70">… {{ $t('calendar.continued') }}</span>
            </li>
          </ul>
        </td>
      </tr>
    </tbody>
  </table>
</template>
