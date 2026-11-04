<script setup lang="ts">
// The team search is on the « Clubs and teams » page (PAGE_CLUBS.md § 2.1, PAGE_TEAM.md § 2.1): 301, query kept.
defineOptions({ inheritAttrs: false })
const route = useRoute()
const localePath = useLocalePath()
await navigateTo({ path: localePath('/clubs'), query: route.query }, { redirectCode: 301 })
</script>

<template>
  <div />
</template>
