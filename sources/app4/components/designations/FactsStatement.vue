<script setup lang="ts">
/**
 * Relevé des faits pour les rétributions et pénalités (FEATURE_DESIGNATIONS.md D19, § 6 ; REL-01 à REL-05) : quantités
 * par club, jamais de montant, chaque fait avec ses sources ; export CSV et Excel.
 */
interface FactRow {
  club: string | null
  clubLabel: string
  fact: string
  qualifier: string
  quantity: number
  details: { label: string, sourceType: string, sourceId: string }[]
}

const props = defineProps<{ season: string }>()
const { t, te } = useI18n()
const api = useApi()

const items = ref<FactRow[]>([])
const loading = ref(false)
const clubFilter = ref('')
const kindFilter = ref('')
/** Types de fait présents dans le relevé, pour le filtre. */
const kinds = computed(() => [...new Set(items.value.map(row => row.fact))])
const shown = computed(() => {
  const q = clubFilter.value.trim().toLowerCase()
  return items.value.filter(row => (!q || `${row.club ?? ''} ${row.clubLabel}`.toLowerCase().includes(q)) && (!kindFilter.value || row.fact === kindFilter.value))
})
const qualifierLabel = (row: FactRow) => {
  const key = `designations.facts.qualifier.${row.qualifier}`
  return row.qualifier && te(key) ? t(key) : row.qualifier
}

const load = async () => {
  if (!props.season) return
  loading.value = true
  try {
    items.value = (await api.get<{ items: FactRow[] }>('/admin/designations/facts', { season: props.season })).items
  } finally {
    loading.value = false
  }
}

const download = async (format: 'csv' | 'xlsx') => {
  const buffer = await api.getBlob(`/admin/designations/facts?season=${props.season}&format=${format}`)
  const type = format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
  const link = document.createElement('a')
  link.href = URL.createObjectURL(new Blob([buffer], { type }))
  link.download = `releve-des-faits-${props.season}.${format}`
  link.click()
  URL.revokeObjectURL(link.href)
}

watch(() => props.season, load, { immediate: true })
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center gap-3 mb-4">
      <input v-model="clubFilter" type="search" :placeholder="t('designations.facts.filter')" :aria-label="t('designations.facts.filter')" class="rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm">
      <select v-model="kindFilter" :aria-label="t('designations.facts.kind_filter')" class="rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm">
        <option value="">{{ t('designations.facts.all_kinds') }}</option>
        <option v-for="kind in kinds" :key="kind" :value="kind">{{ t(`designations.facts.kind.${kind}`) }}</option>
      </select>
      <span class="text-xs text-header-500">{{ t('designations.facts.help') }}</span>
      <div class="ml-auto flex gap-2">
        <button type="button" class="px-3 py-1.5 text-sm rounded-lg border border-header-300 dark:border-header-700" @click="download('csv')">CSV</button>
        <button type="button" class="px-3 py-1.5 text-sm rounded-lg border border-header-300 dark:border-header-700" @click="download('xlsx')">Excel</button>
      </div>
    </div>
    <p v-if="loading" class="text-sm text-header-500">{{ t('common.loading') }}</p>
    <p v-else-if="shown.length === 0" class="text-sm text-header-500">{{ t('designations.facts.empty') }}</p>
    <table v-else class="min-w-full text-sm border border-header-200 dark:border-header-700">
      <thead>
        <tr class="bg-header-50 dark:bg-header-800">
          <th scope="col" class="px-2 py-1 text-left">{{ t('designations.column.club') }}</th>
          <th scope="col" class="px-2 py-1 text-left">{{ t('designations.facts.fact') }}</th>
          <th scope="col" class="px-2 py-1 text-left">{{ t('designations.facts.qualifier_label') }}</th>
          <th scope="col" class="px-2 py-1 text-right">{{ t('designations.facts.quantity') }}</th>
          <th scope="col" class="px-2 py-1 text-left">{{ t('designations.facts.sources') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in shown" :key="`${row.club}-${row.fact}-${row.qualifier}`" class="align-top">
          <th scope="row" class="px-2 py-1 text-left font-normal">{{ row.club ? `${row.club} · ${row.clubLabel}` : t('designations.facts.no_club') }}</th>
          <td class="px-2 py-1">{{ t(`designations.facts.kind.${row.fact}`) }}</td>
          <td class="px-2 py-1">{{ qualifierLabel(row) }}</td>
          <td class="px-2 py-1 text-right tabular-nums font-semibold">{{ row.quantity }}</td>
          <td class="px-2 py-1">
            <details>
              <summary class="cursor-pointer text-xs underline">{{ t('designations.facts.sources_count', { n: row.details.length }) }}</summary>
              <ul class="text-xs mt-1 space-y-0.5">
                <li v-for="(detail, index) in row.details" :key="index">
                  <NuxtLink v-if="detail.sourceType === 'REPORT'" :to="`/reports/${detail.sourceId}`" class="underline">{{ detail.label }}</NuxtLink>
                  <span v-else>{{ detail.label }}</span>
                </li>
              </ul>
            </details>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
