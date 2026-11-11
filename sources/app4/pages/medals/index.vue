<script setup lang="ts">
import { changedThresholds, formatStatValue, formatThreshold, humanizeKey, isRefereeKind, matchesQuery, statRowText, statSubject, thresholdsUsed, type AdminMedalItem, type Thresholds } from '~/utils/medals'

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
const queries = reactive<Record<Tab, string>>({ catalogue: '', seasons: '', stats: '', referees: '' })
const kinds = ref<{ code: string, surfaces: string[] }[]>([])
const kind = ref('')
const stat = ref<StatResult | null>(null)
const editing = ref<AdminMedalItem | null>(null)
const draft = reactive({ name_fr: '', name_en: '', explanation_fr: '', explanation_en: '' })

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
  const data = await api.get<{ rules_version: number, thresholds: Thresholds, seasons: SeasonRow[] }>('/admin/medals/seasons')
  rulesVersion.value = data?.rules_version ?? 0
  currentThresholds.value = data?.thresholds ?? {}
  seasons.value = data?.seasons ?? []
}

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
  Object.assign(draft, { name_fr: item.name_fr, name_en: item.name_en, explanation_fr: item.explanation_fr_template, explanation_en: item.explanation_en_template })
}

async function saveEdit() {
  if (!editing.value) {
    return
  }
  await save(editing.value, { ...draft, explanation_fr_template: draft.explanation_fr, explanation_en_template: draft.explanation_en })
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
  await Promise.all([loadCatalogue(), loadSeasons(), loadKinds()])
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
              <td class="text-right tabular-nums">{{ item.awards ?? '—' }}</td>
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
    <section v-else-if="tab === 'seasons'" class="rounded-lg bg-white p-5 shadow dark:bg-header-900">
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
          </div>
        </li>
      </ol>
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

    <AdminModal :open="editing !== null" :title="editing ? `${editing.name_fr} (${editing.code})` : ''" max-width="xl" @close="editing = null">
      <div v-if="editing" class="space-y-3 text-sm">
        <label class="block">{{ t('medals.name_fr') }}<UInput v-model="draft.name_fr" class="w-full" maxlength="80" /></label>
        <label class="block">{{ t('medals.name_en') }}<UInput v-model="draft.name_en" class="w-full" maxlength="80" /></label>
        <label class="block">{{ t('medals.explanation_fr') }}<UTextarea v-model="draft.explanation_fr" class="w-full" maxlength="500" /></label>
        <label class="block">{{ t('medals.explanation_en') }}<UTextarea v-model="draft.explanation_en" class="w-full" maxlength="500" /></label>
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
