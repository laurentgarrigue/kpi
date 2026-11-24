<script setup lang="ts">
import type { PlayerAutocomplete } from '~/types'
import {
  alertLinks, articleGroups, compareRules, gamedayLink, groupAlerts, linesCsv, MANUAL_TYPE, parseCodes, seasonActions, standingsCsv, unresolvedOfficials,
  type ClubRankingCategory, type ClubRankingRule, type DetailLine, type RankingAlert, type SeasonStatus, type StandingRow,
} from '~/utils/clubRanking'

/**
 * National club ranking, profile 1 only (DOC/specs/PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 8, Q-1): scale of the season,
 * calculation, review of every point, manual entries, adjustments, validation and publication.
 */
definePageMeta({
  layout: 'admin',
  middleware: ['auth'],
})

interface SeasonInfo {
  season: string
  status: SeasonStatus
  stale: boolean
  top_n: number
  national_prefixes: string
  excluded_competitions: string
  computed_at: string | null
  computed_by: string | null
  validated_at: string | null
  validated_by: string | null
}
interface Entry { id: number, rule: string, matric: number | null, name: string, club: string | null, club_label: string | null, detail: string, created_by: string | null, created_at: string }
interface AdjustmentRow { id: number, rule: string, club: string, points: number, reason: string, created_by: string | null, created_at: string }
interface Entente { team_number: number, label: string, club: string, partners: string[] }
interface Publication { version: number, published_at: string, published_by: string | null }
interface Overview {
  season: SeasonInfo
  types: string[]
  rules: ClubRankingRule[]
  categories: ClubRankingCategory[]
  scale_errors: string[]
  alerts: RankingAlert[]
  standings: StandingRow[]
  entries: Entry[]
  adjustments: AdjustmentRow[]
  ententes: Entente[]
  publications: Publication[]
  competitions: string[]
}
type Tab = 'ranking' | 'alerts' | 'entries' | 'adjustments' | 'scale' | 'publications'
interface ApiFailure { message?: string }

const { t } = useI18n()
const router = useRouter()
const api = useApi()
const toast = useToast()
const authStore = useAuthStore()
const workContext = useWorkContextStore()
const { openDocument } = usePdf()

// Profile guard: profile 1 only (CNC-28b), api2 refuses the others anyway
if (authStore.profile > 1) {
  navigateTo('/')
}

const TABS: Tab[] = ['scale', 'ranking', 'alerts', 'entries', 'adjustments', 'publications']
const tab = ref<Tab>('scale')
const seasons = ref<{ season: string, status: SeasonStatus }[]>([])
const season = ref('')
const data = ref<Overview | null>(null)
const loading = ref(false)
const working = ref(false)
const query = ref('')

const actions = computed(() => data.value ? seasonActions(data.value.season) : null)
const sortedRules = computed(() => [...(data.value?.rules ?? [])].sort(compareRules))
const groups = computed(() => articleGroups(data.value?.rules ?? []))
const manualRules = computed(() => sortedRules.value.filter(rule => rule.type === MANUAL_TYPE))
const alertGroups = computed(() => groupAlerts(data.value?.alerts ?? []))
const officials = computed(() => unresolvedOfficials(data.value?.alerts ?? []))
const blockingAlerts = computed(() => (data.value?.alerts ?? []).filter(alert => alert.blocking).length)
const shownStandings = computed(() => {
  const words = query.value.trim().toLowerCase()
  return (data.value?.standings ?? []).filter(row => words === '' || `${row.label} ${row.club} ${row.department} ${row.region}`.toLowerCase().includes(words))
})
const clubLabel = (code: string) => data.value?.standings.find(row => row.club === code)?.label ?? code

const errorMessage = (error: unknown) => (error as ApiFailure)?.message ?? t('common.error')
const fail = (error: unknown) => toast.add({ title: t('common.error'), description: errorMessage(error), color: 'error' })

async function loadSeasons() {
  const result = await api.get<{ items: { season: string, status: SeasonStatus }[] }>('/admin/club-ranking/seasons')
  seasons.value = result.items
  if (!season.value) {
    season.value = seasons.value.find(item => item.season === workContext.season)?.season ?? seasons.value[0]?.season ?? ''
  }
}

async function load() {
  if (!season.value) {
    data.value = null
    return
  }
  loading.value = true
  try {
    data.value = await api.get<Overview>(`/admin/club-ranking/${season.value}`)
  }
  catch (error) {
    fail(error)
  }
  finally {
    loading.value = false
  }
}

/** Runs an action, then reloads the season; `success` is the toast of a success. */
async function run(action: () => Promise<unknown>, success?: string) {
  working.value = true
  try {
    await action()
    if (success) {
      toast.add({ title: success, color: 'success' })
    }
    await Promise.all([load(), loadSeasons()])
    return true
  }
  catch (error) {
    fail(error)
    return false
  }
  finally {
    working.value = false
  }
}

const base = () => `/admin/club-ranking/${season.value}`
const compute = () => run(() => api.post(`${base()}/compute`), t('club_ranking.computed'))
const validateSeason = () => run(() => api.post(`${base()}/validate`), t('club_ranking.validated'))
const publish = () => run(() => api.post(`${base()}/publish`), t('club_ranking.published'))
const reopen = () => run(() => api.post(`${base()}/reopen`), t('club_ranking.reopened'))
const pdf = (view: 'public' | 'work', version?: number) => openDocument('club-ranking', { season: season.value, view, ...(version ? { version } : {}) })

// New season, optionally with the scale of another one (CNC-01)
const newSeason = reactive({ open: false, season: '', copyFrom: '' })
async function createSeason() {
  const created = newSeason.season
  if (await run(() => api.post('/admin/club-ranking/seasons', { season: created, copy_from: newSeason.copyFrom || null }), t('club_ranking.season_created'))) {
    newSeason.open = false
    season.value = created
  }
}

// Detail of a cell: every line that makes the points of a club for a rule (§ 8 step 3)
const detail = reactive<{ open: boolean, title: string, lines: DetailLine[] }>({ open: false, title: '', lines: [] })
async function showDetail(row: StandingRow, rule?: ClubRankingRule) {
  const params: Record<string, string> = { club: row.club }
  if (rule) {
    params.rule = rule.code
  }
  const result = await api.get<{ items: DetailLine[] }>(`${base()}/lines`, params)
  const adjustments = (data.value?.adjustments ?? []).filter(item => item.club === row.club && (!rule || item.rule === rule.code))
  detail.lines = [...result.items, ...adjustments.map(item => ({ rule: item.rule, club: item.club, points: item.points, reason: `${t('club_ranking.adjustment')} : ${item.reason}`, source_type: 'adjustment', source_id: String(item.id), matric: null }))]
  detail.title = rule ? `${row.label} — ${rule.code} ${rule.short_label}` : row.label
  detail.open = true
}

// CSV exports, like the calculation tab of the workbook
function download(name: string, text: string) {
  const url = URL.createObjectURL(new Blob([`\uFEFF${text}`], { type: 'text/csv;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = name
  link.click()
  URL.revokeObjectURL(url)
}
const headers = computed(() => ({ rank: t('club_ranking.rank'), club: t('club_ranking.club'), number: t('club_ranking.number'), department: t('club_ranking.department'), region: t('club_ranking.region'), total: t('club_ranking.total'), rule: t('club_ranking.rule'), points: t('club_ranking.points'), reason: t('club_ranking.reason') }))
function exportStandings() {
  download(`classement-clubs-${season.value}.csv`, standingsCsv(data.value?.standings ?? [], data.value?.rules ?? [], headers.value))
}
async function exportLines() {
  const result = await api.get<{ items: DetailLine[] }>(`${base()}/lines`)
  download(`classement-clubs-${season.value}-detail.csv`, linesCsv(result.items, clubLabel, headers.value))
}

// Matching of the gameday officials (N-5)
const aliasChoice = reactive<Record<string, PlayerAutocomplete | null>>({})
const saveAlias = (name: string) => run(() => api.post(`${base()}/aliases`, { name, matric: aliasChoice[name]?.matric }), t('club_ranking.alias_saved'))

// Manual entries (§ 7)
const entryForm = reactive<{ rule: string, player: PlayerAutocomplete | null, club: string, detail: string }>({ rule: '', player: null, club: '', detail: '' })
const entriesOf = (code: string) => (data.value?.entries ?? []).filter(entry => entry.rule === code)
async function addEntry(rule: string) {
  const payload = { rule, matric: entryForm.player?.matric ?? null, club: entryForm.player ? null : entryForm.club || null, detail: entryForm.detail }
  if (await run(() => api.post(`${base()}/entries`, payload), t('club_ranking.entry_added'))) {
    Object.assign(entryForm, { player: null, club: '', detail: '' })
  }
}
const deleteEntry = (id: number) => run(() => api.del(`${base()}/entries/${id}`))

// Ententes (N-7)
const ententeForm = reactive<{ team: number | null, club: string }>({ team: null, club: '' })
async function addEntente() {
  if (await run(() => api.post(`${base()}/ententes`, { team_number: ententeForm.team, club: ententeForm.club }), t('club_ranking.entente_added'))) {
    Object.assign(ententeForm, { team: null, club: '' })
  }
}
const deleteEntente = (team: number, club: string) => run(() => api.del(`${base()}/ententes/${team}/${club}`))

// Adjustments, reason required (CNC-26)
const adjustmentForm = reactive({ rule: '', club: '', points: 0, reason: '' })
async function addAdjustment() {
  if (await run(() => api.post(`${base()}/adjustments`, { ...adjustmentForm }), t('club_ranking.adjustment_added'))) {
    Object.assign(adjustmentForm, { club: '', points: 0, reason: '' })
  }
}
const deleteAdjustment = (id: number) => run(() => api.del(`${base()}/adjustments/${id}`))

// Scale: settings, rules, categories (§ 3)
const settings = reactive({ top_n: 40, national_prefixes: '', excluded_competitions: '' })
watch(data, (value) => {
  if (value) {
    Object.assign(settings, { top_n: value.season.top_n, national_prefixes: value.season.national_prefixes, excluded_competitions: value.season.excluded_competitions })
  }
})
const saveSettings = () => run(() => api.put(`${base()}/settings`, { ...settings }), t('common.saved'))
const copyFrom = ref('')
const copyScale = () => run(() => api.post(`${base()}/copy-scale`, { from: copyFrom.value }), t('club_ranking.scale_copied'))

const ruleForm = reactive({ open: false, id: null as number | null, article: '7.1', rank: 1, label: '', short_label: '', points: 0, type: 'TEAM_OF_CLUB', params: '{}', note: '', error: '' })
function editRule(rule?: ClubRankingRule) {
  Object.assign(ruleForm, rule
    ? { open: true, id: rule.id, article: rule.article, rank: rule.rank, label: rule.label, short_label: rule.short_label, points: rule.points, type: rule.type, params: JSON.stringify(rule.params, null, 2), note: rule.note, error: '' }
    : { open: true, id: null, article: '7.1', rank: 1, label: '', short_label: '', points: 0, type: 'TEAM_OF_CLUB', params: '{}', note: '', error: '' })
}
async function saveRule() {
  let params: unknown
  try {
    params = JSON.parse(ruleForm.params)
  }
  catch {
    ruleForm.error = t('club_ranking.params_invalid')
    return
  }
  const payload = { article: ruleForm.article, rank: ruleForm.rank, label: ruleForm.label, short_label: ruleForm.short_label, points: ruleForm.points, type: ruleForm.type, params, note: ruleForm.note }
  const saved = await run(() => ruleForm.id ? api.put(`${base()}/rules/${ruleForm.id}`, payload) : api.post(`${base()}/rules`, payload), t('common.saved'))
  if (saved) {
    ruleForm.open = false
  }
}
const deleteRule = (id: number) => run(() => api.del(`${base()}/rules/${id}`))

const categoryForm = reactive({ open: false, id: null as number | null, code: '', label: '', entry_codes: '', final_code: '', regular_code: '' })
function editCategory(category?: ClubRankingCategory) {
  Object.assign(categoryForm, category
    ? { open: true, id: category.id, code: category.code, label: category.label, entry_codes: category.entry_codes.join(', '), final_code: category.final_code ?? '', regular_code: category.regular_code ?? '' }
    : { open: true, id: null, code: '', label: '', entry_codes: '', final_code: '', regular_code: '' })
}
async function saveCategory() {
  const payload = { code: categoryForm.code, label: categoryForm.label, entry_codes: parseCodes(categoryForm.entry_codes), final_code: categoryForm.final_code.trim().toUpperCase() || null, regular_code: categoryForm.regular_code.trim().toUpperCase() || null }
  const saved = await run(() => categoryForm.id ? api.put(`${base()}/categories/${categoryForm.id}`, payload) : api.post(`${base()}/categories`, payload), t('common.saved'))
  if (saved) {
    categoryForm.open = false
  }
}
const deleteCategory = (id: number) => run(() => api.del(`${base()}/categories/${id}`))
const unknownCodes = (category: ClubRankingCategory) => [category.final_code, category.regular_code, ...category.entry_codes.filter(code => !code.endsWith('*'))]
  .filter((code): code is string => !!code && !(data.value?.competitions ?? []).includes(code))

const date = (value: string | null) => value ? new Date(value.replace(' ', 'T')).toLocaleString() : '—'

watch(season, load)
onMounted(async () => {
  await workContext.initContext()
  await loadSeasons()
  await load()
})
</script>

<template>
  <div>
    <AdminPageHeader :title="t('club_ranking.title')" :show-filters="false" />
    <p class="mb-5 max-w-4xl text-base text-header-600 dark:text-header-300">{{ t('club_ranking.intro') }}</p>

    <!-- Season and life cycle (§ 8) -->
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg bg-white p-4 shadow dark:bg-header-900">
      <label class="flex items-center gap-2 text-base font-semibold">{{ t('club_ranking.season') }}
        <select v-model="season" class="rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" data-testid="club-ranking-season">
          <option v-for="item in seasons" :key="item.season" :value="item.season">{{ item.season }} · {{ t(`club_ranking.status.${item.status}`) }}</option>
        </select>
      </label>
      <UButton variant="ghost" icon="i-heroicons-plus" @click="newSeason.open = true">{{ t('club_ranking.new_season') }}</UButton>
      <template v-if="data && actions">
        <span class="rounded-full px-3 py-1 text-sm font-semibold" :class="data.season.status === 'published' ? 'bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-100' : 'bg-header-100 dark:bg-header-800'">
          {{ t(`club_ranking.status.${data.season.status}`) }}
        </span>
        <span class="text-sm text-header-500">{{ t('club_ranking.reference_date') }} : {{ date(data.season.computed_at) }}</span>
        <div class="ml-auto flex flex-wrap gap-2">
          <UButton v-if="actions.compute" :loading="working" icon="i-heroicons-calculator" :disabled="data.scale_errors.length > 0" @click="compute">{{ t('club_ranking.compute') }}</UButton>
          <UButton v-if="actions.compute" variant="outline" :disabled="!actions.validate || working" icon="i-heroicons-check-badge" @click="validateSeason">{{ t('club_ranking.validate') }}</UButton>
          <UButton v-if="actions.publish" :loading="working" icon="i-heroicons-megaphone" @click="publish">{{ t('club_ranking.publish') }}</UButton>
          <UButton v-if="actions.reopen" variant="outline" :loading="working" icon="i-heroicons-arrow-uturn-left" @click="reopen">{{ t('club_ranking.reopen') }}</UButton>
          <UButton variant="outline" icon="i-heroicons-document-arrow-down" @click="pdf('public')">{{ t('club_ranking.pdf_public', { n: data.season.top_n }) }}</UButton>
          <UButton variant="outline" icon="i-heroicons-document-text" @click="pdf('work')">{{ t('club_ranking.pdf_work') }}</UButton>
        </div>
      </template>
    </div>

    <p v-if="!loading && seasons.length === 0" class="text-base">{{ t('club_ranking.no_season') }}</p>

    <template v-if="data">
      <p v-if="data.scale_errors.length" class="mb-3 rounded-md bg-red-50 px-4 py-3 text-base text-red-900 dark:bg-red-950/40 dark:text-red-100" role="alert">
        <b>{{ t('club_ranking.scale_invalid') }}</b>
        <span v-for="error in data.scale_errors" :key="error" class="block">{{ error }}</span>
      </p>
      <p v-if="data.season.stale" class="mb-3 rounded-md bg-amber-50 px-4 py-3 text-base text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">{{ t('club_ranking.stale') }}</p>
      <p v-if="!actions?.editable" class="mb-3 rounded-md bg-primary-50 px-4 py-3 text-base text-primary-900 dark:bg-primary-950/40 dark:text-primary-100">{{ t('club_ranking.read_only') }}</p>

      <div class="mb-4 flex gap-1 overflow-x-auto border-b border-header-200 dark:border-header-700" role="tablist">
        <button
          v-for="item in TABS"
          :key="item"
          type="button"
          role="tab"
          :aria-selected="tab === item"
          class="whitespace-nowrap border-b-2 px-5 py-2.5 text-base font-semibold"
          :class="tab === item ? 'border-primary-600 text-primary-700 dark:text-primary-300' : 'border-transparent text-header-500'"
          @click="tab = item"
        >
          {{ t(`club_ranking.tabs.${item}`) }}
          <span v-if="item === 'alerts' && data.alerts.length" class="ml-1 rounded-full px-2 text-sm" :class="blockingAlerts ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-900'">{{ data.alerts.length }}</span>
        </button>
      </div>

      <!-- Ranking: clubs × rules, a click shows the lines of detail -->
      <section v-if="tab === 'ranking'" class="rounded-lg bg-white p-5 shadow dark:bg-header-900">
        <div class="mb-4 flex flex-wrap items-center gap-3">
          <input v-model="query" type="search" class="w-72 rounded border border-header-300 px-3 py-1.5 dark:bg-header-800" :placeholder="t('club_ranking.search')">
          <span class="text-sm text-header-500">{{ t('club_ranking.clubs_count', { n: shownStandings.length }, shownStandings.length) }}</span>
          <UButton class="ml-auto" variant="ghost" icon="i-heroicons-table-cells" @click="exportStandings">{{ t('club_ranking.export_standings') }}</UButton>
          <UButton variant="ghost" icon="i-heroicons-list-bullet" @click="exportLines">{{ t('club_ranking.export_lines') }}</UButton>
        </div>
        <p v-if="data.standings.length === 0" class="py-6 text-base text-header-500">{{ t('club_ranking.no_standing') }}</p>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm [&_td]:px-2 [&_th]:px-2" data-testid="club-ranking-table">
            <thead>
              <tr class="text-header-600 dark:text-header-300">
                <th colspan="3" />
                <th v-for="group in groups" :key="group.article" :colspan="group.rules.length" class="border-l border-header-200 text-center dark:border-header-700">{{ group.article }}</th>
              </tr>
              <tr class="text-left text-header-600 dark:text-header-300">
                <th class="pb-2">{{ t('club_ranking.rank') }}</th>
                <th class="min-w-56 pb-2">{{ t('club_ranking.club') }}</th>
                <th class="pb-2 text-right">{{ t('club_ranking.total') }}</th>
                <th v-for="rule in sortedRules" :key="rule.code" class="pb-2 text-right whitespace-nowrap" :title="`${rule.label} (${rule.points} pts)`">{{ rule.code }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in shownStandings" :key="row.club" class="border-t border-header-100 hover:bg-header-50/60 dark:border-header-800 dark:hover:bg-header-800/40">
                <td class="py-2 font-bold tabular-nums">{{ row.rank }}</td>
                <td class="py-2">
                  <button type="button" class="text-left font-semibold hover:underline" @click="showDetail(row)">{{ row.label }}</button>
                  <span class="block text-xs text-header-500">{{ row.club }} · {{ row.department }}</span>
                </td>
                <td class="text-right text-base font-bold tabular-nums">{{ row.total }}</td>
                <td v-for="rule in sortedRules" :key="rule.code" class="text-right tabular-nums">
                  <button v-if="row.by_rule[rule.code]" type="button" class="underline decoration-dotted underline-offset-4 hover:text-primary-700" @click="showDetail(row, rule)">{{ row.by_rule[rule.code] }}</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Alerts of the last calculation (§ 8 step 2) -->
      <section v-else-if="tab === 'alerts'" class="space-y-5 rounded-lg bg-white p-5 shadow dark:bg-header-900">
        <p v-if="data.alerts.length === 0" class="text-base text-header-500">{{ t('club_ranking.no_alert') }}</p>
        <div v-if="officials.length" class="rounded-md border border-amber-300 p-4 dark:border-amber-800">
          <h2 class="mb-2 text-lg font-bold">{{ t('club_ranking.officials_title') }}</h2>
          <p class="mb-3 text-sm text-header-600 dark:text-header-300">{{ t('club_ranking.officials_help') }}</p>
          <div v-for="official in officials" :key="official.name" class="mb-3 grid items-start gap-3 md:grid-cols-[16rem_1fr_auto]">
            <span><b>{{ official.name }}</b><span class="block text-xs text-header-600 dark:text-header-300">{{ t('club_ranking.gamedays', { ids: '' }) }}<a v-for="id in official.gamedays" :key="id" :href="router.resolve(gamedayLink(id, official.competitions[String(id)]).to).href" target="_blank" rel="noopener" class="mr-1 underline">{{ id }}</a></span></span>
            <AdminPlayerAutocomplete :model-value="aliasChoice[official.name] ?? null" :placeholder="t('club_ranking.find_licensee')" :disabled="!actions?.editable" @update:model-value="value => aliasChoice[official.name] = value" />
            <UButton :disabled="!aliasChoice[official.name] || !actions?.editable" @click="saveAlias(official.name)">{{ t('club_ranking.match') }}</UButton>
          </div>
        </div>
        <div v-for="group in alertGroups" :key="group.kind">
          <h2 class="mb-2 text-base font-bold" :class="group.blocking ? 'text-red-700 dark:text-red-300' : ''">{{ t(`club_ranking.alerts.${group.kind}`) }} ({{ group.items.length }})</h2>
          <ul class="list-disc space-y-1 pl-6 text-sm">
            <li v-for="(item, index) in group.items" :key="index">
              {{ item.message }}
              <a v-for="link in alertLinks(item)" :key="link.to" :href="router.resolve(link.to).href" target="_blank" rel="noopener" class="ml-2 whitespace-nowrap font-semibold text-primary-700 underline dark:text-primary-300">{{ link.label }} ↗</a>
            </li>
          </ul>
        </div>
      </section>

      <!-- Manual entries and ententes (§ 7) -->
      <section v-else-if="tab === 'entries'" class="space-y-6 rounded-lg bg-white p-5 shadow dark:bg-header-900">
        <div v-for="rule in manualRules" :key="rule.code" class="border-b border-header-100 pb-5 dark:border-header-800">
          <h2 class="text-lg font-bold">{{ rule.code }} · {{ rule.label }} <span class="text-sm font-normal text-header-500">({{ rule.points }} pts)</span></h2>
          <p v-if="rule.note" class="mb-2 text-sm text-header-500">{{ rule.note }}</p>
          <table v-if="entriesOf(rule.code).length" class="mb-3 w-full text-sm [&_td]:px-2 [&_td]:py-1">
            <tbody>
              <tr v-for="entry in entriesOf(rule.code)" :key="entry.id" class="border-t border-header-100 dark:border-header-800">
                <td>{{ entry.name || '—' }} <span v-if="entry.matric" class="text-header-500">{{ entry.matric }}</span></td>
                <td>{{ entry.club_label ?? entry.club }}</td>
                <td>{{ entry.detail }}</td>
                <td class="text-right"><UButton v-if="actions?.editable" size="xs" variant="ghost" color="error" icon="i-heroicons-trash" :aria-label="t('common.delete')" @click="deleteEntry(entry.id)" /></td>
              </tr>
            </tbody>
          </table>
          <form v-if="actions?.editable" class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]" @submit.prevent="addEntry(rule.code)" @focusin="entryForm.rule = rule.code">
            <AdminPlayerAutocomplete :model-value="entryForm.rule === rule.code ? entryForm.player : null" :placeholder="t('club_ranking.licensee')" @update:model-value="value => { entryForm.rule = rule.code; entryForm.player = value }" />
            <AdminClubAutocomplete :model-value="entryForm.rule === rule.code ? entryForm.club : ''" :placeholder="t('club_ranking.or_club')" :disabled="entryForm.rule === rule.code && !!entryForm.player" @update:model-value="value => { entryForm.rule = rule.code; entryForm.club = value }" />
            <input v-model="entryForm.detail" class="rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :placeholder="t('club_ranking.detail')">
            <UButton type="submit" :disabled="entryForm.rule !== rule.code || (!entryForm.player && !entryForm.club)">{{ t('common.add') }}</UButton>
          </form>
        </div>

        <div>
          <h2 class="text-lg font-bold">{{ t('club_ranking.ententes_title') }}</h2>
          <p class="mb-2 text-sm text-header-500">{{ t('club_ranking.ententes_help') }}</p>
          <ul class="mb-3 space-y-1 text-sm">
            <li v-for="entente in data.ententes" :key="entente.team_number">
              <b>{{ entente.label }}</b> ({{ entente.team_number }}, {{ entente.club }}) +
              <span v-for="partner in entente.partners" :key="partner" class="mr-2">
                {{ partner }}<UButton v-if="actions?.editable" size="xs" variant="ghost" color="error" icon="i-heroicons-x-mark" :aria-label="t('common.delete')" @click="deleteEntente(entente.team_number, partner)" />
              </span>
            </li>
          </ul>
          <form v-if="actions?.editable" class="grid gap-3 md:grid-cols-[12rem_1fr_auto]" @submit.prevent="addEntente">
            <AdminTeamAutocomplete v-model="ententeForm.team" :placeholder="t('club_ranking.team_search')" />
            <AdminClubAutocomplete v-model="ententeForm.club" :placeholder="t('club_ranking.partner_club')" />
            <UButton type="submit" :disabled="!ententeForm.team || !ententeForm.club">{{ t('common.add') }}</UButton>
          </form>
        </div>
      </section>

      <!-- Adjustments: reason required (CNC-26) -->
      <section v-else-if="tab === 'adjustments'" class="space-y-4 rounded-lg bg-white p-5 shadow dark:bg-header-900">
        <p class="text-sm text-header-500">{{ t('club_ranking.adjustments_help') }}</p>
        <table v-if="data.adjustments.length" class="w-full text-sm [&_td]:px-2 [&_td]:py-1">
          <tbody>
            <tr v-for="item in data.adjustments" :key="item.id" class="border-t border-header-100 dark:border-header-800">
              <td>{{ item.rule }}</td>
              <td>{{ clubLabel(item.club) }} <span class="text-header-500">{{ item.club }}</span></td>
              <td class="text-right font-bold tabular-nums">{{ item.points > 0 ? '+' : '' }}{{ item.points }}</td>
              <td>{{ item.reason }}</td>
              <td class="text-header-500">{{ item.created_by }} · {{ date(item.created_at) }}</td>
              <td class="text-right"><UButton v-if="actions?.editable" size="xs" variant="ghost" color="error" icon="i-heroicons-trash" :aria-label="t('common.delete')" @click="deleteAdjustment(item.id)" /></td>
            </tr>
          </tbody>
        </table>
        <form v-if="actions?.editable" class="grid gap-3 md:grid-cols-[10rem_1fr_7rem_2fr_auto]" @submit.prevent="addAdjustment">
          <select v-model="adjustmentForm.rule" class="rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" required>
            <option value="" disabled>{{ t('club_ranking.rule') }}</option>
            <option v-for="rule in sortedRules" :key="rule.code" :value="rule.code">{{ rule.code }} {{ rule.short_label }}</option>
          </select>
          <AdminClubAutocomplete v-model="adjustmentForm.club" :placeholder="t('club_ranking.club')" />
          <input v-model.number="adjustmentForm.points" type="number" class="rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :aria-label="t('club_ranking.points')">
          <input v-model="adjustmentForm.reason" class="rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :placeholder="t('club_ranking.reason')" required>
          <UButton type="submit" :disabled="!adjustmentForm.rule || !adjustmentForm.club || !adjustmentForm.points || !adjustmentForm.reason.trim()">{{ t('common.add') }}</UButton>
        </form>
      </section>

      <!-- Scale of the season (§ 3) -->
      <section v-else-if="tab === 'scale'" class="space-y-6 rounded-lg bg-white p-5 shadow dark:bg-header-900">
        <form class="grid gap-3 md:grid-cols-[8rem_1fr_1fr_auto] md:items-end" @submit.prevent="saveSettings">
          <label class="text-sm">{{ t('club_ranking.top_n') }}<input v-model.number="settings.top_n" type="number" min="1" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :disabled="!actions?.editable"></label>
          <label class="text-sm">{{ t('club_ranking.national_prefixes') }}<input v-model="settings.national_prefixes" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :disabled="!actions?.editable"></label>
          <label class="text-sm">{{ t('club_ranking.excluded_competitions') }}<input v-model="settings.excluded_competitions" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :disabled="!actions?.editable"></label>
          <UButton v-if="actions?.editable" type="submit">{{ t('common.save') }}</UButton>
        </form>

        <div v-if="data.rules.length === 0 && actions?.editable" class="flex flex-wrap items-center gap-3 rounded-md bg-primary-50 p-4 dark:bg-primary-950/40">
          {{ t('club_ranking.copy_scale_help') }}
          <select v-model="copyFrom" class="rounded border border-header-300 px-2 py-1.5 dark:bg-header-800">
            <option v-for="item in seasons.filter(other => other.season !== season)" :key="item.season" :value="item.season">{{ item.season }}</option>
          </select>
          <UButton :disabled="!copyFrom" @click="copyScale">{{ t('club_ranking.copy_scale') }}</UButton>
        </div>

        <div>
          <div class="mb-2 flex items-center justify-between">
            <h2 class="text-lg font-bold">{{ t('club_ranking.rules_title') }}</h2>
            <UButton v-if="actions?.editable" size="sm" icon="i-heroicons-plus" @click="editRule()">{{ t('common.add') }}</UButton>
          </div>
          <table class="w-full text-sm [&_td]:px-2 [&_td]:py-1.5 [&_th]:px-2">
            <thead><tr class="text-left text-header-600 dark:text-header-300"><th>{{ t('club_ranking.rule') }}</th><th>{{ t('club_ranking.label') }}</th><th class="text-right">{{ t('club_ranking.points') }}</th><th>{{ t('club_ranking.type') }}</th><th /></tr></thead>
            <tbody>
              <tr v-for="rule in sortedRules" :key="rule.id" class="border-t border-header-100 align-top dark:border-header-800">
                <td class="font-semibold">{{ rule.code }}</td>
                <td>{{ rule.label }}<span v-if="rule.note" class="block text-xs text-header-500">{{ rule.note }}</span></td>
                <td class="text-right font-bold tabular-nums">{{ rule.points }}</td>
                <td><code class="text-xs">{{ rule.type }}</code></td>
                <td class="whitespace-nowrap text-right">
                  <UButton size="xs" variant="outline" @click="editRule(rule)">{{ actions?.editable ? t('common.edit') : t('club_ranking.see') }}</UButton>
                  <UButton v-if="actions?.editable" size="xs" variant="ghost" color="error" icon="i-heroicons-trash" :aria-label="t('common.delete')" @click="deleteRule(rule.id)" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div>
          <div class="mb-2 flex items-center justify-between">
            <h2 class="text-lg font-bold">{{ t('club_ranking.categories_title') }}</h2>
            <UButton v-if="actions?.editable" size="sm" icon="i-heroicons-plus" @click="editCategory()">{{ t('common.add') }}</UButton>
          </div>
          <p class="mb-2 text-sm text-header-600 dark:text-header-300">{{ t('club_ranking.categories_help') }}</p>
          <table class="w-full text-sm [&_td]:px-2 [&_td]:py-1.5 [&_th]:px-2">
            <thead><tr class="text-left text-header-600 dark:text-header-300"><th>{{ t('club_ranking.category') }}</th><th>{{ t('club_ranking.entry_codes') }}</th><th>{{ t('club_ranking.regular_code') }}</th><th>{{ t('club_ranking.final_code') }}</th><th /></tr></thead>
            <tbody>
              <tr v-for="category in data.categories" :key="category.id" class="border-t border-header-100 dark:border-header-800">
                <td><b>{{ category.code }}</b> <span class="text-header-500">{{ category.label }}</span></td>
                <td>{{ category.entry_codes.join(', ') || '—' }}</td>
                <td>{{ category.regular_code ?? '—' }}</td>
                <td>{{ category.final_code ?? '—' }}</td>
                <td class="whitespace-nowrap text-right">
                  <span v-if="unknownCodes(category).length" class="mr-2 text-xs font-semibold text-amber-700 dark:text-amber-300" :title="t('club_ranking.unknown_codes_help')">⚠ {{ unknownCodes(category).join(', ') }}</span>
                  <UButton v-if="actions?.editable" size="xs" variant="outline" @click="editCategory(category)">{{ t('common.edit') }}</UButton>
                  <UButton v-if="actions?.editable" size="xs" variant="ghost" color="error" icon="i-heroicons-trash" :aria-label="t('common.delete')" @click="deleteCategory(category.id)" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Publications: frozen snapshots (§ 8 steps 5-6) -->
      <section v-else-if="tab === 'publications'" class="rounded-lg bg-white p-5 shadow dark:bg-header-900">
        <p v-if="data.publications.length === 0" class="text-base text-header-500">{{ t('club_ranking.no_publication') }}</p>
        <ul class="space-y-2">
          <li v-for="item in data.publications" :key="item.version" class="flex flex-wrap items-center gap-3">
            <b>{{ t('club_ranking.version', { n: item.version }) }}</b>
            <span class="text-sm text-header-500">{{ date(item.published_at) }} · {{ item.published_by }}</span>
            <UButton size="xs" variant="outline" icon="i-heroicons-document-arrow-down" @click="pdf('public', item.version)">PDF</UButton>
          </li>
        </ul>
      </section>
    </template>

    <!-- Detail of a cell -->
    <AdminModal :open="detail.open" :title="detail.title" max-width="3xl" @close="detail.open = false">
      <table class="w-full text-sm [&_td]:px-2 [&_td]:py-1">
        <tbody>
          <tr v-for="(line, index) in detail.lines" :key="index" class="border-t border-header-100 align-top dark:border-header-800">
            <td class="font-semibold">{{ line.rule }}</td>
            <td>{{ line.reason }}</td>
            <td class="text-right font-bold tabular-nums">{{ line.points }}</td>
          </tr>
        </tbody>
      </table>
    </AdminModal>

    <!-- New season -->
    <AdminModal :open="newSeason.open" :title="t('club_ranking.new_season')" @close="newSeason.open = false">
      <form class="space-y-3" @submit.prevent="createSeason">
        <label class="block text-sm">{{ t('club_ranking.season') }}<input v-model="newSeason.season" pattern="\d{4}" required class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800"></label>
        <label class="block text-sm">{{ t('club_ranking.copy_from') }}
          <select v-model="newSeason.copyFrom" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800">
            <option value="">—</option>
            <option v-for="item in seasons" :key="item.season" :value="item.season">{{ item.season }}</option>
          </select>
        </label>
        <UButton type="submit" :loading="working">{{ t('common.add') }}</UButton>
      </form>
    </AdminModal>

    <!-- Rule of the scale -->
    <AdminModal :open="ruleForm.open" :title="t('club_ranking.rule')" max-width="2xl" @close="ruleForm.open = false">
      <form class="grid gap-3 md:grid-cols-4" @submit.prevent="saveRule">
        <label class="text-sm">{{ t('club_ranking.article') }}<input v-model="ruleForm.article" pattern="7\.[1-9]" required class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :disabled="!actions?.editable"></label>
        <label class="text-sm">{{ t('club_ranking.position') }}<input v-model.number="ruleForm.rank" type="number" min="1" required class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :disabled="!actions?.editable"></label>
        <label class="text-sm">{{ t('club_ranking.points') }}<input v-model.number="ruleForm.points" type="number" min="0" required class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :disabled="!actions?.editable"></label>
        <label class="text-sm">{{ t('club_ranking.type') }}
          <select v-model="ruleForm.type" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :disabled="!actions?.editable">
            <option v-for="type in data?.types ?? []" :key="type" :value="type">{{ type }}</option>
          </select>
        </label>
        <label class="text-sm md:col-span-4">{{ t('club_ranking.label') }}<textarea v-model="ruleForm.label" rows="2" required class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :disabled="!actions?.editable" /></label>
        <label class="text-sm md:col-span-2">{{ t('club_ranking.short_label') }}<input v-model="ruleForm.short_label" maxlength="40" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :disabled="!actions?.editable"></label>
        <label class="text-sm md:col-span-2">{{ t('club_ranking.note') }}<input v-model="ruleForm.note" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" :disabled="!actions?.editable"></label>
        <label class="text-sm md:col-span-4">{{ t('club_ranking.params') }}
          <textarea v-model="ruleForm.params" rows="8" spellcheck="false" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 font-mono text-xs dark:bg-header-800" :disabled="!actions?.editable" />
          <span class="text-xs text-header-500">{{ t('club_ranking.params_help') }}</span>
        </label>
        <p v-if="ruleForm.error" class="text-sm text-red-600 md:col-span-4">{{ ruleForm.error }}</p>
        <UButton v-if="actions?.editable" type="submit" class="md:col-span-4 md:justify-self-start">{{ t('common.save') }}</UButton>
      </form>
    </AdminModal>

    <!-- Category of the season -->
    <AdminModal :open="categoryForm.open" :title="t('club_ranking.category')" max-width="xl" @close="categoryForm.open = false">
      <form class="grid gap-3 md:grid-cols-2" @submit.prevent="saveCategory">
        <label class="text-sm">{{ t('club_ranking.code') }}<input v-model="categoryForm.code" required pattern="[A-Za-z0-9_]{1,20}" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800"></label>
        <label class="text-sm">{{ t('club_ranking.label') }}<input v-model="categoryForm.label" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800"></label>
        <label class="text-sm md:col-span-2">{{ t('club_ranking.entry_codes') }}<input v-model="categoryForm.entry_codes" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" placeholder="N15*, CF15"></label>
        <label class="text-sm">{{ t('club_ranking.regular_code') }}<input v-model="categoryForm.regular_code" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800"></label>
        <label class="text-sm">{{ t('club_ranking.final_code') }}<input v-model="categoryForm.final_code" class="mt-1 w-full rounded border border-header-300 px-2 py-1.5 dark:bg-header-800"></label>
        <p class="text-xs text-header-600 dark:text-header-300 md:col-span-2">{{ t('club_ranking.categories_help') }}</p>
        <UButton type="submit" class="md:justify-self-start">{{ t('common.save') }}</UButton>
      </form>
    </AdminModal>
  </div>
</template>
