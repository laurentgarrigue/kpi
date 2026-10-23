<script setup lang="ts">
// The club is shown on the map of the « Clubs and teams » page (PAGE_CLUBS.md § 2.2, CLB-08): 301.
defineOptions({ inheritAttrs: false })
const route = useRoute()
const localePath = useLocalePath()
await navigateTo({ path: localePath('/clubs'), query: { club: String(route.params.code) } }, { redirectCode: 301 })
</script>

<template>
  <div />
</template>
