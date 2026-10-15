<script setup lang="ts">
import { fixtureKey, type Fixture } from '~/utils/fixtures'

// One titled section of fixture cards, or its empty message (PAGE_HOME.md § 2).
defineProps<{ id: string, title: string, empty: string, fixtures: Fixture[], today: string }>()
</script>

<template>
  <section :aria-labelledby="`${id}-title`">
    <h2 :id="`${id}-title`" class="mb-4 text-3xl text-kpi-blue-600">{{ title }}</h2>
    <p v-if="fixtures.length === 0" :data-testid="`${id}-empty`">{{ empty }}</p>
    <ul v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" :data-testid="id">
      <li v-for="fixture in fixtures" :key="fixtureKey(fixture)">
        <HomeFixtureCard :fixture="fixture" :today="today" />
      </li>
    </ul>
  </section>
</template>
