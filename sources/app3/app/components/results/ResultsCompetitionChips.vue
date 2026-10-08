<script setup lang="ts">
// Switch between competitions (siblings of a group or of an event), keeping the current tab (CMP-03, EVT-03).
defineProps<{ items: { code: string, label: string, to: string, current: boolean }[], all?: { to: string, current: boolean } }>()
</script>

<template>
  <nav :aria-label="$t('results.competitions')" data-testid="competition-chips">
    <ul class="flex flex-wrap gap-2">
      <li v-if="all">
        <NuxtLink
          :to="all.to"
          :aria-current="all.current ? 'page' : undefined"
          class="inline-block rounded-full border px-3 py-1 text-sm"
          :class="all.current ? 'border-kpi-blue-600 bg-kpi-blue-600 text-white' : 'border-line hover:bg-kpi-blue-50'"
        >
          {{ $t('results.allCompetitions') }}
        </NuxtLink>
      </li>
      <li v-for="item in items" :key="item.code">
        <NuxtLink
          :to="item.to"
          :aria-current="item.current ? 'page' : undefined"
          class="inline-block rounded-full border px-3 py-1 text-sm"
          :class="item.current ? 'border-kpi-blue-600 bg-kpi-blue-600 text-white' : 'border-line hover:bg-kpi-blue-50'"
        >
          {{ item.label }}
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>
