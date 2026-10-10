<script setup lang="ts">
import { eventsOfYear, eventYears, todayInParis, type PublicEvent } from '~/utils/events'

// Events of a year (PAGE_EVENTS.md, EVL-*): the page behind the « Events » menu entry. An event is a place and a
// weekend; its competitions are reached from the event page.
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()
const today = todayInParis(new Date())

const { data: events, error } = await useApiResource<PublicEvent[]>('/events/all', { notFoundIsError: false })
const years = computed(() => eventYears(events.value ?? []))
// Requested year when it has events, otherwise the current one, otherwise the most recent one.
const year = computed(() => {
  const requested = Number(route.query.year)
  const current = Number(today.slice(0, 4))
  return [requested, current].find(candidate => years.value.includes(candidate)) ?? years.value[0] ?? current
})
const list = computed(() => eventsOfYear(events.value ?? [], year.value))
const yearLink = (target: number) => ({ path: localePath('/events'), query: { year: target } })

useSeoMeta({
  title: () => t('events.pageTitle', { year: year.value }),
  description: () => t('events.description', { year: year.value }),
})
</script>

<template>
  <div class="space-y-6">
    <h1 class="text-5xl text-kpi-blue-600">{{ $t('events.title') }}</h1>
    <p class="max-w-3xl">{{ $t('events.intro') }}</p>
    <p v-if="error" class="text-kpi-red-600" data-testid="events-unavailable">{{ $t('common.unavailable') }}</p>
    <template v-else>
      <nav :aria-label="$t('events.year')" class="flex flex-wrap gap-2" data-testid="events-years">
        <NuxtLink
          v-for="item in years"
          :key="item"
          :to="yearLink(item)"
          :aria-current="item === year ? 'page' : undefined"
          class="rounded-full border px-3 py-1 text-sm"
          :class="item === year ? 'border-kpi-blue-600 bg-kpi-blue-600 text-white' : 'border-line hover:bg-kpi-blue-50'"
        >
          {{ item }}
        </NuxtLink>
      </nav>
      <HomeEventList id="year-events" :title="String(year)" :empty="$t('events.empty', { year })" :events="list" :today="today" />
    </template>
  </div>
</template>
