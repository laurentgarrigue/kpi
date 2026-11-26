<script setup lang="ts">
import type { ChecklistCompetition } from '~/types'

/**
 * AdminCompetitionChecklist — liste de compétitions à cocher, groupées par section, avec une recherche (code ou
 * libellé). Partagée par le formulaire de mandat (UserMandateForm) et le suivi des Désignations.
 */

const props = withDefaults(defineProps<{
  modelValue: string[]
  competitions: ChecklistCompetition[]
  disabled?: boolean
  loading?: boolean
  listClass?: string
}>(), { disabled: false, loading: false, listClass: 'max-h-28' })
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()
const { t } = useI18n()

const search = ref('')
const grouped = computed(() => {
  const q = search.value.trim().toLowerCase()
  const groups = new Map<number, { label: string, items: ChecklistCompetition[] }>()
  for (const c of props.competitions) {
    if (q && !c.code.toLowerCase().includes(q) && !c.libelle.toLowerCase().includes(q)) continue
    groups.set(c.section, { label: c.sectionLabel, items: [...(groups.get(c.section)?.items ?? []), c] })
  }
  return [...groups.values()]
})
/** Compétitions affichées (après recherche), pour « cocher les affichées ». */
const shown = computed(() => grouped.value.flatMap(group => group.items.map(c => c.code)))

const toggle = (code: string) => emit('update:modelValue', props.modelValue.includes(code)
  ? props.modelValue.filter(c => c !== code)
  : [...props.modelValue, code])

defineExpose({ shown, clearSearch: () => (search.value = '') })
</script>

<template>
  <div>
    <div class="relative mb-1.5">
      <input
        v-model="search"
        type="text"
        class="w-full px-2 py-1 text-sm border border-header-200 dark:border-header-700 rounded bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-1 focus:ring-primary-400 focus:border-primary-400"
        :class="search ? 'pr-6' : ''"
        :placeholder="t('users.modal.filter_competitions_search')"
        :aria-label="t('users.modal.filter_competitions_search')"
      >
      <button
        v-if="search"
        type="button"
        class="absolute right-1.5 top-1/2 -translate-y-1/2 text-header-600 dark:text-header-300 hover:text-header-900 dark:hover:text-header-50 p-2"
        :aria-label="t('common.clear')"
        @click="search = ''"
      >
        <UIcon name="i-heroicons-x-mark" class="w-5 h-5" />
      </button>
    </div>
    <div class="relative overflow-y-auto" :class="listClass">
      <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-white/70 dark:bg-header-900/70">
        <span class="text-xs text-header-600 dark:text-header-300">{{ t('common.loading') }}</span>
      </div>
      <template v-if="!loading">
        <template v-for="group in grouped" :key="group.label">
          <div class="text-xs font-semibold text-header-600 dark:text-header-300 mt-1 mb-0.5">— {{ group.label }} —</div>
          <label
            v-for="c in group.items"
            :key="c.code"
            class="flex items-center gap-1.5 text-sm mb-0.5 cursor-pointer text-header-900 dark:text-header-50"
          >
            <input type="checkbox" :checked="modelValue.includes(c.code)" :disabled="disabled" @change="toggle(c.code)">
            {{ c.code }} - {{ c.libelle }}
          </label>
        </template>
        <div v-if="grouped.length === 0" class="text-xs text-header-600 dark:text-header-300 italic py-1">
          {{ t('users.modal.filter_competitions_no_results') }}
        </div>
      </template>
    </div>
  </div>
</template>
