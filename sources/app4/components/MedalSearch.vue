<template>
  <label class="relative flex min-w-64 flex-1 items-center sm:max-w-md">
    <span class="sr-only">{{ placeholder }}</span>
    <UIcon name="i-heroicons-magnifying-glass" class="pointer-events-none absolute left-3 size-5 text-header-400" />
    <input
      v-model="model"
      type="search"
      class="w-full rounded-lg border border-header-300 bg-white py-2 pr-3 pl-10 text-base placeholder:text-header-400 focus-visible:outline-2 focus-visible:outline-primary-600 dark:border-header-600 dark:bg-header-800"
      :placeholder="placeholder"
      autocomplete="off"
    >
    <span v-if="model.trim() !== ''" class="absolute right-10 text-sm text-header-500 tabular-nums" aria-live="polite">{{ count }} / {{ total }}</span>
  </label>
</template>

<script setup lang="ts">
// Front-end search of a tab of the medals page: filters what is already loaded, nothing is sent to api2.
const model = defineModel<string>({ required: true })
defineProps<{ placeholder: string, count: number, total: number }>()
</script>
