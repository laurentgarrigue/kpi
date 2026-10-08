<script setup lang="ts">
import { eventLogoUrl, eventUrl, formatEventDates, isOngoing, type PublicEvent } from '~/utils/events'

// One event, linking to its app2 page in a new tab until app3 has event pages (PAGE_HOME.md § 2, HOME-04).
const props = defineProps<{ event: PublicEvent, today: string }>()
const { legacyBaseUrl, app2BaseUrl } = useRuntimeConfig().public
const { locale, locales } = useI18n()

const href = computed(() => eventUrl(props.event.id, app2BaseUrl))
const logo = computed(() => eventLogoUrl(props.event.logo, legacyBaseUrl))
const language = computed(() => locales.value.find(item => item.code === locale.value)?.language ?? locale.value)
const dates = computed(() => formatEventDates(props.event.start, props.event.end, language.value))
const ongoing = computed(() => isOngoing(props.event, props.today))
</script>

<template>
  <SiteExternalLink
    :href="href"
    new-tab
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
  </SiteExternalLink>
</template>
