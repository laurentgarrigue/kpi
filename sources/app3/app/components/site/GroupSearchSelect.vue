<script setup lang="ts">
import type { GroupSection } from '#kpi-layer/utils/results/types'
import { groupLabel } from '~/utils/competitions'

// Group dropdown with search by typing (autocomplete): groups listed by section. A hidden field named `name` carries the
// code, so the enclosing GET form still works; `allLabel` adds an « all » entry with an empty code.
const props = defineProps<{ sections: GroupSection[], sectionLabel: (section: number) => string, name: string, allLabel?: string }>()
const model = defineModel<string>({ default: '' })
const { locale } = useI18n()

// Reka UI refuses an empty string as the value of an item: « all » travels as a sentinel, the model keeps ''.
const ALL = '__all__'
const selected = computed({
  get: () => (model.value === '' ? ALL : model.value),
  set: (value: string) => { model.value = value === ALL ? '' : value },
})

type Item = { label: string, value: string } | { type: 'label', label: string }
const items = computed<Item[][]>(() => [
  ...(props.allLabel === undefined ? [] : [[{ label: props.allLabel, value: ALL }]]),
  ...props.sections.map(section => [
    { type: 'label' as const, label: props.sectionLabel(section.section) },
    ...section.groups.map(group => ({ label: groupLabel(group, locale.value), value: group.code })),
  ]),
])
</script>

<template>
  <div class="min-w-56">
    <input type="hidden" :name="name" :value="model">
    <USelectMenu :model-value="selected as never" :items="items as never" value-key="value" class="w-full" :search-input="{ placeholder: $t('common.search') }" @update:model-value="selected = $event as string" />
  </div>
</template>
