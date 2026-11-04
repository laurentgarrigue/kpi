<script setup lang="ts">
// Page heading of the redesign (DOC/specs/public/maquettes/maquette.css `.page-head`): a large Agency FB title, an
// optional muted introduction.
defineProps<{ title: string, intro?: string }>()
</script>

<template>
  <div class="pt-2 pb-2">
    <h1 class="text-[2.75rem] leading-none text-navy md:text-[3.5rem] dark:text-ink">{{ title }}</h1>
    <p v-if="intro" class="mt-2 max-w-[65ch] text-muted">{{ intro }}</p>
    <slot />
  </div>
</template>
