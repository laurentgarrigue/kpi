<script setup lang="ts">
import type { ChecklistCompetition } from '~/types'

/**
 * AdminCompetitionSectionPicker — choix de compétitions d'une saison : une liste « section » à part, puis une liste
 * déroulante de compétitions à cocher (AdminCompetitionChecklist, celle du formulaire de mandat). Même source que le
 * filtre des mandats. Aucune compétition choisie = toutes (de la section choisie, s'il y en a une).
 */
const props = defineProps<{ modelValue: string[], season: string, storageKey?: string }>()
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()
const { t } = useI18n()
const api = useApi()

const competitions = ref<ChecklistCompetition[]>([])
const loading = ref(false)
/** Section mémorisée dans le navigateur quand `storageKey` est fourni (les compétitions cochées le sont par la page). */
const loadSection = (): number | '' => {
  if (!props.storageKey) return ''
  try {
    const saved = JSON.parse(localStorage.getItem(props.storageKey) ?? '""')
    return typeof saved === 'number' ? saved : ''
  } catch { return '' }
}
const section = ref<number | ''>(loadSection())
const checklist = ref<{ shown: string[] } | null>(null)

const sections = computed(() => [...new Map(competitions.value.map(c => [c.section, c.sectionLabel])).entries()])
const inSection = computed(() => competitions.value.filter(c => section.value === '' || c.section === section.value))
const summary = computed(() => (props.modelValue.length === 0 ? t('competition_picker.all') : t('competition_picker.count', { n: props.modelValue.length })))

const load = async () => {
  if (!props.season) return
  loading.value = true
  try {
    const data = await api.get<{ groups: { section: number, sectionLabel: string, competitions: { code: string, libelle: string }[] }[] }>('/admin/filters/competitions', { seasons: props.season })
    competitions.value = (data.groups ?? []).flatMap(group => group.competitions.map(c => ({ ...c, section: group.section, sectionLabel: group.sectionLabel })))
    if (section.value !== '' && !sections.value.some(([id]) => id === section.value)) section.value = ''
    const valid = new Set(competitions.value.map(c => c.code))
    if (props.modelValue.some(code => !valid.has(code))) {
      emit('update:modelValue', props.modelValue.filter(code => valid.has(code)))
    }
  } finally {
    loading.value = false
  }
}

/** Une section choisie sans compétition cochée restreint l'affichage à ses compétitions. */
const onSection = () => {
  if (props.storageKey) {
    try { localStorage.setItem(props.storageKey, JSON.stringify(section.value)) } catch { /* ignore */ }
  }
  emit('update:modelValue', section.value === '' ? [] : inSection.value.map(c => c.code))
}
const selectShown = () => emit('update:modelValue', [...new Set([...props.modelValue, ...(checklist.value?.shown ?? [])])])

watch(() => props.season, load, { immediate: true })
</script>

<template>
  <div class="flex flex-wrap items-center gap-2">
    <select
      v-model="section"
      class="px-3 py-1.5 text-sm rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50"
      :aria-label="t('competition_picker.section')"
      @change="onSection"
    >
      <option value="">{{ t('competition_picker.all_sections') }}</option>
      <option v-for="[id, label] in sections" :key="id" :value="id">{{ label }}</option>
    </select>
    <details class="relative">
      <summary class="cursor-pointer list-none px-3 py-1.5 text-sm rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900">
        {{ t('competition_picker.label') }} : {{ summary }}
      </summary>
      <div class="absolute z-20 mt-1 w-96 max-w-[90vw] p-2 rounded-lg shadow-lg border border-header-200 dark:border-header-700 bg-white dark:bg-header-900">
        <div class="flex gap-3 mb-2 text-xs">
          <button type="button" class="underline" @click="selectShown">{{ t('competition_picker.select_shown') }}</button>
          <button type="button" class="underline" @click="emit('update:modelValue', [])">{{ t('competition_picker.clear') }}</button>
        </div>
        <AdminCompetitionChecklist
          ref="checklist"
          :model-value="modelValue"
          :competitions="inSection"
          :loading="loading"
          list-class="max-h-72"
          @update:model-value="emit('update:modelValue', $event)"
        />
      </div>
    </details>
  </div>
</template>
