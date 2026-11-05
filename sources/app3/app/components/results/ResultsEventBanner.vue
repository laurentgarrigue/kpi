<script setup lang="ts">
import type { LinkedEvent } from '#kpi-layer/utils/results/types'
import { dateTile, formatEventDates } from '~/utils/events'

// Direct access to the event of a competition or a group (CPL-11, CMP-16, CMP-18, GRP-05): the main event is one
// clickable card inviting to its other competitions, its pitches and the live; the other linked events are listed
// discreetly. `competitionCount`: number of competitions of the main event, when known.
const MAX_OTHER_EVENTS = 5
const props = defineProps<{ events: LinkedEvent[], main: LinkedEvent | null, scope: string, competitionCount?: number }>()
const localePath = useLocalePath()
const language = useLanguageTag()

const others = computed(() => props.events.filter(event => event.id !== props.main?.id).slice(0, MAX_OTHER_EVENTS))
const tile = computed(() => (props.main ? dateTile(props.main.start, props.main.end, language.value) : null))
const where = computed(() => (props.main
  ? [props.main.place, formatEventDates(props.main.start, props.main.end, language.value)].filter(Boolean).join(', ')
  : ''))
</script>

<template>
  <div v-if="main || others.length" class="space-y-2">
    <NuxtLink
      v-if="main"
      :to="localePath(`/events/${main.id}`)"
      class="group grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4.5 gap-y-3 rounded-lg border border-navy/20 bg-wash px-4 py-3.5 text-ink md:grid-cols-[auto_minmax(0,1fr)_auto] dark:border-line"
      data-testid="main-event"
    >
      <span v-if="tile" class="grid size-16 place-content-center rounded-md bg-navy text-center font-display text-[1.375rem] leading-[0.95] text-white" aria-hidden="true" data-testid="event-tile">
        {{ tile.days }}<small class="block text-sm text-sky">{{ tile.month }}</small>
      </span>
      <span :class="{ 'col-span-2 md:col-span-1': !tile }">
        <span class="sr-only">{{ $t('results.partOfEvent', { scope }) }} </span>
        <strong class="block text-[1.0625rem]">{{ main.libelle }}</strong>
        <span class="text-sm text-muted">
          <template v-if="where">{{ where }}. </template>
          {{ competitionCount && competitionCount > 1 ? $t('results.eventCta.many', { n: competitionCount }) : $t('results.eventCta.one') }}
        </span>
      </span>
      <span class="col-span-2 inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md bg-navy px-4 py-2.5 font-semibold text-white group-hover:bg-kpi-blue-700 md:col-span-1 dark:group-hover:bg-kpi-blue-900">
        {{ $t('results.seeEvent') }}
        <UIcon name="i-heroicons-arrow-right" class="size-4.5" aria-hidden="true" />
      </span>
    </NuxtLink>
    <p v-if="others.length" class="text-sm" data-testid="other-events">
      {{ $t(main ? 'results.alsoInEvents' : 'results.events') }}
      <template v-for="(event, index) in others" :key="event.id">
        <NuxtLink :to="localePath(`/events/${event.id}`)" class="underline">{{ event.libelle }}</NuxtLink><template v-if="index < others.length - 1">, </template>
      </template>
    </p>
  </div>
</template>
