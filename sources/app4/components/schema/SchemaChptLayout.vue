<script setup lang="ts">
import type { SchemaPhase } from '~/types/schema'

const props = defineProps<{
  phases: SchemaPhase[]
  hoveredTeam: string | null
}>()

const emit = defineEmits<{
  hoverTeam: [team: string | null]
}>()

// Sort by dateDebut (chronological) then gameday name
const sortedPhases = computed(() => {
  return [...props.phases].sort((a, b) => {
    const da = a.dateDebut ?? ''
    const db = b.dateDebut ?? ''
    if (da !== db) return da.localeCompare(db)
    const na = a.nom || a.phase || ''
    const nb = b.nom || b.phase || ''
    return na.localeCompare(nb)
  })
})
</script>

<template>
  <div class="space-y-4">
    <SchemaChptGameday
      v-for="phase in sortedPhases"
      :key="phase.idJournee"
      :phase="phase"
      :hovered-team="hoveredTeam"
      @hover-team="emit('hoverTeam', $event)"
    />
  </div>
</template>
