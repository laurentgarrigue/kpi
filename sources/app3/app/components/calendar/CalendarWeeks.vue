<script setup lang="ts">
import { shortEventDates } from '~/utils/events'
import { entrySection, gamedayCompetitionPath, SECTION_STYLES, type CalendarWeek } from '~/utils/calendar'

// Calendar by week (PAGE_CALENDAR.md § 2 bis, CAL-12): one block per week, Monday → Sunday, with its real dates and
// « weekend » or « week »; one row per gameday, its section as a coloured rule AND written.
defineProps<{ weeks: CalendarWeek[] }>()
const localePath = useLocalePath()
const language = useLanguageTag()
const kind = (week: CalendarWeek) => (week.current ? (week.weekend ? 'thisWeekend' : 'thisWeek') : (week.weekend ? 'weekend' : 'week'))
</script>

<template>
  <div class="pb-10" data-testid="calendar-weeks">
    <section
      v-for="week in weeks"
      :key="week.monday"
      class="grid gap-x-6 gap-y-2 border-t border-line py-4.5 md:grid-cols-[9.375rem_minmax(0,1fr)]"
      :aria-label="`${$t(`calendar.${kind(week)}`)} ${shortEventDates(week.start, week.end, language)}`"
      data-testid="calendar-week-block"
    >
      <p class="font-display text-[1.875rem] leading-none text-kpi-blue-700">
        <time :datetime="week.start" data-testid="week-dates">{{ shortEventDates(week.start, week.end, language) }}</time>
        <small
          class="ml-2 font-sans text-[0.8125rem] font-semibold md:mt-1 md:ml-0 md:block"
          :class="week.current ? 'text-live' : 'text-muted'"
          data-testid="week-kind"
        >{{ $t(`calendar.${kind(week)}`) }}</small>
      </p>
      <ul>
        <li v-for="item in week.entries" :key="item.entry.id" class="grid grid-cols-[0.25rem_minmax(0,1fr)_auto] gap-3.5 py-2" :data-gameday="item.entry.id">
          <i class="rounded-sm" :class="SECTION_STYLES[entrySection(item.entry)].rule" aria-hidden="true" />
          <span class="min-w-0">
            <NuxtLink :to="localePath(gamedayCompetitionPath(item.entry))" class="font-semibold hover:underline" data-testid="calendar-label">{{ item.entry.label }}</NuxtLink>
            <span v-if="item.days >= 3" class="ml-1.5 text-xs font-bold text-kpi-blue-700" data-testid="entry-length">{{ $t('calendar.days', { n: item.days }) }}</span>
            <small class="block text-[0.8125rem] text-muted">
              <NuxtLink :to="localePath(gamedayCompetitionPath(item.entry))" class="hover:underline" data-testid="calendar-competition">{{ item.entry.competition.display_title }}</NuxtLink>
              <template v-if="item.ownDates">, <span data-testid="entry-dates">{{ shortEventDates(item.entry.start, item.entry.end, language) }}</span></template>
              <template v-if="item.entry.event"> · <NuxtLink :to="localePath(`/events/${item.entry.event.id}`)" class="underline" data-testid="calendar-event">{{ $t('calendar.event', { event: item.entry.event.libelle }) }}</NuxtLink></template>
            </small>
          </span>
          <CalendarSectionBadge :section="entrySection(item.entry)" class="self-start" />
        </li>
      </ul>
    </section>
  </div>
</template>
