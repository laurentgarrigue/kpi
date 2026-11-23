<script setup lang="ts">
import type { ReportScope } from '~/utils/reports'

/**
 * Choix de l'élément auquel rattacher un nouveau rapport, depuis la page Rapports (FEATURE_DESIGNATIONS.md D12, D14) :
 * une recherche côté serveur (GET /admin/reports/scopes) dans les événements, compétitions, journées et matchs de la
 * saison. C'est le seul point d'entrée de création : les autres pages n'ont pas de bouton « Rapport ».
 */
interface ScopeItem { scopeType: ReportScope, scopeId: string, label: string }

const props = defineProps<{ open: boolean, season: string }>()
const emit = defineEmits<{ close: [], chosen: [scope: ScopeItem] }>()
const { t } = useI18n()
const api = useApi()

const SCOPES: ReportScope[] = ['EVENEMENT', 'COMPETITION', 'JOURNEE', 'MATCH']
const MIN_LENGTH = 2
const DEBOUNCE_MS = 300
const query = ref('')
const types = ref<ReportScope[]>([])
const items = ref<ScopeItem[]>([])
const loading = ref(false)
const selected = ref<ScopeItem | null>(null)
let timer: ReturnType<typeof setTimeout> | undefined

const search = async () => {
  if (query.value.trim().length < MIN_LENGTH || !props.season) {
    items.value = []
    return
  }
  loading.value = true
  try {
    items.value = (await api.get<{ items: ScopeItem[] }>('/admin/reports/scopes', { season: props.season, q: query.value.trim(), types: types.value.join(',') })).items
  } finally {
    loading.value = false
  }
}

watch([query, types], () => {
  clearTimeout(timer)
  timer = setTimeout(search, DEBOUNCE_MS)
}, { deep: true })
watch(() => props.open, (open) => {
  if (open) {
    query.value = ''
    items.value = []
    selected.value = null
  }
})
onBeforeUnmount(() => clearTimeout(timer))

const submit = () => {
  if (selected.value) emit('chosen', selected.value)
}
</script>

<template>
  <AdminModal :open="open" :title="t('reports.new.choose_title')" max-width="xl" @close="emit('close')">
    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <label for="report-scope-search" class="block text-sm font-medium mb-1">{{ t('reports.new.search_label') }}</label>
        <input
          id="report-scope-search"
          v-model="query"
          type="search"
          autocomplete="off"
          :placeholder="t('reports.new.search_placeholder')"
          class="w-full rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1.5 text-sm"
        >
      </div>
      <fieldset>
        <legend class="sr-only">{{ t('reports.new.scope') }}</legend>
        <div class="flex flex-wrap gap-4 text-sm">
          <label v-for="type in SCOPES" :key="type" class="inline-flex items-center gap-1.5">
            <input v-model="types" type="checkbox" :value="type">
            {{ t(`reports.scope.${type}`) }}
          </label>
        </div>
      </fieldset>

      <p v-if="loading" class="text-sm text-header-500">{{ t('common.loading') }}</p>
      <p v-else-if="query.trim().length >= MIN_LENGTH && items.length === 0" class="text-sm text-header-500">{{ t('reports.new.no_result') }}</p>
      <ul v-else-if="items.length" class="max-h-72 overflow-y-auto border border-header-200 dark:border-header-700 rounded divide-y divide-header-100 dark:divide-header-800" role="listbox" :aria-label="t('reports.new.search_label')">
        <li v-for="item in items" :key="`${item.scopeType}-${item.scopeId}`" role="option" :aria-selected="selected === item">
          <button
            type="button"
            class="w-full text-left px-3 py-1.5 text-sm hover:bg-header-50 dark:hover:bg-header-800"
            :class="{ 'bg-primary-100 dark:bg-primary-900/40': selected === item }"
            @click="selected = item"
          >
            <span class="text-xs uppercase tracking-wide text-header-500 mr-2">{{ t(`reports.scope.${item.scopeType}`) }}</span>{{ item.label }}
          </button>
        </li>
      </ul>

      <p v-if="selected" class="text-sm">{{ t('reports.new.selected', { label: `${t(`reports.scope.${selected.scopeType}`)} · ${selected.label}` }) }}</p>
      <div class="flex justify-end gap-2">
        <button type="button" class="px-3 py-1.5 rounded border border-header-300 dark:border-header-700 text-sm" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm disabled:opacity-50" :disabled="!selected">{{ t('common.next') }}</button>
      </div>
    </form>
  </AdminModal>
</template>
