<script setup lang="ts">
import type { GroupSection } from '#kpi-layer/utils/results/types'
import { groupLabel } from '~/utils/competitions'

// Group selection: a GET form to /history (which redirects), applied on change with JavaScript (HIS-01).
defineProps<{ sections: GroupSection[], group: string }>()
const { locale } = useI18n()
const localePath = useLocalePath()

function apply(event: Event) {
  const value = (event.target as HTMLSelectElement).value
  navigateTo(localePath(`/history/${value}`))
}
</script>

<template>
  <form method="get" :action="localePath('/history')" class="flex flex-wrap items-end gap-4" data-testid="history-selector">
    <label class="flex flex-col text-sm">
      {{ $t('history.group') }}
      <select name="group" class="rounded border border-line px-2 py-1" @change="apply">
        <optgroup v-for="section in sections" :key="section.section" :label="$t(`results.section.${section.section}`)">
          <option v-for="item in section.groups" :key="item.code" :value="item.code" :selected="item.code === group">{{ groupLabel(item, locale) }}</option>
        </optgroup>
      </select>
    </label>
    <UButton type="submit" variant="outline">{{ $t('common.show') }}</UButton>
  </form>
</template>
