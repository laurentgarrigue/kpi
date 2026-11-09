<script setup lang="ts">
import { FAMILY_CLASSES, formatStatValue, isRefereeKind, statSubject, svgWithUniqueIds, type AdminMedalItem } from '~/utils/medals'

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

const { t, locale } = useI18n()
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
const kinds = ref<{ code: string, surfaces: string[] }[]>([])
const kind = ref('')
const stat = ref<StatResult | null>(null)
const editing = ref<AdminMedalItem | null>(null)
const draft = reactive({ name_fr: '', name_en: '', explanation_fr: '', explanation_en: '' })

const name = (item: AdminMedalItem) => (locale.value === 'en' ? item.name_en : item.name_fr)
const byCode = computed(() => new Map(items.value.map(item => [item.code, item])))
const shownItems = computed(() => items.value.filter(item =>
  (!typeFilter.value || item.type === typeFilter.value) && (!unpublishedOnly.value || (!item.published_app2 && !item.published_app3))))
const tabKinds = computed(() => kinds.value.filter(entry => isRefereeKind(entry.code) === (tab.value === 'referees')))
const iconOf = (item: AdminMedalItem) => svgWithUniqueIds(item.icon_svg, item.code)
const variables = (template: string) => [...new Set(template.match(/\{\w+\}/g) ?? [])]

async function loadCatalogue() {
  const data = await api.get<{ items: AdminMedalItem[] }>('/admin/medals/catalog', { season: workContext.season })
  items.value = data?.items ?? []
}

async function loadSeasons() {
  const data = await api.get<{ rules_version: number, seasons: SeasonRow[] }>('/admin/medals/seasons')
  rulesVersion.value = data?.rules_version ?? 0
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
    <p class="mb-4 text-sm text-header-600 dark:text-header-300">{{ t('medals.intro') }}</p>

    <div class="mb-4 flex gap-1 overflow-x-auto border-b border-header-200 dark:border-header-700" role="tablist">
      <button
        v-for="item in TABS"
        :key="item"
        type="button"
        role="tab"
        :aria-selected="tab === item"
        class="whitespace-nowrap border-b-2 px-4 py-2 text-sm font-semibold"
        :class="tab === item ? 'border-primary-600 text-primary-700 dark:text-primary-300' : 'border-transparent text-header-500'"
        @click="tab = item"
      >
        {{ t(`medals.tabs.${item}`) }}
      </button>
    </div>

    <!-- Catalogue: surfaces, status, texts (§ 9.3) -->
    <section v-if="tab === 'catalogue'" class="rounded-lg bg-white p-4 shadow dark:bg-header-900">
      <div class="mb-3 flex flex-wrap items-center gap-4 text-sm">
        <label class="flex items-center gap-2">{{ t('medals.type') }}
          <select v-model="typeFilter" class="rounded border border-header-300 px-2 py-1 dark:bg-header-800">
            <option value="">{{ t('medals.all') }}</option>
            <option v-for="type in ['medal', 'label', 'stat']" :key="type" :value="type">{{ t(`medals.types.${type}`) }}</option>
          </select>
        </label>
        <label class="flex items-center gap-2"><input v-model="unpublishedOnly" type="checkbox"> {{ t('medals.unpublished_only') }}</label>
        <span class="text-header-500">{{ t('medals.publish_note') }}</span>
      </div>
      <div class="overflow-x-auto">
        <table class="w-full text-sm [&_td]:px-2 [&_th]:px-2">
          <thead>
            <tr class="text-left text-xs text-header-500">
              <th />
              <th>{{ t('medals.name') }}</th>
              <th>{{ t('medals.type') }}</th>
              <th>{{ t('medals.data') }}</th>
              <th class="text-right">{{ t('medals.awards', { season: workContext.season }) }}</th>
              <th>app2</th>
              <th>app3</th>
              <th>{{ t('medals.status') }}</th>
              <th />
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in shownItems" :key="item.code" class="border-t border-header-100 dark:border-header-800" :class="{ 'opacity-50': item.status === 'retired' }">
              <td class="py-2 pr-2">
                <span class="inline-grid h-7 w-7 place-items-center rounded-full" :class="FAMILY_CLASSES[item.family]">
                  <!-- eslint-disable-next-line vue/no-v-html -- pictogram of the api2 catalogue -->
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="iconOf(item)" />
                </span>
              </td>
              <td class="py-2 pr-2">
                <b>{{ item.name_fr }}</b><span v-if="item.name_fr !== item.name_en" class="italic text-header-500"> · {{ item.name_en }}</span>
                <code class="block text-xs text-header-400">{{ item.code }}</code>
              </td>
              <td class="pr-2">{{ t(`medals.types.${item.type}`) }}<span v-if="item.scopes.includes('R')"> ({{ t('medals.referee') }})</span></td>
              <td class="pr-2"><span class="rounded border border-header-200 px-1 text-xs" :title="t(`medals.levels.${item.data_level}`)">{{ item.data_level }}</span></td>
              <td class="pr-2 text-right tabular-nums">{{ item.awards ?? '—' }}</td>
              <td v-for="surface in (['published_app2', 'published_app3'] as const)" :key="surface">
                <input
                  type="checkbox"
                  :checked="item[surface]"
                  :disabled="item.status === 'retired'"
                  :aria-label="t('medals.publish_on', { name: name(item), surface: surface.slice(10) })"
                  @change="save(item, { [surface]: ($event.target as HTMLInputElement).checked })"
                >
              </td>
              <td>
                <select :value="item.status" class="rounded border border-header-300 px-1 py-0.5 dark:bg-header-800" @change="save(item, { status: ($event.target as HTMLSelectElement).value as AdminMedalItem['status'] })">
                  <option value="active">{{ t('medals.active') }}</option>
                  <option value="retired">{{ t('medals.retired') }}</option>
                </select>
              </td>
              <td class="text-right"><UButton size="xs" variant="outline" @click="edit(item)">{{ t('common.edit') }}</UButton></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Seasons: frozen thresholds (§ 6.3) -->
    <section v-else-if="tab === 'seasons'" class="rounded-lg bg-white p-4 shadow dark:bg-header-900">
      <p class="mb-3 text-sm text-header-600 dark:text-header-300">{{ t('medals.seasons_help', { version: rulesVersion }) }}</p>
      <p v-if="seasons.length === 0" class="text-sm">{{ t('medals.no_season') }}</p>
      <table v-else class="w-full text-sm">
        <thead>
          <tr class="text-left text-xs text-header-500">
            <th>{{ t('medals.season') }}</th><th>{{ t('medals.rules') }}</th><th>{{ t('medals.computed_at') }}</th>
            <th class="text-right">{{ t('medals.games') }}</th><th class="text-right">{{ t('medals.reliable') }}</th><th>{{ t('medals.thresholds') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in seasons" :key="row.season" class="border-t border-header-100 align-top dark:border-header-800">
            <td class="py-2 font-bold">{{ row.season }}</td>
            <td>v{{ row.rules_version }} <span v-if="row.outdated" class="ml-1 rounded bg-amber-100 px-1.5 text-xs text-amber-800">{{ t('medals.outdated') }}</span></td>
            <td>{{ row.computed_at }}</td>
            <td class="text-right tabular-nums">{{ row.games_count }}</td>
            <td class="text-right tabular-nums">{{ row.reliable_games_count }}</td>
            <td>
              <details>
                <summary class="cursor-pointer">{{ row.differs ? t('medals.thresholds_differ') : t('medals.thresholds_same') }}</summary>
                <p class="text-xs text-header-500">{{ Object.entries(row.thresholds).map(([key, value]) => `${key} = ${value}`).join(' · ') }}</p>
              </details>
            </td>
          </tr>
        </tbody>
      </table>
    </section>

    <!-- Statistics and referee statistics of the work context -->
    <section v-else class="rounded-lg bg-white p-4 shadow dark:bg-header-900">
      <div class="mb-3 flex flex-wrap items-center gap-3 text-sm">
        <label class="flex items-center gap-2">{{ t('medals.statistic') }}
          <select v-model="kind" class="rounded border border-header-300 px-2 py-1 dark:bg-header-800">
            <option v-for="entry in tabKinds" :key="entry.code" :value="entry.code">
              {{ byCode.get(entry.code) ? name(byCode.get(entry.code)!) : entry.code }} — {{ entry.surfaces.length ? entry.surfaces.join(', ') : t('medals.unpublished') }}
            </option>
          </select>
        </label>
        <span v-if="tab === 'referees'" class="text-header-500">{{ t('medals.referee_note') }}</span>
      </div>
      <p v-if="workContext.competitionCodes.length === 0" class="text-sm">{{ t('medals.no_context') }}</p>
      <p v-else-if="loading" class="text-sm">{{ t('common.loading') }}</p>
      <template v-else-if="stat">
        <p class="mb-2 text-sm text-header-500">
          {{ byCode.get(stat.kind)?.explanation_fr }} {{ t('medals.basis', { games: stat.games_count, reliable: stat.reliable_games_count }) }}
          <template v-if="stat.summary.undesignated_games">{{ t('medals.undesignated', { n: stat.summary.undesignated_games }) }}</template>
        </p>
        <p v-if="Object.keys(stat.summary).length" class="mb-2 text-sm">
          <b>{{ t('medals.summary') }} :</b> {{ formatStatValue(stat.summary, 'integer') }}
        </p>
        <div class="overflow-x-auto">
          <table class="w-full text-sm tabular-nums [&_td]:px-2 [&_th]:px-2">
            <thead>
              <tr class="text-left text-xs text-header-500">
                <th />
                <th v-for="column in stat.columns" :key="column.key" class="text-right">{{ column.key }}</th>
                <th v-if="stat.rows.some(row => row.medals)">{{ t('medals.tabs.catalogue') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(row, index) in stat.rows" :key="index" class="border-t border-header-100 dark:border-header-800">
                <td class="py-1.5 pr-2">{{ statSubject(row) }}</td>
                <td v-for="column in stat.columns" :key="column.key" class="text-right">{{ formatStatValue(row[column.key], column.type) }}</td>
                <td v-if="row.medals" class="pl-2 text-xs">{{ formatStatValue(row.medals, 'integer') }}</td>
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
