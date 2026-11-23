<script setup lang="ts">
/**
 * AdminCompetitionSectionPicker — sélection multiple de compétitions d'une saison, groupées par section, avec un
 * filtre section et une recherche (même source et même présentation que le filtre compétitions des mandats,
 * UserMandateForm). Aucune compétition choisie = toutes.
 */
interface Competition { code: string, libelle: string, section: number, sectionLabel: string }

const props = defineProps<{ modelValue: string[], season: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()
const { t } = useI18n()
const api = useApi()

const competitions = ref<Competition[]>([])
const loading = ref(false)
const search = ref('')
const section = ref<number | ''>('')

const sections = computed(() => [...new Map(competitions.value.map(c => [c.section, c.sectionLabel])).entries()])
const shown = computed(() => {
  const q = search.value.trim().toLowerCase()
  return competitions.value.filter(c => (section.value === '' || c.section === section.value)
    && (!q || c.code.toLowerCase().includes(q) || c.libelle.toLowerCase().includes(q)))
})
const grouped = computed(() => {
  const groups = new Map<number, { label: string, items: Competition[] }>()
  for (const c of shown.value) {
    groups.set(c.section, { label: c.sectionLabel, items: [...(groups.get(c.section)?.items ?? []), c] })
  }
  return [...groups.values()]
})
const summary = computed(() => (props.modelValue.length === 0 ? t('competition_picker.all') : t('competition_picker.count', { n: props.modelValue.length })))

const load = async () => {
  if (!props.season) return
  loading.value = true
  try {
    const data = await api.get<{ groups: { section: number, sectionLabel: string, competitions: { code: string, libelle: string }[] }[] }>('/admin/filters/competitions', { seasons: props.season })
    competitions.value = (data.groups ?? []).flatMap(group => group.competitions.map(c => ({ ...c, section: group.section, sectionLabel: group.sectionLabel })))
    const valid = new Set(competitions.value.map(c => c.code))
    if (props.modelValue.some(code => !valid.has(code))) {
      emit('update:modelValue', props.modelValue.filter(code => valid.has(code)))
    }
  } finally {
    loading.value = false
  }
}

const toggle = (code: string) => emit('update:modelValue', props.modelValue.includes(code)
  ? props.modelValue.filter(c => c !== code)
  : [...props.modelValue, code])
const selectShown = () => emit('update:modelValue', [...new Set([...props.modelValue, ...shown.value.map(c => c.code)])])

watch(() => props.season, load, { immediate: true })
</script>

<template>
  <details class="relative">
    <summary class="cursor-pointer list-none px-3 py-1.5 text-sm rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900">
      {{ t('competition_picker.label') }} : {{ summary }}
    </summary>
    <div class="absolute z-20 mt-1 w-96 max-w-[90vw] p-3 rounded-lg shadow-lg border border-header-200 dark:border-header-700 bg-white dark:bg-header-900">
      <div class="flex gap-2 mb-2">
        <select v-model="section" class="flex-1 text-sm rounded border-header-300 dark:bg-header-900 dark:border-header-700" :aria-label="t('competition_picker.section')">
          <option value="">{{ t('competition_picker.all_sections') }}</option>
          <option v-for="[id, label] in sections" :key="id" :value="id">{{ label }}</option>
        </select>
        <input v-model="search" type="search" class="flex-1 text-sm rounded border-header-300 dark:bg-header-900 dark:border-header-700" :placeholder="t('competition_picker.search')" :aria-label="t('competition_picker.search')">
      </div>
      <div class="flex gap-3 mb-2 text-xs">
        <button type="button" class="underline" @click="selectShown">{{ t('competition_picker.select_shown') }}</button>
        <button type="button" class="underline" @click="emit('update:modelValue', [])">{{ t('competition_picker.clear') }}</button>
      </div>
      <p v-if="loading" class="text-xs text-header-500">{{ t('common.loading') }}</p>
      <div v-else class="max-h-72 overflow-y-auto">
        <template v-for="group in grouped" :key="group.label">
          <div class="text-xs font-semibold text-header-600 dark:text-header-300 mt-1 mb-0.5">— {{ group.label }} —</div>
          <label v-for="c in group.items" :key="c.code" class="flex items-center gap-1.5 text-sm mb-0.5 cursor-pointer">
            <input type="checkbox" :checked="modelValue.includes(c.code)" @change="toggle(c.code)">
            {{ c.code }} - {{ c.libelle }}
          </label>
        </template>
        <p v-if="grouped.length === 0" class="text-xs italic text-header-500">{{ t('competition_picker.none') }}</p>
      </div>
    </div>
  </details>
</template>
