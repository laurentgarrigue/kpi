<script setup lang="ts">
import type { GroupSection } from '#kpi-layer/utils/results/types'
import { CALENDAR_LEVELS, type CalendarFilters } from '~/utils/calendar'
import { groupLabel } from '~/utils/competitions'

// Level and group filters: a GET form to /calendar keeping the month, usable without JavaScript (CAL-04).
defineProps<{ month: string, filters: CalendarFilters, sections: GroupSection[] }>()
const { locale } = useI18n()
const localePath = useLocalePath()
</script>

<template>
  <form method="get" :action="localePath('/calendar')" :aria-label="$t('calendar.filters')" class="flex flex-wrap items-end gap-4" data-testid="calendar-filters">
    <input type="hidden" name="month" :value="month">
    <label class="flex flex-col text-sm">
      {{ $t('common.level_label') }}
      <select name="level" class="rounded border border-line px-2 py-1">
        <option value="">{{ $t('common.allLevels') }}</option>
        <option v-for="level in CALENDAR_LEVELS" :key="level" :value="level" :selected="filters.level === level">{{ $t(`common.level.${level}`) }}</option>
      </select>
    </label>
    <label class="flex flex-col text-sm">
      {{ $t('results.group') }}
      <select name="group" class="rounded border border-line px-2 py-1">
        <option value="">{{ $t('common.allGroups') }}</option>
        <optgroup v-for="section in sections" :key="section.section" :label="$t(`results.section.${section.section}`)">
          <option v-for="item in section.groups" :key="item.code" :value="item.code" :selected="filters.group === item.code">{{ groupLabel(item, locale) }}</option>
        </optgroup>
      </select>
    </label>
    <UButton type="submit" variant="outline">{{ $t('common.show') }}</UButton>
  </form>
</template>
