<script setup lang="ts">
import { defaultGroup } from '~/utils/competitions'
import type { HistoryGroups } from '~/utils/history'

// /history → /history/{group} (HIS-01): the requested group (`?group=`, target of the selector without
// JavaScript) if it has honours, otherwise the first national group.
const route = useRoute()
const localePath = useLocalePath()
const { data } = await useApiResource<HistoryGroups>('/history')
if (!data.value) {
  throw createError({ statusCode: 503, fatal: true })
}
const group = defaultGroup(data.value.sections, typeof route.query.group === 'string' ? route.query.group : undefined)
if (!group) {
  throw createError({ statusCode: 404, fatal: true })
}
await navigateTo(localePath(`/history/${group}`), { redirectCode: 302 })
</script>

<template>
  <div />
</template>
