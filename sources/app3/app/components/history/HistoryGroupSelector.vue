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
  <form method="get" :action="localePath('/history')" class="flex flex-wrap items-center gap-x-4 gap-y-2" data-testid="history-selector">
    <label class="flex w-full flex-wrap items-center gap-1.5 text-sm text-muted sm:w-auto">
      {{ $t('history.group') }}
      <select name="group" class="w-full max-w-full rounded-md sm:w-auto border border-line bg-surface px-2.5 py-1.5 text-[0.9375rem] text-ink" @change="apply">
        <optgroup v-for="section in sections" :key="section.section" :label="$t(`results.section.${section.section}`)">
          <option v-for="item in section.groups" :key="item.code" :value="item.code" :selected="item.code === group">{{ groupLabel(item, locale) }}</option>
        </optgroup>
      </select>
    </label>
    <UButton type="submit" variant="outline">{{ $t('common.show') }}</UButton>
  </form>
</template>
