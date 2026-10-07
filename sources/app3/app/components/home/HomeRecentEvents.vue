<script setup lang="ts">
import { latestEvents, type PublicEvent } from '~/utils/events'

// "Recent events" block: the page still renders when api2 fails (PAGE_HOME.md § 2, HOME-03/05).
const RECENT_EVENTS_LIMIT = 6

const api2 = useApi2()
const { data, error } = await useAsyncData('home-recent-events', () => api2<PublicEvent[]>('/events/all'))
const events = computed(() => latestEvents(data.value ?? [], RECENT_EVENTS_LIMIT))
</script>

<template>
  <section aria-labelledby="recent-events-title">
    <h2 id="recent-events-title" class="mb-4 text-3xl text-kpi-blue-600">{{ $t('home.recentEvents') }}</h2>
    <p v-if="error" class="text-kpi-red-600" data-testid="events-unavailable">{{ $t('home.eventsUnavailable') }}</p>
    <p v-else-if="events.length === 0" data-testid="events-empty">{{ $t('home.noEvents') }}</p>
    <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" data-testid="events-list">
      <li v-for="event in events" :key="event.id">
        <HomeEventCard :event="event" />
      </li>
    </ul>
  </section>
</template>
