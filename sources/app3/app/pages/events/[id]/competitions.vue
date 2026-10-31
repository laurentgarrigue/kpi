<script setup lang="ts">
import type { EventCompetition, Ranking } from '#kpi-layer/utils/results/types'

// « Competitions » tab of the event view (PAGE_EVENT_GROUP.md § 2.3, EVT-11): one row per competition with its
// podium; the other attributes of the parent page are for the other tabs.
defineOptions({ inheritAttrs: false })
const props = defineProps<{ eventId: number, competitions: EventCompetition[], singleGroup: boolean }>()
const api2 = useApi2()

// One ranking per competition; one that cannot be read leaves its row without podium.
const { data: rankings } = await useAsyncData(
  () => `api2:/event/${props.eventId}/rankings`,
  async () => {
    const results = await Promise.allSettled(props.competitions.map(item => api2<Ranking>(`/competition/${item.season}/${item.code}/ranking`)))
    return Object.fromEntries(results.map((result, index) => [props.competitions[index]!.code, result.status === 'fulfilled' ? result.value : null]))
  },
)
</script>

<template>
  <ResultsEventCompetitions :event-id="eventId" :competitions="competitions" :single-group="singleGroup" :rankings="rankings ?? {}" />
</template>
