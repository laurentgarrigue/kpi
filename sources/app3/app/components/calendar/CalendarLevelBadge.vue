<script setup lang="ts">
import type { CalendarLevel } from '~/utils/calendar'

// Level of a competition: colour AND text (PAGE_CALENDAR.md § 2.4, CAL-02).
defineProps<{ level: CalendarLevel | null }>()
const CLASSES: Record<CalendarLevel, string> = {
  INT: 'bg-kpi-gold-100 text-kpi-gold-900',
  NAT: 'bg-kpi-blue-100 text-kpi-blue-800',
  REG: 'bg-kpi-green-100 text-kpi-green-800',
}
</script>

<template>
  <span v-if="level" class="rounded px-1.5 py-0.5 text-xs font-semibold" :class="CLASSES[level]" :data-level="level">{{ $t(`common.level.${level}`) }}</span>
</template>
