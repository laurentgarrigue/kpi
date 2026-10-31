<script setup lang="ts">
import type { GroupSection } from '#kpi-layer/utils/results/types'
import { CALENDAR_SECTIONS, groupsOfSection, type CalendarFilters, type CalendarView } from '~/utils/calendar'
import { groupLabel } from '~/utils/competitions'

// Section and group filters: a GET form to /calendar keeping the month, usable without JavaScript (CAL-04). The
// groups offered are those of the chosen section; changing the section submits the form with no group (CAL-09).
const props = defineProps<{ month: string, view: CalendarView, filters: CalendarFilters, sections: GroupSection[] }>()
const { locale } = useI18n()
const localePath = useLocalePath()
const form = useTemplateRef<HTMLFormElement>('form')
const groupSections = computed(() => groupsOfSection(props.sections, props.filters.section))

function changeSection() {
  const group = form.value?.elements.namedItem('group')
  if (group instanceof HTMLSelectElement) {
    group.value = ''
  }
  form.value?.requestSubmit()
}
</script>

<template>
  <form ref="form" method="get" :action="localePath('/calendar')" :aria-label="$t('calendar.filters')" class="flex flex-wrap items-end gap-x-4 gap-y-2" data-testid="calendar-filters">
    <input type="hidden" name="month" :value="month">
    <input v-if="view === 'month'" type="hidden" name="view" :value="view">
    <label class="flex flex-col text-sm">
      {{ $t('calendar.section') }}
      <select name="section" class="rounded-md border border-line bg-surface px-2.5 py-1.5" @change="changeSection">
        <option value="">{{ $t('calendar.allSections') }}</option>
        <option v-for="section in CALENDAR_SECTIONS" :key="section" :value="section" :selected="filters.section === section">{{ $t(`calendar.sections.${section}`) }}</option>
      </select>
    </label>
    <label class="flex flex-col text-sm">
      {{ $t('results.group') }}
      <select name="group" class="rounded-md border border-line bg-surface px-2.5 py-1.5" @change="form?.requestSubmit()">
        <option value="">{{ $t('common.allGroups') }}</option>
        <optgroup v-for="section in groupSections" :key="section.section" :label="$t(`calendar.sections.${section.section}`)">
          <option v-for="item in section.groups" :key="item.code" :value="item.code" :selected="filters.group === item.code">{{ groupLabel(item, locale) }}</option>
        </optgroup>
      </select>
    </label>
    <UButton type="submit" variant="outline">{{ $t('common.show') }}</UButton>
  </form>
</template>
