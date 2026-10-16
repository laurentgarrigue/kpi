<script setup lang="ts">
import type { GroupSection } from '#kpi-layer/utils/results/types'
import { groupLabel } from '~/utils/competitions'

// Season and group selection: a GET form to /competitions (which redirects), applied on change with JS (CPL-08).
const props = defineProps<{ seasons: string[], season: string, sections: GroupSection[], group: string | null }>()
const { locale } = useI18n()
const localePath = useLocalePath()
const form = useTemplateRef<HTMLFormElement>('form')

function apply() {
  if (!form.value) {
    return
  }
  const data = new FormData(form.value)
  const season = String(data.get('season'))
  const group = String(data.get('group') ?? '')
  // Changing season keeps the group: /competitions/{season} falls back to the default group if it is unknown.
  navigateTo({ path: localePath(`/competitions/${season}`), query: group ? { group } : {} })
}
</script>

<template>
  <form ref="form" method="get" :action="localePath('/competitions')" class="flex flex-wrap items-end gap-4" data-testid="competition-selector" @change="apply" @submit.prevent="apply">
    <label class="flex flex-col text-sm">
      {{ $t('results.season') }}
      <select name="season" class="rounded border border-line px-2 py-1">
        <option v-for="item in props.seasons" :key="item" :value="item" :selected="item === season">{{ item }}</option>
      </select>
    </label>
    <label class="flex flex-col text-sm">
      {{ $t('results.group') }}
      <select name="group" class="rounded border border-line px-2 py-1">
        <optgroup v-for="section in sections" :key="section.section" :label="$t(`results.section.${section.section}`)">
          <option v-for="item in section.groups" :key="item.code" :value="item.code" :selected="item.code === group">
            {{ groupLabel(item, locale) }}
          </option>
        </optgroup>
      </select>
    </label>
    <UButton type="submit" variant="outline">{{ $t('results.show') }}</UButton>
  </form>
</template>
