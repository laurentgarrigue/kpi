<script setup lang="ts">
/** Icon of an equipment status (PAGE_SCRUTINEERING.md § 3.2): 0 not checked, 1 valid, 2 / 3 / 4 defects. */
const props = defineProps<{ status: number, size?: string }>()

const ICONS = [
  'heroicons:clipboard',
  'heroicons:check-circle-solid',
  'heroicons:exclamation-circle-solid',
  'heroicons:exclamation-triangle-solid',
  'heroicons:shield-exclamation-solid',
] as const
const VALID = 1

const icon = computed(() => ICONS[props.status] ?? ICONS[0])
const colorClass = computed(() => {
  if (props.status === VALID) return 'text-success-600 dark:text-success-400'
  if (props.status > VALID) return 'text-danger-600 dark:text-danger-400'
  return 'text-header-400 dark:text-header-500'
})
</script>

<template>
  <UIcon :name="icon" :class="[size ?? 'w-8 h-8', colorClass]" />
</template>
