<script setup lang="ts">
import { entrySection, gamedayCompetitionPath, type CalendarEntry } from '~/utils/calendar'

// One gameday, labelled like the legacy calendar « nom - lieu (département) » (link to its competition), then its
// competition, section and event when there is one (CAL-02, CAL-03). Compact: the label alone, for the grid bars.
defineProps<{ entry: CalendarEntry, compact?: boolean }>()
const localePath = useLocalePath()
</script>

<template>
  <span class="flex flex-col gap-0.5">
    <NuxtLink :to="localePath(gamedayCompetitionPath(entry))" class="font-semibold text-kpi-blue-600 hover:underline" data-testid="calendar-label">{{ entry.label }}</NuxtLink>
    <span v-if="!compact" class="flex flex-wrap items-center gap-2 text-sm">
      <CalendarSectionBadge :section="entrySection(entry)" />
      <NuxtLink :to="localePath(gamedayCompetitionPath(entry))" class="hover:underline" data-testid="calendar-competition">{{ entry.competition.display_title }}</NuxtLink>
    </span>
    <NuxtLink v-if="entry.event && !compact" :to="localePath(`/events/${entry.event.id}`)" class="text-sm underline" data-testid="calendar-event">
      {{ $t('calendar.event', { event: entry.event.libelle }) }}
    </NuxtLink>
  </span>
</template>
