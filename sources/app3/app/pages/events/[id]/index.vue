<script setup lang="ts">
// /events/{id} → /events/{id}/competitions (EVT-01).
defineOptions({ inheritAttrs: false })
const route = useRoute()
const localePath = useLocalePath()
await navigateTo(localePath(`/events/${route.params.id}/competitions`), { redirectCode: 302 })
</script>

<template>
  <div />
</template>
