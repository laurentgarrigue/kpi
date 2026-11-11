<script setup lang="ts">
import { awardsBasis, awardsPerGame, changedThresholds, formatStatValue, formatThreshold, humanizeKey, isRefereeKind, matchesQuery, statRowText, statSubject, thresholdChanges, thresholdsUsed, type AdminMedalItem, type Thresholds } from '~/utils/medals'

/**
 * Admin of the match medals, profiles 1 and 2 (DOC/specs/public/FEATURE_MATCH_MEDALS.md § 9.3, maquette
 * `admin-medailles`): catalogue (names, explanations, surfaces, status), frozen seasons, every active statistic of
 * the work context, referee statistics.
 */
definePageMeta({
  layout: 'admin',
  middleware: ['auth'],
})

interface SeasonRow { season: string, thresholds: Record<string, unknown>, differs: boolean, rules_version: number, outdated: boolean, computed_at: string, games_count: number, reliable_games_count: number }
interface Job { season: string, status: 'running' | 'done' | 'error', computed?: number, message?: string }
interface Limit { min: number, max: number }
interface StatResult { kind: string, columns: { key: string, type: string }[], rows: Record<string, unknown>[], summary: Record<string, unknown>, games_count: number, reliable_games_count: number }
type Tab = 'catalogue' | 'seasons' | 'stats' | 'referees'

const { t, te, locale } = useI18n()
const api = useApi()
const toast = useToast()
const authStore = useAuthStore()
const workContext = useWorkContextStore()

// Profile guard: only profiles <= 2 (MED-40), api2 refuses the others anyway
if (authStore.profile > 2) {
  navigateTo('/')
}

const TABS: Tab[] = ['catalogue', 'seasons', 'stats', 'referees']
const tab = ref<Tab>('catalogue')
const loading = ref(false)
const items = ref<AdminMedalItem[]>([])
const typeFilter = ref<'' | AdminMedalItem['type']>('')
const unpublishedOnly = ref(false)
const seasons = ref<SeasonRow[]>([])
const rulesVersion = ref(0)
const currentThresholds = ref<Thresholds>({})
const defaultThresholds = ref<Thresholds>({})
const overrides = ref<Thresholds>({})
const limits = ref<Record<string, Limit>>({})
const cardCodes = ref<string[]>([])
const uncomputed = ref<string[]>([])
// Profile 1 changes the thresholds, recomputes and deletes seasons (§ 9.3); profile 2 reads
const canEdit = computed(() => authStore.profile === 1)
const draft = reactive<Record<string, number | string | string[]>>({})
const saving = ref(false)
const pending = ref<{ action: 'compute' | 'delete', season: string, games: number | null } | null>(null)
const working = ref(false)
// A full computation takes minutes: api2 runs it in the background and the page follows its state
const job = ref<Job | null>(null)
const computing = computed(() => job.value?.status === 'running')
let pollTimer: ReturnType<typeof setInterval> | undefined
const queries = reactive<Record<Tab, string>>({ catalogue: '', seasons: '', stats: '', referees: '' })
const kinds = ref<{ code: string, surfaces: string[] }[]>([])
const kind = ref('')
const stat = ref<StatResult | null>(null)
const editing = ref<AdminMedalItem | null>(null)
const editDraft = reactive({ name_fr: '', name_en: '', explanation_fr: '', explanation_en: '' })

const name = (item: AdminMedalItem) => (locale.value === 'en' ? item.name_en : item.name_fr)
const byCode = computed(() => new Map(items.value.map(item => [item.code, item])))
const explanation = (item: AdminMedalItem) => (locale.value === 'en' ? item.explanation_en : item.explanation_fr)
const shownItems = computed(() => items.value.filter(item =>
  (!typeFilter.value || item.type === typeFilter.value)
  && (!unpublishedOnly.value || (!item.published_app2 && !item.published_app3))
  && matchesQuery(queries.catalogue, item.name_fr, item.name_en, item.code, explanation(item))))
const thresholdsOfItem = (item: AdminMedalItem) => thresholdsUsed(`${item.explanation_fr_template} ${item.explanation_en_template}`, currentThresholds.value)

const shownSeasons = computed(() => seasons.value.flatMap((row) => {
  const changed = new Map(changedThresholds(row.thresholds, currentThresholds.value).map(entry => [entry.key, entry]))
  const entries = Object.entries(row.thresholds).map(([key, value]) => ({ key, value, current: changed.get(key)?.current, changed: changed.has(key) }))
  const found = entries.filter(entry => matchesQuery(queries.seasons, row.season, humanizeKey(entry.key), entry.key, formatThreshold(entry.value)))
  return matchesQuery(queries.seasons, row.season) || found.length > 0
    ? [{ ...row, entries: matchesQuery(queries.seasons, row.season) ? entries : found, changedCount: changed.size }]
    : []
}))
const shownRows = computed(() => (stat.value?.rows ?? []).filter(row => matchesQuery(queries[tab.value], statRowText(row, stat.value!.columns))))
const column = (key: string) => (te(`medals.cols.${key}`) ? t(`medals.cols.${key}`) : humanizeKey(key))
const number = (value: number) => value.toLocaleString(locale.value)
const contextSeason = computed(() => seasons.value.find(row => row.season === workContext.season))
/** Awards of the season of the work context, against the games they could be given on (§ 9.3). */
const awardsOf = (item: AdminMedalItem) => {
  const basis = awardsBasis(item.data_level, contextSeason.value)
  const rate = basis ? awardsPerGame(item.awards, basis.games) : null
  return basis && rate !== null ? { games: number(basis.games), reliable: basis.reliable, rate: rate.toLocaleString(locale.value, { maximumFractionDigits: 2 }) } : null
}
const showDefaults = ref(false)
const share = (row: SeasonRow) => (row.games_count ? Math.round((100 * row.reliable_games_count) / row.games_count) : 0)
const computedAt = (row: SeasonRow) => new Date(row.computed_at.replace(' ', 'T')).toLocaleString(locale.value, { dateStyle: 'long', timeStyle: 'short' })
const medalsOf = (row: Record<string, unknown>) => Object.entries((row.medals ?? {}) as Record<string, number>).flatMap(([code, count]) => (byCode.value.has(code) ? [{ item: byCode.value.get(code)!, count }] : []))
const tabKinds = computed(() => kinds.value.filter(entry => isRefereeKind(entry.code) === (tab.value === 'referees')))
const variables = (template: string) => [...new Set(template.match(/\{\w+\}/g) ?? [])]

async function loadCatalogue() {
  const data = await api.get<{ items: AdminMedalItem[] }>('/admin/medals/catalog', { season: workContext.season })
  items.value = data?.items ?? []
}

async function loadSeasons() {
  const data = await api.get<{ rules_version: number, thresholds: Thresholds, default_thresholds: Thresholds, overrides: Thresholds, limits: Record<string, Limit>, card_codes: string[], uncomputed: string[], job: Job | null, seasons: SeasonRow[] }>('/admin/medals/seasons')
  rulesVersion.value = data?.rules_version ?? 0
  currentThresholds.value = data?.thresholds ?? {}
  defaultThresholds.value = data?.default_thresholds ?? {}
  overrides.value = data?.overrides ?? {}
  limits.value = data?.limits ?? {}
  cardCodes.value = data?.card_codes ?? []
  uncomputed.value = data?.uncomputed ?? []
  job.value = data?.job ?? null
  resetDraft()
  seasons.value = data?.seasons ?? []
}

function resetDraft() {
  for (const key of Object.keys(draft)) {
    Reflect.deleteProperty(draft, key)
  }
  for (const [key, value] of Object.entries(currentThresholds.value)) {
    draft[key] = Array.isArray(value) ? [...value as string[]] : value as number
  }
}

const changes = computed(() => thresholdChanges(draft, currentThresholds.value))
const invalidKeys = computed(() => Object.entries(limits.value).filter(([key, range]) => {
  const value = draft[key]
  return value === '' || value === undefined || !Number.isInteger(Number(value)) || Number(value) < range.min || Number(value) > range.max
}).map(([key]) => key))
const outdatedSeasons = computed(() => seasons.value.filter(row => changedThresholds(row.thresholds, currentThresholds.value).length > 0).length)
const isOverridden = (key: string) => key in overrides.value
const toggleCard = (card: string) => {
  const cards = draft.permanentCards as string[]
  draft.permanentCards = cards.includes(card) ? cards.filter(item => item !== card) : [...cards, card]
}

async function saveThresholds() {
  saving.value = true
  try {
    await api.put('/admin/medals/thresholds', { changes: changes.value })
    toast.add({ title: t('common.success'), description: t('medals.thresholds_saved'), color: 'success', duration: 3000 })
    await loadSeasons()
  } catch {
    toast.add({ title: t('common.error'), description: t('medals.thresholds_error'), color: 'error' })
  } finally {
    saving.value = false
  }
}

async function resetThresholds() {
  saving.value = true
  try {
    await api.del('/admin/medals/thresholds')
    toast.add({ title: t('common.success'), description: t('medals.thresholds_reset_done'), color: 'success', duration: 3000 })
    await loadSeasons()
  } finally {
    saving.value = false
  }
}

/** Runs the action the user confirmed in the modal, then refreshes what depends on it. */
async function confirmAction() {
  if (!pending.value) {
    return
  }
  const { action, season } = pending.value
  working.value = true
  try {
    if (action === 'compute') {
      await api.post(`/admin/medals/seasons/${season}/compute`)
      pending.value = null
      await loadSeasons()
      followJob()
      return
    } else {
      const result = await api.del<{ removed: number }>(`/admin/medals/seasons/${season}`)
      toast.add({ title: t('common.success'), description: t('medals.deleted_done', { season, n: result.removed }, result.removed), color: 'success', duration: 4000 })
    }
    pending.value = null
    await Promise.all([loadSeasons(), loadCatalogue(), loadStat()])
  } catch (error) {
    const conflict = (error as { error?: string } | null)?.error === 'already_running'
    toast.add({ title: t('common.error'), description: t(conflict ? 'medals.compute_already' : 'medals.action_error'), color: 'error' })
  } finally {
    working.value = false
  }
}

/** Polls the job every few seconds until it ends, then says how it went and refreshes what depends on it. */
function followJob() {
  clearInterval(pollTimer)
  pollTimer = setInterval(async () => {
    await loadSeasons()
    if (job.value?.status === 'running') {
      return
    }
    clearInterval(pollTimer)
    if (job.value?.status === 'done') {
      toast.add({ title: t('common.success'), description: t('medals.computed_done', { season: job.value.season, n: job.value.computed ?? 0 }, job.value.computed ?? 0), color: 'success', duration: 5000 })
    } else if (job.value) {
      toast.add({ title: t('common.error'), description: t('medals.compute_failed', { season: job.value.season, message: job.value.message ?? '' }), color: 'error' })
    }
    await Promise.all([loadCatalogue(), loadStat()])
  }, 3000)
}
onBeforeUnmount(() => clearInterval(pollTimer))

async function loadKinds() {
  const data = await api.get<{ kinds: { code: string, surfaces: string[] }[] }>('/admin/medals/stats')
  kinds.value = data?.kinds ?? []
}

async function loadStat() {
  stat.value = null
  if (!kind.value || !workContext.season || workContext.competitionCodes.length === 0) {
    return
  }
  loading.value = true
  try {
    stat.value = await api.get<StatResult>(`/admin/medals/stats/${kind.value}`, {
      season: workContext.season,
      competitions: workContext.competitionCodes.join(','),
    })
  } finally {
    loading.value = false
  }
}

/** Saves a change of the catalogue (journalled by api2, MED-43); publishing needs no recomputation (MED-36). */
async function save(item: AdminMedalItem, changes: Partial<AdminMedalItem>) {
  try {
    await api.patch(`/admin/medals/catalog/${item.code}`, changes)
    Object.assign(item, changes)
    toast.add({ title: t('common.success'), description: t('medals.saved'), color: 'success', duration: 2500 })
  } catch {
    toast.add({ title: t('common.error'), description: t('medals.save_error'), color: 'error' })
    await loadCatalogue()
  }
}

function edit(item: AdminMedalItem) {
  editing.value = item
  Object.assign(editDraft, { name_fr: item.name_fr, name_en: item.name_en, explanation_fr: item.explanation_fr_template, explanation_en: item.explanation_en_template })
}

async function saveEdit() {
  if (!editing.value) {
    return
  }
  await save(editing.value, { ...editDraft, explanation_fr_template: editDraft.explanation_fr, explanation_en_template: editDraft.explanation_en })
  editing.value = null
  await loadCatalogue()
}

watch(tab, (value) => {
  if (value === 'stats' || value === 'referees') {
    kind.value = tabKinds.value[0]?.code ?? ''
  }
})
watch(kind, loadStat)
watch(() => [workContext.season, workContext.competitionCodes.join(',')], () => {
  loadCatalogue()
  loadStat()
})

onMounted(async () => {
  // Like the other pages: restores the work context (season, competitions) from the browser after a reload
  await workContext.initContext()
  await Promise.all([loadCatalogue(), loadSeasons(), loadKinds()])
  if (computing.value) {
    followJob()
  }
})
</script>

<template>
  <div>
    <AdminPageHeader :title="t('medals.title')" />
    <p class="mb-5 max-w-4xl text-base text-header-600 dark:text-header-300">{{ t('medals.intro') }}</p>

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
        {{ t(`medals.tabs.${item}`) }}
      </button>
    </div>

    <!-- Catalogue: surfaces, status, texts (§ 9.3) -->
    <section v-if="tab === 'catalogue'" class="rounded-lg bg-white p-5 shadow dark:bg-header-900">
      <div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-3 text-base">
        <MedalSearch v-model="queries.catalogue" :placeholder="t('medals.search_catalogue')" :count="shownItems.length" :total="items.length" />
        <label class="flex items-center gap-2">{{ t('medals.type') }}
          <select v-model="typeFilter" class="rounded border border-header-300 px-2 py-1.5 dark:bg-header-800">
            <option value="">{{ t('medals.all') }}</option>
            <option v-for="type in ['medal', 'label', 'stat']" :key="type" :value="type">{{ t(`medals.types.${type}`) }}</option>
          </select>
        </label>
        <label class="flex items-center gap-2"><input v-model="unpublishedOnly" type="checkbox" class="size-4"> {{ t('medals.unpublished_only') }}</label>
      </div>
      <p class="mb-4 text-sm text-header-500">{{ t('medals.publish_note') }}</p>
      <p v-if="shownItems.length === 0" class="py-6 text-base text-header-500">{{ t('medals.search_empty') }}</p>
      <div v-else class="overflow-x-auto xl:overflow-visible">
        <table class="w-full text-base [&_td]:px-3 [&_th]:px-3">
          <thead>
            <tr class="text-left text-sm font-semibold text-header-600 dark:text-header-300">
              <th class="pb-2">{{ t('medals.name') }}</th>
              <th class="pb-2">{{ t('medals.type') }}</th>
              <th class="pb-2">{{ t('medals.data') }}</th>
              <th class="pb-2">{{ t('medals.current_thresholds') }}</th>
              <th class="pb-2 text-right">{{ t('medals.awards', { season: workContext.season }) }}</th>
              <th class="pb-2">app2</th>
              <th class="pb-2">app3</th>
              <th class="pb-2">{{ t('medals.status') }}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in shownItems" :key="item.code" class="border-t border-header-100 hover:bg-header-50/60 dark:border-header-800 dark:hover:bg-header-800/40" :class="{ 'opacity-50': item.status === 'retired' }">
              <td class="py-3">
                <!-- Hover or focus: the explanation in the language of the interface -->
                <div class="group relative flex w-fit items-center gap-3" tabindex="0" :aria-label="`${name(item)}. ${explanation(item)}`">
                  <MedalShield :item="item" :size="40" />
                  <span>
                    <b>{{ item.name_fr }}</b><span v-if="item.name_fr !== item.name_en" class="italic text-header-500"> · {{ item.name_en }}</span>
                    <code class="block text-sm text-header-400">{{ item.code }}</code>
                  </span>
                  <span role="tooltip" class="pointer-events-none absolute bottom-full left-0 z-30 mb-1 hidden w-max max-w-sm rounded-md bg-header-900 px-3 py-2 text-sm leading-snug text-white shadow-lg ring-1 ring-white/10 group-hover:block group-focus-visible:block">
                    {{ explanation(item) }}
                  </span>
                </div>
              </td>
              <td>{{ t(`medals.types.${item.type}`) }}<span v-if="item.scopes.includes('R')"> ({{ t('medals.referee') }})</span></td>
              <td><span class="rounded border border-header-200 px-1.5 py-0.5 text-sm" :title="t(`medals.levels.${item.data_level}`)">{{ item.data_level }}</span></td>
              <td>
                <ul v-if="thresholdsOfItem(item).length" class="space-y-0.5">
                  <li v-for="threshold in thresholdsOfItem(item)" :key="threshold.key" class="whitespace-nowrap">
                    <span class="text-header-500">{{ humanizeKey(threshold.key) }}</span> <b class="tabular-nums">{{ formatThreshold(threshold.value) }}</b>
                  </li>
                </ul>
                <span v-else class="text-header-400">—</span>
              </td>
              <td class="text-right tabular-nums">
                <b class="text-lg">{{ item.awards === null ? '—' : number(item.awards) }}</b>
                <span v-if="awardsOf(item)" class="block text-sm whitespace-nowrap text-header-500">
                  {{ t('medals.per_game', { rate: awardsOf(item)!.rate }) }} · {{ t(awardsOf(item)!.reliable ? 'medals.basis_reliable' : 'medals.basis_games', { games: awardsOf(item)!.games }) }}
                </span>
              </td>
              <td v-for="surface in (['published_app2', 'published_app3'] as const)" :key="surface">
                <input
                  type="checkbox"
                  class="size-4"
                  :checked="item[surface]"
                  :disabled="item.status === 'retired'"
                  :aria-label="t('medals.publish_on', { name: name(item), surface: surface.slice(10) })"
                  @change="save(item, { [surface]: ($event.target as HTMLInputElement).checked })"
                >
              </td>
              <td>
                <select :value="item.status" class="rounded border border-header-300 px-2 py-1 dark:bg-header-800" @change="save(item, { status: ($event.target as HTMLSelectElement).value as AdminMedalItem['status'] })">
                  <option value="active">{{ t('medals.active') }}</option>
                  <option value="retired">{{ t('medals.retired') }}</option>
                </select>
              </td>
              <td class="text-right"><UButton size="sm" variant="outline" @click="edit(item)">{{ t('common.edit') }}</UButton></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Seasons: frozen thresholds (§ 6.3) -->
    <section v-else-if="tab === 'seasons'" class="space-y-8 rounded-lg bg-white p-5 shadow dark:bg-header-900">
      <!-- Default thresholds: the values used by the next computation of a season (§ 5.4, MED-66) -->
      <p v-if="computing" class="flex items-center gap-3 rounded-md bg-primary-50 px-4 py-3 text-base text-primary-900 dark:bg-primary-950/40 dark:text-primary-100" role="status" data-testid="medal-job">
        <UIcon name="i-heroicons-arrow-path" class="size-5 animate-spin" />
        {{ t('medals.compute_running', { season: job?.season }) }}
      </p>
      <div data-testid="medal-thresholds">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <h2 class="text-xl font-bold">{{ t('medals.defaults_title') }}</h2>
          <UButton variant="outline" :icon="showDefaults ? 'i-heroicons-chevron-up' : 'i-heroicons-chevron-down'" :aria-expanded="showDefaults" aria-controls="medal-defaults" @click="showDefaults = !showDefaults">
            {{ showDefaults ? t('medals.defaults_hide') : t(canEdit ? 'medals.defaults_show_edit' : 'medals.defaults_show') }}
          </UButton>
        </div>
        <div v-show="showDefaults" id="medal-defaults">
        <p class="mt-3 mb-5 max-w-3xl text-base leading-relaxed text-header-600 dark:text-header-300">
          {{ canEdit ? t('medals.defaults_help') : t('medals.defaults_readonly') }}
        </p>
        <form class="grid gap-x-8 gap-y-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4" @submit.prevent="saveThresholds">
          <div v-for="(value, key) in currentThresholds" :key="key" class="border-l-2 pl-3" :class="isOverridden(String(key)) ? 'border-primary-500' : 'border-header-200 dark:border-header-700'">
            <template v-if="key === 'permanentCards'">
              <span class="block text-sm text-header-500">{{ humanizeKey(String(key)) }}</span>
              <span class="mt-1 flex gap-4 text-lg">
                <label v-for="card in cardCodes" :key="card" class="flex items-center gap-1.5">
                  <input type="checkbox" class="size-4" :checked="(draft.permanentCards as string[] | undefined)?.includes(card)" :disabled="!canEdit" @change="toggleCard(card)">
                  <b>{{ card }}</b>
                </label>
              </span>
            </template>
            <label v-else class="block">
              <span class="block text-sm text-header-500">{{ humanizeKey(String(key)) }}</span>
              <input
                v-model="draft[String(key)]"
                type="number"
                inputmode="numeric"
                class="mt-1 w-28 rounded border bg-white px-2 py-1 text-lg font-semibold tabular-nums disabled:border-transparent disabled:bg-transparent dark:bg-header-800"
                :class="invalidKeys.includes(String(key)) ? 'border-red-500' : 'border-header-300'"
                :min="limits[String(key)]?.min"
                :max="limits[String(key)]?.max"
                :disabled="!canEdit"
              >
              <span v-if="invalidKeys.includes(String(key))" class="block text-sm text-red-600">{{ t('medals.threshold_range', { min: limits[String(key)]?.min, max: limits[String(key)]?.max }) }}</span>
            </label>
            <span v-if="isOverridden(String(key))" class="block text-sm text-primary-700 dark:text-primary-300">{{ t('medals.threshold_file', { value: formatThreshold(defaultThresholds[String(key)]) }) }}</span>
          </div>
          <div v-if="canEdit" class="flex flex-wrap items-center gap-3 sm:col-span-2 xl:col-span-3 2xl:col-span-4">
            <UButton type="submit" size="lg" :loading="saving" :disabled="Object.keys(changes).length === 0 || invalidKeys.length > 0">{{ t('common.save') }}</UButton>
            <UButton type="button" size="lg" variant="outline" :disabled="Object.keys(changes).length === 0" @click="resetDraft">{{ t('medals.cancel_changes') }}</UButton>
            <UButton v-if="Object.keys(overrides).length" type="button" size="lg" variant="ghost" :loading="saving" @click="resetThresholds">{{ t('medals.thresholds_reset') }}</UButton>
          </div>
        </form>
        </div>
        <p v-if="outdatedSeasons > 0" class="mt-5 rounded-md bg-amber-50 px-4 py-3 text-base text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
          {{ t('medals.outdated_seasons', { n: outdatedSeasons }, outdatedSeasons) }}
        </p>
      </div>

      <div>
        <h2 class="mb-4 text-xl font-bold">{{ t('medals.seasons_title') }}</h2>
        <div class="mb-5 flex flex-wrap items-center gap-x-6 gap-y-3">
          <MedalSearch v-model="queries.seasons" :placeholder="t('medals.search_seasons')" :count="shownSeasons.length" :total="seasons.length" />
        </div>
        <p class="mb-6 max-w-3xl text-base leading-relaxed text-header-600 dark:text-header-300">{{ t('medals.seasons_help', { version: rulesVersion }) }}</p>
      <p v-if="seasons.length === 0" class="text-base">{{ t('medals.no_season') }}</p>
      <p v-else-if="shownSeasons.length === 0" class="py-6 text-base text-header-500">{{ t('medals.search_empty') }}</p>
      <ol v-else class="space-y-5">
        <li v-for="row in shownSeasons" :key="row.season" class="grid gap-x-8 gap-y-4 rounded-xl border border-header-200 p-5 lg:grid-cols-[8rem_minmax(0,1fr)] dark:border-header-700" data-testid="medal-season">
          <p class="text-5xl leading-none font-bold tabular-nums text-primary-700 dark:text-primary-300">{{ row.season }}</p>
          <div class="space-y-5">
            <div class="flex flex-wrap items-center gap-x-10 gap-y-3 text-base">
              <p>
                <span class="block text-sm text-header-500">{{ t('medals.rules') }}</span>
                <b>v{{ row.rules_version }}</b>
                <span v-if="row.outdated" class="ml-2 rounded bg-amber-100 px-2 py-0.5 text-sm text-amber-800">{{ t('medals.outdated') }}</span>
              </p>
              <p>
                <span class="block text-sm text-header-500">{{ t('medals.computed_at') }}</span>
                <b>{{ computedAt(row) }}</b>
              </p>
              <div class="min-w-64 flex-1">
                <p class="flex flex-wrap items-baseline justify-between gap-x-4">
                  <span><b class="text-2xl tabular-nums">{{ number(row.reliable_games_count) }}</b> <span class="text-header-500">{{ t('medals.reliable_of', { games: number(row.games_count) }) }}</span></span>
                  <b class="tabular-nums">{{ share(row) }} %</b>
                </p>
                <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-header-100 dark:bg-header-800" role="img" :aria-label="`${share(row)} %`">
                  <div class="h-full rounded-full bg-primary-600" :style="{ width: `${share(row)}%` }" />
                </div>
              </div>
            </div>
            <details :open="row.changedCount > 0 || queries.seasons !== ''">
              <summary class="cursor-pointer text-base font-semibold">
                {{ t('medals.thresholds') }}
                <span class="font-normal" :class="row.changedCount ? 'text-amber-700 dark:text-amber-400' : 'text-header-500'">
                  — {{ row.changedCount ? t('medals.thresholds_differ_n', { n: row.changedCount }, row.changedCount) : t('medals.thresholds_same') }}
                </span>
              </summary>
              <dl class="mt-4 grid gap-x-8 gap-y-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                <div v-for="entry in row.entries" :key="entry.key" class="border-l-2 pl-3" :class="entry.changed ? 'border-amber-500' : 'border-header-200 dark:border-header-700'">
                  <dt class="text-sm text-header-500">{{ humanizeKey(entry.key) }}</dt>
                  <dd class="text-lg font-semibold tabular-nums">
                    {{ formatThreshold(entry.value) }}
                    <span v-if="entry.changed" class="block text-sm font-normal text-amber-700 dark:text-amber-400">{{ t('medals.threshold_now', { value: formatThreshold(entry.current) }) }}</span>
                  </dd>
                </div>
              </dl>
            </details>
            <div v-if="canEdit" class="flex flex-wrap gap-3">
              <UButton variant="outline" :disabled="computing" @click="pending = { action: 'compute', season: row.season, games: row.games_count }">{{ t('medals.recompute') }}</UButton>
              <UButton variant="outline" color="error" @click="pending = { action: 'delete', season: row.season, games: row.games_count }">{{ t('medals.delete_calculations') }}</UButton>
            </div>
          </div>
        </li>
      </ol>

      <!-- Existing seasons with no calculation (never computed, or deleted) -->
      <div v-if="uncomputed.length" class="mt-8">
        <h3 class="mb-3 text-lg font-bold">{{ t('medals.uncomputed_title') }}</h3>
        <ul class="flex flex-wrap gap-3">
          <li v-for="season in uncomputed.filter(code => matchesQuery(queries.seasons, code))" :key="season" class="flex items-center gap-3 rounded-lg border border-dashed border-header-300 px-4 py-2 dark:border-header-600">
            <b class="text-xl tabular-nums">{{ season }}</b>
            <UButton v-if="canEdit" size="sm" variant="outline" :disabled="computing" @click="pending = { action: 'compute', season, games: null }">{{ t('medals.compute') }}</UButton>
          </li>
        </ul>
      </div>
      </div>
    </section>

    <!-- Statistics and referee statistics of the work context -->
    <section v-else class="rounded-lg bg-white p-5 shadow dark:bg-header-900">
      <div class="mb-5 flex flex-wrap items-center gap-x-6 gap-y-3 text-base">
        <label class="flex items-center gap-2">{{ t('medals.statistic') }}
          <select v-model="kind" class="rounded border border-header-300 px-2 py-1.5 dark:bg-header-800">
            <option v-for="entry in tabKinds" :key="entry.code" :value="entry.code">
              {{ byCode.get(entry.code) ? name(byCode.get(entry.code)!) : entry.code }} — {{ entry.surfaces.length ? entry.surfaces.join(', ') : t('medals.unpublished') }}
            </option>
          </select>
        </label>
        <MedalSearch v-model="queries[tab]" :placeholder="t('medals.search_stats')" :count="shownRows.length" :total="stat?.rows.length ?? 0" />
      </div>
      <p v-if="tab === 'referees'" class="mb-4 max-w-3xl text-sm text-header-500">{{ t('medals.referee_note') }}</p>
      <p v-if="workContext.competitionCodes.length === 0" class="text-base">{{ t('medals.no_context') }}</p>
      <p v-else-if="loading" class="text-base">{{ t('common.loading') }}</p>
      <template v-else-if="stat">
        <div class="mb-6 grid items-start gap-x-10 gap-y-4 lg:grid-cols-[minmax(0,1fr)_auto]">
          <div class="flex items-start gap-4">
            <MedalShield v-if="byCode.get(stat.kind)" :item="byCode.get(stat.kind)!" :size="48" />
            <p class="max-w-prose text-base leading-relaxed">{{ byCode.get(stat.kind) ? explanation(byCode.get(stat.kind)!) : '' }}</p>
          </div>
          <p class="flex gap-8">
            <span><b class="block text-3xl tabular-nums">{{ number(stat.games_count) }}</b><span class="text-sm text-header-500">{{ t('medals.validated_games') }}</span></span>
            <span><b class="block text-3xl tabular-nums">{{ number(stat.reliable_games_count) }}</b><span class="text-sm text-header-500">{{ t('medals.complete_sheets') }}</span></span>
          </p>
        </div>
        <p v-if="stat.summary.undesignated_games" class="mb-4 text-sm text-header-500">{{ t('medals.undesignated', { n: stat.summary.undesignated_games }) }}</p>
        <p v-if="Object.keys(stat.summary).length" class="mb-4 text-base">
          <b>{{ t('medals.summary') }} :</b> {{ formatStatValue(stat.summary, 'integer') }}
        </p>
        <p v-if="shownRows.length === 0" class="py-6 text-base text-header-500">{{ t('medals.search_empty') }}</p>
        <div v-else class="overflow-x-auto">
          <table class="w-full text-base tabular-nums [&_td]:px-4 [&_th]:px-4">
            <thead>
              <tr class="border-b-2 border-header-200 text-left text-sm font-semibold text-header-600 dark:border-header-700 dark:text-header-300">
                <th class="pb-3" />
                <th v-for="col in stat.columns" :key="col.key" class="pb-3 text-right">{{ column(col.key) }}</th>
                <th v-if="stat.rows.some(row => row.medals)" class="pb-3">{{ t('medals.title') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in shownRows" :key="index" class="border-t border-header-100 hover:bg-header-50/60 dark:border-header-800 dark:hover:bg-header-800/40">
                <td class="py-3.5 font-medium">{{ statSubject(row) }}</td>
                <td v-for="col in stat.columns" :key="col.key" class="text-right">{{ formatStatValue(row[col.key], col.type) }}</td>
                <td v-if="row.medals">
                  <span class="flex flex-wrap gap-x-3 gap-y-2">
                    <span v-for="medal in medalsOf(row)" :key="medal.item.code" class="inline-flex items-center gap-1.5" :title="name(medal.item)">
                      <MedalShield :item="medal.item" :size="30" /><b>×{{ medal.count }}</b>
                    </span>
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </section>

    <!-- Confirmation before recomputing or deleting a season (MED-70) -->
    <AdminModal :open="pending !== null" :title="pending ? t(`medals.confirm_${pending.action}_title`, { season: pending.season }) : ''" max-width="lg" @close="!working && (pending = null)">
      <div v-if="pending" class="space-y-5 text-base">
        <p class="leading-relaxed">
          {{ pending.games === null ? t(`medals.confirm_${pending.action}_unknown`, { season: pending.season }) : t(`medals.confirm_${pending.action}_text`, { season: pending.season, games: number(pending.games) }) }}
        </p>
        <p v-if="pending.action === 'compute'" class="rounded-md bg-header-50 px-4 py-3 text-sm dark:bg-header-800">{{ t('medals.confirm_compute_thresholds') }}</p>
        <div class="flex justify-end gap-3">
          <UButton variant="outline" :disabled="working" @click="pending = null">{{ t('common.cancel') }}</UButton>
          <UButton :color="pending.action === 'delete' ? 'error' : 'primary'" :loading="working" @click="confirmAction">
            {{ t(`medals.confirm_${pending.action}_button`, { season: pending.season }) }}
          </UButton>
        </div>
      </div>
    </AdminModal>

    <AdminModal :open="editing !== null" :title="editing ? `${editing.name_fr} (${editing.code})` : ''" max-width="xl" @close="editing = null">
      <div v-if="editing" class="space-y-3 text-sm">
        <label class="block">{{ t('medals.name_fr') }}<UInput v-model="editDraft.name_fr" class="w-full" maxlength="80" /></label>
        <label class="block">{{ t('medals.name_en') }}<UInput v-model="editDraft.name_en" class="w-full" maxlength="80" /></label>
        <label class="block">{{ t('medals.explanation_fr') }}<UTextarea v-model="editDraft.explanation_fr" class="w-full" maxlength="500" /></label>
        <label class="block">{{ t('medals.explanation_en') }}<UTextarea v-model="editDraft.explanation_en" class="w-full" maxlength="500" /></label>
        <p v-if="variables(editing.explanation_fr_template).length" class="text-xs text-header-500">
          {{ t('medals.variables') }} <code v-for="variable in variables(editing.explanation_fr_template)" :key="variable" class="mr-1">{{ variable }}</code>
        </p>
        <p class="rounded bg-header-50 p-2 dark:bg-header-800"><b>{{ t('medals.preview') }}</b> {{ editing.explanation_fr }}</p>
        <p class="text-xs text-header-500">{{ t('medals.journalled') }}</p>
        <div class="flex justify-end gap-2">
          <UButton variant="outline" @click="editing = null">{{ t('common.cancel') }}</UButton>
          <UButton @click="saveEdit">{{ t('common.save') }}</UButton>
        </div>
      </div>
    </AdminModal>
  </div>
</template>
