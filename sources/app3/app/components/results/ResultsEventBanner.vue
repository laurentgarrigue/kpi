<script setup lang="ts">
import { joinURL } from 'ufo'
import type { LinkedEvent } from '#kpi-layer/utils/results/types'
import { formatEventDates } from '~/utils/events'

// Direct access to the event of a competition or a group (CPL-11, CMP-16, GRP-05): the main event is put
// forward, the other linked events are listed discreetly.
const MAX_OTHER_EVENTS = 5
const props = defineProps<{ events: LinkedEvent[], main: LinkedEvent | null, scope: string }>()
const localePath = useLocalePath()
const { legacyBaseUrl } = useRuntimeConfig().public
const language = useLanguageTag()

const others = computed(() => props.events.filter(event => event.id !== props.main?.id).slice(0, MAX_OTHER_EVENTS))
const logo = computed(() => (props.main?.logo ? joinURL(legacyBaseUrl, 'img', props.main.logo) : null))
</script>

<template>
  <div v-if="main || others.length" class="space-y-2">
    <aside v-if="main" class="flex flex-wrap items-center gap-4 rounded border-l-4 border-kpi-blue-500 bg-kpi-blue-50 p-4" data-testid="main-event">
      <img v-if="logo" :src="logo" alt="" class="size-12 object-contain">
      <p class="flex-1">
        {{ $t('results.partOfEvent', { scope }) }}
        <strong>{{ main.libelle }}</strong>
        <span class="text-sm"> ({{ [main.place, formatEventDates(main.start, main.end, language)].filter(Boolean).join(', ') }})</span>
      </p>
      <UButton :to="localePath(`/events/${main.id}`)" data-testid="main-event-link">{{ $t('results.seeEvent') }}</UButton>
    </aside>
    <p v-if="others.length" class="text-sm" data-testid="other-events">
      {{ $t(main ? 'results.alsoInEvents' : 'results.events') }}
      <template v-for="(event, index) in others" :key="event.id">
        <NuxtLink :to="localePath(`/events/${event.id}`)" class="underline">{{ event.libelle }}</NuxtLink><template v-if="index < others.length - 1">, </template>
      </template>
    </p>
  </div>
</template>
