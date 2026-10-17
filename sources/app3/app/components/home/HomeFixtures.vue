<script setup lang="ts">
import type { HomeFixtures } from '~/utils/fixtures'

// « Coming up » then « Recently »: events and group days outside any event (PAGE_HOME.md § 2, HOME-03/05/07/09).
// api2 splits them on its own « today » and sends it with the lists: the browser never recomputes it at hydration.
const api2 = useApi2()
const { data, error } = await useAsyncData('home-fixtures', () => api2<HomeFixtures>('/fixtures/site'))
</script>

<template>
  <p v-if="error || !data" class="text-kpi-red-600" data-testid="fixtures-unavailable">{{ $t('home.fixturesUnavailable') }}</p>
  <div v-else class="space-y-12">
    <HomeFixtureList
      id="upcoming-fixtures"
      :title="$t('home.upcomingFixtures')"
      :empty="$t('home.noUpcomingFixtures')"
      :fixtures="data.upcoming"
      :today="data.today"
    />
    <HomeFixtureList
      id="recent-fixtures"
      :title="$t('home.recentFixtures')"
      :empty="$t('home.noRecentFixtures')"
      :fixtures="data.recent"
      :today="data.today"
    />
  </div>
</template>
