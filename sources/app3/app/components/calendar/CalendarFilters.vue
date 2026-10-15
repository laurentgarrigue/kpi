<script setup lang="ts">
import type { GroupSection } from '#kpi-layer/utils/results/types'
import { CALENDAR_SECTIONS, groupsOfSection, type CalendarFilters } from '~/utils/calendar'
import { groupLabel } from '~/utils/competitions'

// Section and group filters: a GET form to /calendar keeping the month, usable without JavaScript (CAL-04). The
// groups offered are those of the chosen section; changing the section submits the form with no group (CAL-09).
const props = defineProps<{ month: string, filters: CalendarFilters, sections: GroupSection[] }>()
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
  <form ref="form" method="get" :action="localePath('/calendar')" :aria-label="$t('calendar.filters')" class="flex flex-wrap items-end gap-4" data-testid="calendar-filters">
    <input type="hidden" name="month" :value="month">
    <label class="flex flex-col text-sm">
      {{ $t('calendar.section') }}
      <select name="section" class="rounded border border-line px-2 py-1" @change="changeSection">
        <option value="">{{ $t('calendar.allSections') }}</option>
        <option v-for="section in CALENDAR_SECTIONS" :key="section" :value="section" :selected="filters.section === section">{{ $t(`calendar.sections.${section}`) }}</option>
      </select>
    </label>
    <label class="flex flex-col text-sm">
      {{ $t('results.group') }}
      <select name="group" class="rounded border border-line px-2 py-1" @change="form?.requestSubmit()">
        <option value="">{{ $t('common.allGroups') }}</option>
        <optgroup v-for="section in groupSections" :key="section.section" :label="$t(`calendar.sections.${section.section}`)">
          <option v-for="item in section.groups" :key="item.code" :value="item.code" :selected="filters.group === item.code">{{ groupLabel(item, locale) }}</option>
        </optgroup>
      </select>
    </label>
    <UButton type="submit" variant="outline">{{ $t('common.show') }}</UButton>
  </form>
</template>
