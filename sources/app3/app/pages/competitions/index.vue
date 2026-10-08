<script setup lang="ts">
import type { Seasons } from '#kpi-layer/utils/results/types'

// /competitions → /competitions/{season}[?group=] (CPL-01). Also the target of the season / group form
// without JavaScript (CPL-08): `?season=` and `?group=` are carried over.
const route = useRoute()
const localePath = useLocalePath()
const { data } = await useApiResource<Seasons>('/seasons')
if (!data.value) {
  throw createError({ statusCode: 503, fatal: true })
}

const requested = typeof route.query.season === 'string' ? route.query.season : undefined
const season = requested && data.value.seasons.includes(requested) ? requested : (data.value.active ?? data.value.seasons[0])
if (!season) {
  throw createError({ statusCode: 404, fatal: true })
}
const group = typeof route.query.group === 'string' && route.query.group ? { group: route.query.group } : {}
await navigateTo({ path: localePath(`/competitions/${season}`), query: group }, { redirectCode: 302 })
</script>

<template>
  <div />
</template>
