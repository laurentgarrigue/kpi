<template>
  <section :aria-labelledby="`${id}-title`">
    <h2 :id="`${id}-title`" class="mb-3 flex items-center gap-2 text-lg font-semibold text-gray-800">
      <UIcon :name="icon" class="h-5 w-5 shrink-0 text-blue-600" />
      {{ title }}
    </h2>
    <slot />
  </section>
</template>

<script setup>
// Titled section of the home page: one heading style for every block (PAGE_HOME.md § 7).
defineProps({
  id: { type: String, required: true },
  title: { type: String, required: true },
  icon: { type: String, required: true },
})
</script>
