<script setup lang="ts">
import { eventLogoUrl, eventUrl, type PublicEvent } from '~/utils/events'

// One recent event, linking to its app2 page until app3 has event pages (PAGE_HOME.md § 2, HOME-04).
const props = defineProps<{ event: PublicEvent }>()
const { legacyBaseUrl, app2BaseUrl } = useRuntimeConfig().public

const href = computed(() => eventUrl(props.event.id, app2BaseUrl))
const logo = computed(() => eventLogoUrl(props.event.logo, legacyBaseUrl))
</script>

<template>
  <a :href="href" class="flex h-full items-center gap-4 rounded border border-line p-4 hover:border-kpi-blue-500 hover:bg-kpi-blue-50">
    <img v-if="logo" :src="logo" :alt="event.libelle" loading="lazy" class="size-16 shrink-0 object-contain">
    <span class="flex flex-col">
      <span class="font-semibold text-kpi-blue-600">{{ event.libelle }}</span>
      <span class="text-sm">{{ event.place }} · <span class="tabular-nums">{{ event.year }}</span></span>
    </span>
  </a>
</template>
