<script setup lang="ts">
import type { PlayerAutocomplete } from '~/types'
import { localized, typesForScope, type ReportScope, type ReportSummary, type ReportTypes } from '~/utils/reports'

/**
 * Rapports officiels (DOC/specs/FEATURE_DESIGNATIONS.md § 5, ticket designations/09) : rapports lisibles par
 * l'utilisateur ; création depuis un périmètre (?scopeType=&scopeId=, lien du bloc Désignations), avec un auteur
 * arbitre facultatif (saisie pour son compte, D17).
 */
definePageMeta({
  layout: 'admin',
  middleware: ['auth'],
})

const { t, locale } = useI18n()
const api = useApi()
const route = useRoute()
const workContext = useWorkContextStore()
const reportPdf = useReportPdf()

const types = ref<ReportTypes>({})
const reports = ref<ReportSummary[]>([])
const loading = ref(false)
const typeFilter = ref('')
const stateFilter = ref('')
const season = computed(() => workContext.season)
const scope = computed(() => (typeof route.query.scopeType === 'string' && typeof route.query.scopeId === 'string'
  ? { scopeType: route.query.scopeType as ReportScope, scopeId: route.query.scopeId, label: String(route.query.label ?? route.query.scopeId) }
  : null))
const creatable = computed(() => (scope.value ? typesForScope(types.value, scope.value.scopeType) : []))
const newType = ref('')
const author = ref<PlayerAutocomplete | null>(null)
const creating = ref(false)

const typeLabel = (code: string) => {
  const type = types.value[code]
  return type ? localized(type.versions[String(type.current)].label, locale.value) : code
}

const load = async () => {
  if (!season.value) return
  loading.value = true
  try {
    reports.value = (await api.get<{ items: ReportSummary[] }>('/admin/reports', { season: season.value, type: typeFilter.value, state: stateFilter.value })).items
  } finally {
    loading.value = false
  }
}

const create = async () => {
  if (!scope.value || !newType.value || !season.value) return
  creating.value = true
  try {
    const report = await api.post<{ id: number }>('/admin/reports', {
      type: newType.value,
      scopeType: scope.value.scopeType,
      scopeId: scope.value.scopeId,
      season: season.value,
      authorMatric: author.value?.matric ?? null,
      answers: {},
    })
    await navigateTo(`/reports/${report.id}`)
  } catch {
    // useApi affiche l'erreur (403 : pas officiel du périmètre ; 422 : auteur non officiel)
  } finally {
    creating.value = false
  }
}

watch([season, typeFilter, stateFilter], load)
onMounted(async () => {
  types.value = await api.get<ReportTypes>('/admin/reports/types')
  await load()
})
</script>

<template>
  <div>
    <AdminPageHeader :title="t('reports.title')" :show-filters="false" />

    <section v-if="scope" class="mb-6 p-4 rounded-lg border border-header-200 dark:border-header-700">
      <h2 class="font-semibold mb-3">{{ t('reports.new.title', { line: scope.label }) }}</h2>
      <form class="flex flex-wrap items-end gap-4" @submit.prevent="create">
        <label class="text-sm">
          {{ t('reports.new.type') }}
          <select v-model="newType" class="ml-2 rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm" required>
            <option value="">—</option>
            <option v-for="type in creatable" :key="type.code" :value="type.code">{{ localized(type.label, locale) }}</option>
          </select>
        </label>
        <div class="min-w-64">
          <span class="block text-sm mb-1">{{ t('reports.new.author') }}</span>
          <AdminPlayerAutocomplete v-model="author" :placeholder="t('reports.new.author_placeholder')" />
        </div>
        <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm disabled:opacity-50" :disabled="!newType || creating">{{ t('reports.new.submit') }}</button>
        <button v-for="type in creatable" :key="`blank-${type.code}`" type="button" class="text-sm underline" @click="reportPdf.openBlank(type.code, season)">
          {{ t('reports.blank', { type: localized(type.label, locale) }) }}
        </button>
      </form>
      <p class="text-xs text-header-500 mt-2">{{ t('reports.new.help') }}</p>
    </section>

    <div class="flex flex-wrap items-center gap-4 mb-4">
      <label class="text-sm">
        {{ t('reports.filter.type') }}
        <select v-model="typeFilter" class="ml-2 rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm">
          <option value="">{{ t('reports.filter.all') }}</option>
          <option v-for="code in Object.keys(types)" :key="code" :value="code">{{ typeLabel(code) }}</option>
        </select>
      </label>
      <label class="text-sm">
        {{ t('reports.filter.state') }}
        <select v-model="stateFilter" class="ml-2 rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm">
          <option value="">{{ t('reports.filter.all') }}</option>
          <option value="BROUILLON">{{ t('reports.state.BROUILLON') }}</option>
          <option value="SOUMIS">{{ t('reports.state.SOUMIS') }}</option>
        </select>
      </label>
    </div>

    <p v-if="loading" class="text-sm text-header-500">{{ t('common.loading') }}</p>
    <p v-else-if="reports.length === 0" class="text-sm text-header-500">{{ t('reports.empty') }}</p>
    <table v-else class="min-w-full text-sm border border-header-200 dark:border-header-700">
      <thead>
        <tr class="bg-header-50 dark:bg-header-800">
          <th scope="col" class="px-2 py-1 text-left">{{ t('reports.column.type') }}</th>
          <th scope="col" class="px-2 py-1 text-left">{{ t('reports.column.scope') }}</th>
          <th scope="col" class="px-2 py-1 text-left">{{ t('reports.column.state') }}</th>
          <th scope="col" class="px-2 py-1 text-left">{{ t('reports.column.entered_by') }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="report in reports" :key="report.id">
          <th scope="row" class="px-2 py-1 text-left font-normal"><NuxtLink :to="`/reports/${report.id}`" class="underline">{{ typeLabel(report.type) }} n° {{ report.id }}</NuxtLink></th>
          <td class="px-2 py-1">{{ t(`reports.scope.${report.scopeType}`) }} {{ report.scopeId }}</td>
          <td class="px-2 py-1">
            {{ t(`reports.state.${report.state}`) }}<span v-if="report.submittedAt"> · {{ report.submittedAt.slice(0, 10) }}</span>
            <span v-if="report.late" class="ml-1 px-1.5 rounded bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100">{{ t('reports.late') }}</span>
          </td>
          <td class="px-2 py-1">{{ report.enteredBy }}<span v-if="report.authorMatric && String(report.authorMatric) !== report.enteredBy"> ({{ t('reports.for_author', { matric: report.authorMatric }) }})</span></td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
