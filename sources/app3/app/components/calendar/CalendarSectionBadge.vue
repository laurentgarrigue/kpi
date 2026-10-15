<script setup lang="ts">
import { SECTION_STYLES, type CalendarSection } from '~/utils/calendar'

// Section of a competition (International, National…): colour AND text (PAGE_CALENDAR.md § 2.4, CAL-02).
defineProps<{ section: CalendarSection }>()
</script>

<template>
  <span class="rounded px-1.5 py-0.5 text-xs font-semibold" :class="SECTION_STYLES[section].badge" :data-section="section">{{ $t(`calendar.sections.${section}`) }}</span>
</template>
