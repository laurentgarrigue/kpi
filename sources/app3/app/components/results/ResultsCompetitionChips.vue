<script setup lang="ts">
// Switch between competitions (siblings of a group or of an event), keeping the current tab (CMP-03, EVT-03).
// `all.disabled`: « All » makes no sense on this tab (it needs one competition): greyed out, not a link.
defineProps<{ items: { code: string, label: string, to: string, current: boolean }[], all?: { to: string, current: boolean, disabled?: boolean } }>()
</script>

<template>
  <nav :aria-label="$t('results.competitions')" data-testid="competition-chips">
    <ul class="flex flex-wrap gap-2">
      <li v-if="all">
        <span
          v-if="all.disabled"
          aria-disabled="true"
          class="inline-block cursor-not-allowed rounded-full border border-line px-3 py-1 text-sm text-ink/40"
          data-testid="chip-all"
        >
          {{ $t('results.allCompetitions') }}
        </span>
        <NuxtLink
          v-else
          :to="all.to"
          :aria-current="all.current ? 'page' : undefined"
          class="inline-block rounded-full border px-3 py-1 text-sm"
          :class="all.current ? 'border-kpi-blue-600 bg-kpi-blue-600 text-white' : 'border-line hover:bg-kpi-blue-50'"
          data-testid="chip-all"
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
