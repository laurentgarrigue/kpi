<script setup lang="ts">
import type { PublicEvent } from '~/utils/events'

// One titled section of event cards, or its empty message (PAGE_HOME.md § 2).
defineProps<{ id: string, title: string, empty: string, events: PublicEvent[], today: string }>()
</script>

<template>
  <section :aria-labelledby="`${id}-title`">
    <h2 :id="`${id}-title`" class="mb-4 text-3xl text-kpi-blue-600">{{ title }}</h2>
    <p v-if="events.length === 0" :data-testid="`${id}-empty`">{{ empty }}</p>
    <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" :data-testid="id">
      <li v-for="event in events" :key="event.id">
        <HomeEventCard :event="event" :today="today" />
      </li>
    </ul>
  </section>
</template>
