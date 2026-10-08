<script setup lang="ts">
// Tabs as plain links: no JavaScript needed, current tab marked; scrolls horizontally on small screens (CMP-04).
defineProps<{ tabs: { id: string, to: string }[], current: string }>()
</script>

<template>
  <nav :aria-label="$t('results.tabs')" class="overflow-x-auto border-b border-line" data-testid="results-tabs">
    <ul class="flex min-w-max">
      <li v-for="tab in tabs" :key="tab.id">
        <NuxtLink
          :to="tab.to"
          :aria-current="tab.id === current ? 'page' : undefined"
          class="block border-b-2 px-4 py-2 font-semibold"
          :class="tab.id === current ? 'border-kpi-blue-600 text-kpi-blue-600' : 'border-transparent hover:border-line'"
        >
          {{ $t(`results.tab.${tab.id}`) }}
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>
