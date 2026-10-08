<script setup lang="ts">
import { recentEvents, todayInParis, upcomingEvents, type PublicEvent } from '~/utils/events'

// "Upcoming events" then "Recent events" (PAGE_HOME.md § 2, HOME-03/05/07). The split is computed once, on the
// server, and sent with the payload: the browser never recomputes "today" during hydration.
const EVENTS_PER_SECTION = 6

const api2 = useApi2()
const { data, error } = await useAsyncData('home-events', async () => {
  const events = await api2<PublicEvent[]>('/events/all')
  const today = todayInParis(new Date())
  return {
    today,
    upcoming: upcomingEvents(events, today, EVENTS_PER_SECTION),
    recent: recentEvents(events, today, EVENTS_PER_SECTION),
  }
})
</script>

<template>
  <p v-if="error || !data" class="text-kpi-red-600" data-testid="events-unavailable">{{ $t('home.eventsUnavailable') }}</p>
  <div v-else class="space-y-12">
    <HomeEventList
      id="upcoming-events"
      :title="$t('home.upcomingEvents')"
      :empty="$t('home.noUpcomingEvents')"
      :events="data.upcoming"
      :today="data.today"
    />
    <HomeEventList
      id="recent-events"
      :title="$t('home.recentEvents')"
      :empty="$t('home.noEvents')"
      :events="data.recent"
      :today="data.today"
    />
  </div>
</template>
