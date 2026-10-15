<script setup lang="ts">
import { alertRole } from '~/utils/home'

// Alert banner of the home page, shown by api2 only between its dates (PAGE_CONTENT.md § 4, CNT-06).
defineProps<{ level: 'info' | 'important', text: string }>()
</script>

<template>
  <div
    :role="alertRole(level)"
    class="flex items-start gap-2 rounded p-4"
    :class="level === 'important' ? 'bg-kpi-red-50 text-kpi-red-800' : 'bg-kpi-blue-50 text-kpi-blue-800'"
    :data-level="level"
    data-testid="home-alert"
  >
    <UIcon :name="level === 'important' ? 'i-heroicons-exclamation-triangle' : 'i-heroicons-information-circle'" class="mt-0.5 size-5 shrink-0" aria-hidden="true" />
    <p class="whitespace-pre-line">{{ text }}</p>
  </div>
</template>
