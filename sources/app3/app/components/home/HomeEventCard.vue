<script setup lang="ts">
import { eventLogoUrl, formatEventDates, isOngoing, type PublicEvent } from '~/utils/events'

// One event, linking to its event page (PAGE_HOME.md § 2, HOME-04).
const props = defineProps<{ event: PublicEvent, today: string }>()
const { legacyBaseUrl } = useRuntimeConfig().public
const localePath = useLocalePath()

const href = computed(() => localePath(`/events/${props.event.id}`))
const logo = computed(() => eventLogoUrl(props.event.logo, legacyBaseUrl))
const language = useLanguageTag()
const dates = computed(() => formatEventDates(props.event.start, props.event.end, language.value))
const ongoing = computed(() => isOngoing(props.event, props.today))
</script>

<template>
  <NuxtLink
    :to="href"
    class="flex h-full items-center gap-4 rounded border border-line p-4 hover:border-kpi-blue-500 hover:bg-kpi-blue-50"
  >
    <img v-if="logo" :src="logo" :alt="event.libelle" loading="lazy" class="size-16 shrink-0 object-contain">
    <span class="flex flex-col gap-1">
      <span class="font-semibold text-kpi-blue-600">{{ event.libelle }}</span>
      <span class="text-sm">{{ event.place }} · <span class="tabular-nums">{{ dates }}</span></span>
      <UBadge v-if="ongoing" color="error" variant="subtle" class="self-start" data-testid="ongoing-badge">
        {{ $t('home.ongoing') }}
      </UBadge>
    </span>
  </NuxtLink>
</template>
