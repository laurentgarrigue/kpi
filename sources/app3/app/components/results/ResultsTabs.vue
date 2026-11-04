<script setup lang="ts">
// Tabs as plain links: no JavaScript needed, current tab marked; scrolls horizontally on small screens (CMP-04).
defineProps<{ tabs: { id: string, to: string }[], current: string }>()
</script>

<template>
  <nav :aria-label="$t('results.tabs')" class="overflow-x-auto border-b border-line [scrollbar-width:none]" data-testid="results-tabs">
    <ul class="flex min-w-max gap-1">
      <li v-for="tab in tabs" :key="tab.id">
        <NuxtLink
          :to="tab.to"
          :aria-current="tab.id === current ? 'page' : undefined"
          class="block border-b-3 px-3.5 py-3 font-semibold"
          :class="tab.id === current ? 'border-navy text-ink dark:border-kpi-blue-500' : 'border-transparent text-muted hover:text-ink'"
        >
          {{ $t(`results.tab.${tab.id}`) }}
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>
