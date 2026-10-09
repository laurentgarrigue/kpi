<script setup lang="ts">
// /groups/{season}/{code} → /groups/{season}/{code}/games (GRP-01).
defineOptions({ inheritAttrs: false })
const route = useRoute()
const localePath = useLocalePath()
await navigateTo(localePath(`/groups/${route.params.season}/${route.params.code}/games`), { redirectCode: 302 })
</script>

<template>
  <div />
</template>
