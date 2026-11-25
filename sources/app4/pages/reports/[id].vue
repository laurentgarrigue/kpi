<script setup lang="ts">
import { answersToSend, localized, missingRequired, type ReportDetail, type ReportRow } from '~/utils/reports'

/**
 * Un rapport officiel (DOC/specs/FEATURE_DESIGNATIONS.md § 5, D18 ; ticket designations/09) : données KPI du
 * périmètre, formulaire rendu depuis la définition de SA version (RAP-01, RAP-02), brouillon, soumission,
 * réouverture (profils 1–2), PDF de chaque soumission.
 */
definePageMeta({
  layout: 'admin',
  middleware: ['auth'],
})

interface KpiData {
  label?: string
  competitions?: string[]
  start?: string | null
  end?: string | null
  places?: string[]
  match?: { number: string | null, teams: (string | null)[], score: (string | null)[], referees: string[] }
  matches?: { planned: number, played: number }
  cards?: Record<string, { green: number, yellow: number, red: number, black: number }>
  linked?: { id: number, type: string, scopeType: string, scopeId: string, state: string }[]
}

const { t, locale } = useI18n()
const api = useApi()
const toast = useToast()
const route = useRoute()
const reportPdf = useReportPdf()

const report = ref<ReportDetail | null>(null)
const answers = ref<Record<string, unknown>>({})
const kpi = ref<KpiData>({})
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const reopening = ref(false)
const reason = ref('')
const id = computed(() => Number(route.params.id))
const missing = computed(() => (report.value ? missingRequired(report.value.definition, answers.value) : []))

const load = async () => {
  report.value = await api.get<ReportDetail>(`/admin/reports/${id.value}`)
  answers.value = { ...report.value.answers }
  kpi.value = await api.get<KpiData>('/admin/reports/prefill', { scopeType: report.value.scopeType, scopeId: report.value.scopeId, season: report.value.season }).catch(() => ({}))
}

const fieldErrors = (error: unknown) => ((error as { data?: { fields?: Record<string, string> } })?.data?.fields) ?? {}

const save = async () => {
  if (!report.value) return
  saving.value = true
  try {
    report.value = await api.patch<ReportDetail>(`/admin/reports/${id.value}`, { answers: answersToSend(report.value.definition, answers.value) })
    errors.value = {}
    toast.add({ title: t('reports.saved'), color: 'success' })
  } catch (error) {
    errors.value = fieldErrors(error)
  } finally {
    saving.value = false
  }
}

const submit = async () => {
  if (!report.value) return
  await save()
  if (Object.keys(errors.value).length) return
  saving.value = true
  try {
    report.value = await api.post<ReportDetail>(`/admin/reports/${id.value}/submit`)
    toast.add({ title: t('reports.submitted'), color: 'success' })
  } catch (error) {
    errors.value = fieldErrors(error)
  } finally {
    saving.value = false
  }
}

const reopen = async () => {
  try {
    report.value = await api.post<ReportDetail>(`/admin/reports/${id.value}/reopen`, { reason: reason.value })
    reopening.value = false
    reason.value = ''
  } catch {
    // useApi affiche l'erreur
  }
}

onMounted(load)
</script>

<template>
  <div v-if="report">
    <AdminPageHeader :title="`${localized(report.definition.label, locale)} n° ${report.id}`" :show-filters="false" />

    <p class="mb-4 text-sm">
      <span class="px-2 py-0.5 rounded" :class="report.state === 'SOUMIS' ? 'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100' : 'bg-header-100 dark:bg-header-800'">{{ t(`reports.state.${report.state}`) }}</span>
      <span v-if="report.late" class="ml-2 px-2 py-0.5 rounded bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100">{{ t('reports.late') }}</span>
      <span class="ml-2">{{ t('reports.version', { version: report.version }) }}</span>
      <span class="ml-2">· {{ t('reports.entered_by', { code: report.enteredBy }) }}</span>
      <span v-if="report.authorMatric && String(report.authorMatric) !== report.enteredBy"> · {{ t('reports.for_author', { matric: report.authorMatric }) }}</span>
    </p>

    <section class="mb-6 p-4 rounded-lg border border-header-200 dark:border-header-700 text-sm">
      <h2 class="font-semibold mb-2">{{ t('reports.kpi') }}</h2>
      <dl class="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1">
        <dt class="text-header-500">{{ t('reports.kpi_label') }}</dt><dd>{{ kpi.label }}</dd>
        <template v-if="kpi.competitions?.length"><dt class="text-header-500">{{ t('reports.kpi_competitions') }}</dt><dd>{{ kpi.competitions.join(', ') }}</dd></template>
        <template v-if="kpi.start"><dt class="text-header-500">{{ t('reports.kpi_dates') }}</dt><dd>{{ kpi.start }}<span v-if="kpi.end && kpi.end !== kpi.start"> – {{ kpi.end }}</span></dd></template>
        <template v-if="kpi.places?.length"><dt class="text-header-500">{{ t('reports.kpi_place') }}</dt><dd>{{ kpi.places.join(', ') }}</dd></template>
        <template v-if="kpi.match">
          <dt class="text-header-500">{{ t('reports.kpi_match') }}</dt><dd>n° {{ kpi.match.number }} · {{ kpi.match.teams.join(' - ') }} · {{ kpi.match.score.join(' - ') }}</dd>
          <dt class="text-header-500">{{ t('reports.kpi_referees') }}</dt><dd>{{ kpi.match.referees.join(', ') }}</dd>
        </template>
        <template v-if="kpi.matches && !kpi.match"><dt class="text-header-500">{{ t('reports.kpi_matches') }}</dt><dd>{{ t('reports.kpi_matches_value', kpi.matches) }}</dd></template>
        <template v-for="(cards, code) in kpi.cards ?? {}" :key="code">
          <dt class="text-header-500">{{ t('reports.kpi_cards', { competition: code }) }}</dt>
          <dd>{{ t('reports.kpi_cards_value', cards) }}</dd>
        </template>
        <template v-if="kpi.linked?.filter(item => item.id !== report?.id).length">
          <dt class="text-header-500">{{ t('reports.kpi_linked') }}</dt>
          <dd>
            <NuxtLink v-for="item in kpi.linked.filter(i => i.id !== report?.id)" :key="item.id" :to="`/reports/${item.id}`" class="underline mr-3">{{ item.type }} n° {{ item.id }} ({{ t(`reports.state.${item.state}`) }})</NuxtLink>
          </dd>
        </template>
      </dl>
    </section>

    <form class="space-y-6" @submit.prevent="submit">
      <fieldset v-for="section in report.definition.sections" :key="section.key" class="p-4 rounded-lg border border-header-200 dark:border-header-700">
        <legend class="px-1 font-semibold">{{ localized(section.title, locale) }}</legend>
        <div class="grid gap-4 md:grid-cols-2">
          <template v-for="field in section.fields" :key="field.key">
            <ReportsTableField
              v-if="field.type === 'table'"
              :field="field"
              :rows="(answers[field.key] as ReportRow[]) ?? []"
              :disabled="!report.canEdit"
              :error="errors[field.key]"
              @update="rows => (answers[field.key] = rows)"
            />
            <ReportsField
              v-else
              :class="{ 'md:col-span-2': field.type === 'textarea' }"
              :field="field"
              :answers="answers"
              :disabled="!report.canEdit"
              :error="errors[field.key]"
              @update="(key, value) => (answers[key] = value)"
            />
          </template>
        </div>
      </fieldset>

      <div class="flex flex-wrap items-center gap-3">
        <template v-if="report.canEdit">
          <button type="button" class="px-3 py-1.5 rounded border border-header-300 dark:border-header-700 text-sm disabled:opacity-50" :disabled="saving" @click="save">{{ t('reports.save') }}</button>
          <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm disabled:opacity-50" :disabled="saving">{{ t('reports.submit') }}</button>
          <span v-if="missing.length" class="text-xs text-header-500">{{ t('reports.missing', { n: missing.length }) }}</span>
        </template>
        <button type="button" class="px-3 py-1.5 rounded border border-header-300 dark:border-header-700 text-sm" @click="reportPdf.openReport(report.id)">{{ t('reports.pdf') }}</button>
        <button v-if="report.canReopen" type="button" class="px-3 py-1.5 rounded border border-orange-400 text-sm" @click="reopening = true">{{ t('reports.reopen.button') }}</button>
      </div>
    </form>

    <section v-if="report.submissions.length" class="mt-6 text-sm">
      <h2 class="font-semibold mb-2">{{ t('reports.submissions') }}</h2>
      <ul class="list-disc ml-5">
        <li v-for="submission in report.submissions" :key="submission.number">
          {{ t('reports.submission', { number: submission.number, date: submission.submittedAt, by: submission.submittedBy }) }}
          <button type="button" class="underline ml-2" @click="reportPdf.openReport(report.id, submission.number)">PDF</button>
        </li>
      </ul>
    </section>

    <AdminModal :open="reopening" :title="t('reports.reopen.title')" @close="reopening = false">
      <form class="space-y-4" @submit.prevent="reopen">
        <label for="reopen-reason" class="block text-sm font-medium">{{ t('reports.reopen.reason') }} *</label>
        <textarea id="reopen-reason" v-model="reason" rows="3" maxlength="500" required class="w-full rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm" />
        <div class="flex justify-end gap-2">
          <button type="button" class="px-3 py-1.5 rounded border border-header-300 dark:border-header-700 text-sm" @click="reopening = false">{{ t('common.cancel') }}</button>
          <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm disabled:opacity-50" :disabled="!reason.trim()">{{ t('reports.reopen.button') }}</button>
        </div>
      </form>
    </AdminModal>
  </div>
</template>
