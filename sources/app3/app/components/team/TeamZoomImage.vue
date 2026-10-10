<script setup lang="ts">
// Small team image (club logo, colours) enlarged on hover and on keyboard focus, so that it can be read like the
// full-width team photo (PAGE_TEAM.md § 2.2, TEA-06). Motion is skipped when the visitor prefers reduced motion.
defineProps<{ src: string, alt: string, origin: 'left' | 'right' }>()
</script>

<template>
  <img
    :src="src"
    :alt="alt"
    tabindex="0"
    class="relative cursor-zoom-in rounded bg-white transition-transform duration-200 hover:z-30 hover:scale-300 hover:shadow-lg focus-visible:z-30 focus-visible:scale-300 focus-visible:shadow-lg motion-reduce:transition-none"
    :class="origin === 'left' ? 'origin-top-left' : 'origin-top-right'"
    data-zoom
  >
</template>
