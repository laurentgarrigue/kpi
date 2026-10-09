<script setup lang="ts">
import { entryLevel, gamedayCompetitionPath, type CalendarEntry } from '~/utils/calendar'

// One gameday: competition (link), gameday, place, level, and its event when there is one (CAL-02, CAL-03).
defineProps<{ entry: CalendarEntry, compact?: boolean }>()
const localePath = useLocalePath()
</script>

<template>
  <span class="flex flex-col gap-0.5">
    <span class="flex flex-wrap items-center gap-2">
      <NuxtLink :to="localePath(gamedayCompetitionPath(entry))" class="font-semibold text-kpi-blue-600 hover:underline" data-testid="calendar-competition">
        {{ entry.competition.display_title }}
      </NuxtLink>
      <CalendarLevelBadge v-if="!compact" :level="entryLevel(entry)" />
    </span>
    <span v-if="!compact" class="text-sm">
      {{ entry.name }}<template v-if="entry.place"> · {{ entry.place }}<template v-if="entry.department"> ({{ entry.department }})</template></template>
    </span>
    <NuxtLink v-if="entry.event && !compact" :to="localePath(`/events/${entry.event.id}`)" class="text-sm underline" data-testid="calendar-event">
      {{ $t('calendar.event', { event: entry.event.libelle }) }}
    </NuxtLink>
  </span>
</template>
