<script setup lang="ts">
import { actionGroups, categoriesParam, defaultCategoryKeys, type JournalCategory } from '~/utils/journal'

interface JournalEntry {
  id: number
  date: string
  userCode: string
  userIdentite: string | null
  userFonction: string | null
  isDeleted: boolean
  action: string
  category: string
  journal: string | null
  saison: string | null
  competition: string | null
  evenement: number | null
  journee: number | null
  match: string | null
}

interface JournalResponse {
  items: JournalEntry[]
  total: number
  page: number
  limit: number
  totalPages: number
}

interface JournalUser {
  code: string
  identite: string | null
  fonction: string | null
  isDeleted: boolean
}

definePageMeta({
  layout: 'admin',
  middleware: ['auth']
})

const { t } = useI18n()
const api = useApi()
const authStore = useAuthStore()

// Profile guard: only profiles <= 2
if (authStore.profile > 2) {
  navigateTo('/')
}

// State
const loading = ref(false)
const entries = ref<JournalEntry[]>([])
const page = ref(1)
const limit = ref(50)
const total = ref(0)
const totalPages = ref(0)
const searchQuery = ref('')

// Filters
const filterUser = ref('')
const filterAction = ref('')
const filterActionMode = ref<'prefix' | 'exact'>('prefix')
const filterSeason = ref('')
const filterCompetition = ref('')
const filterDateFrom = ref('')
const filterDateTo = ref('')
const filterCategories = ref<string[]>([])
const categoriesOpen = ref(false)
const categoriesDropdown = ref<HTMLElement | null>(null)

// Filter data
const journalUsers = ref<JournalUser[]>([])
const journalCategories = ref<JournalCategory[]>([])
const seasons = ref<{ code: string; active: boolean }[]>([])

// Prefix groups of the "Ensemble" optgroup
const actionPrefixGroups = [
  { value: 'Connexion', labelKey: 'journal.filters.action_connexion' },
  { value: 'Ajout', labelKey: 'journal.filters.action_ajout' },
  { value: 'Modif', labelKey: 'journal.filters.action_modif' },
  { value: 'Supp', labelKey: 'journal.filters.action_supp' },
  { value: 'Calcul', labelKey: 'journal.filters.action_calcul' },
]

// Load filter data
async function loadFilters() {
  try {
    const [usersData, actionsData, seasonsData] = await Promise.all([
      api.get<JournalUser[]>('/admin/journal/users'),
      api.get<{ categories: JournalCategory[] }>('/admin/journal/actions'),
      api.get<{ seasons: { code: string; active: boolean }[] }>('/admin/filters/seasons'),
    ])
    journalUsers.value = usersData
    journalCategories.value = actionsData.categories
    filterCategories.value = defaultCategoryKeys(actionsData.categories)
    seasons.value = seasonsData.seasons || []
  } catch { /* useApi handles toast */ }
}

// Load journal entries
async function loadJournal() {
  loading.value = true
  try {
    const params: Record<string, string | number> = {
      page: page.value,
      limit: limit.value,
    }
    if (searchQuery.value) params.search = searchQuery.value
    if (filterUser.value) params.user = filterUser.value
    if (filterAction.value) {
      params.action = filterAction.value
      params.actionMode = filterActionMode.value
    }
    if (filterSeason.value) params.season = filterSeason.value
    if (filterCompetition.value) params.competition = filterCompetition.value
    if (filterDateFrom.value) params.dateFrom = filterDateFrom.value
    if (filterDateTo.value) params.dateTo = filterDateTo.value
    const categories = categoriesParam(filterCategories.value, journalCategories.value)
    if (categories) params.categories = categories

    const data = await api.get<JournalResponse>('/admin/journal', params)
    entries.value = data.items
    total.value = data.total
    totalPages.value = data.totalPages
  } catch { /* useApi handles toast */ }
  finally { loading.value = false }
}

// Handle action filter change (detect group vs detail)
function onActionFilterChange(event: Event) {
  const select = event.target as HTMLSelectElement
  const selectedOption = select.options[select.selectedIndex]
  const mode = selectedOption?.dataset.mode as 'prefix' | 'exact' | undefined
  filterActionMode.value = mode || 'prefix'
  filterAction.value = select.value
}

// Category filter (multi-select)
const visibleActionGroups = computed(() => actionGroups(journalCategories.value, filterCategories.value))
const allCategoriesSelected = computed(() =>
  journalCategories.value.length > 0 && journalCategories.value.every(c => filterCategories.value.includes(c.key)),
)
const categoriesSummary = computed(() =>
  allCategoriesSelected.value || filterCategories.value.length === 0
    ? t('journal.filters.category_all')
    : t('journal.filters.category_count', { count: filterCategories.value.length, total: journalCategories.value.length }),
)

function setCategories(keys: string[]) {
  filterCategories.value = keys
  page.value = 1
  // An exact action hidden by the new selection is dropped (its watcher reloads the journal).
  const actionStillVisible = filterActionMode.value !== 'exact'
    || visibleActionGroups.value.some(g => g.actions.includes(filterAction.value))
  if (filterAction.value && !actionStillVisible) {
    filterAction.value = ''
    filterActionMode.value = 'prefix'
    return
  }
  loadJournal()
}

function toggleCategory(key: string) {
  setCategories(filterCategories.value.includes(key)
    ? filterCategories.value.filter(k => k !== key)
    : [...filterCategories.value, key])
}

function toggleAllCategories() {
  setCategories(allCategoriesSelected.value ? [] : journalCategories.value.map(c => c.key))
}

function closeCategoriesOnClickOutside(event: MouseEvent) {
  if (categoriesDropdown.value && !categoriesDropdown.value.contains(event.target as Node)) {
    categoriesOpen.value = false
  }
}

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    loadJournal()
  }, 300)
})

// Debounced competition filter
let competitionTimeout: ReturnType<typeof setTimeout> | null = null
watch(filterCompetition, () => {
  if (competitionTimeout) clearTimeout(competitionTimeout)
  competitionTimeout = setTimeout(() => {
    page.value = 1
    loadJournal()
  }, 300)
})

watch([filterUser, filterAction, filterSeason, filterDateFrom, filterDateTo], () => {
  page.value = 1
  loadJournal()
})

watch([page, limit], () => {
  loadJournal()
})

// Format date for display
function formatDate(dateStr: string): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = String(d.getFullYear()).slice(-2)
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  return `${day}/${month}/${year} ${hours}:${minutes}`
}

// Format competition + season column
function formatCompSaison(entry: JournalEntry): string {
  const parts: string[] = []
  if (entry.competition) parts.push(entry.competition)
  if (entry.saison) parts.push(entry.saison)
  return parts.join(' - ')
}

// Check if any filter is active
const hasActiveFilters = computed(() => {
  return filterUser.value || filterAction.value || filterSeason.value ||
    filterCompetition.value || filterDateFrom.value || filterDateTo.value ||
    searchQuery.value || categoriesParam(filterCategories.value, journalCategories.value) !== undefined
})

onMounted(async () => {
  document.addEventListener('click', closeCategoriesOnClickOutside)
  // Categories first: the default selection (scoring unchecked) applies to the very first load.
  await loadFilters()
  loadJournal()
})

onUnmounted(() => document.removeEventListener('click', closeCategoriesOnClickOutside))
</script>

<template>
  <div>
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">{{ t('journal.title') }}</h1>
    </div>

    <!-- Toolbar -->
    <AdminToolbar
      v-model:search="searchQuery"
      :search-placeholder="t('journal.search_placeholder')"
      :show-add="false"
      :show-bulk-delete="false"
    >
      <template #before-search>
        <!-- Refresh button -->
        <button
          class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg hover:bg-header-50 dark:hover:bg-header-800"
          :title="t('common.refresh')"
          @click="loadJournal"
        >
          <UIcon name="heroicons:arrow-path" class="w-5 h-5 text-header-600 dark:text-header-300" />
        </button>

        <!-- User filter -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-header-600 dark:text-header-300">{{ t('journal.filters.user') }}</label>
          <select
            v-model="filterUser"
            class="px-3 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="">{{ t('journal.filters.user_all') }}</option>
            <option v-for="u in journalUsers" :key="u.code" :value="u.code">
              {{ u.isDeleted ? `${u.code} (${t('journal.deleted_account')})` : u.identite }}
            </option>
          </select>
        </div>

        <!-- Action filter with optgroups -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-header-600 dark:text-header-300">{{ t('journal.filters.action') }}</label>
          <select
            :value="filterAction"
            class="px-3 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            @change="onActionFilterChange"
          >
            <option value="">{{ t('journal.filters.action_all') }}</option>
            <optgroup :label="t('journal.filters.action_group_ensemble')">
              <option v-for="g in actionPrefixGroups" :key="g.value" :value="g.value" data-mode="prefix">
                {{ t(g.labelKey) }}
              </option>
            </optgroup>
            <optgroup v-for="group in visibleActionGroups" :key="group.key" :label="t(`journal.categories.${group.key}`)">
              <option v-for="a in group.actions" :key="a" :value="a" data-mode="exact">
                {{ a }}
              </option>
            </optgroup>
          </select>
        </div>

        <!-- Category filter (multi-select) -->
        <div ref="categoriesDropdown" class="relative flex flex-col gap-1">
          <label class="text-xs font-medium text-header-600 dark:text-header-300">{{ t('journal.filters.category') }}</label>
          <button
            type="button"
            class="flex items-center justify-between gap-2 min-w-40 px-3 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
            :aria-expanded="categoriesOpen"
            @click="categoriesOpen = !categoriesOpen"
          >
            <span>{{ categoriesSummary }}</span>
            <UIcon name="heroicons:chevron-down" class="w-4 h-4 text-header-600 dark:text-header-300" />
          </button>
          <div
            v-if="categoriesOpen"
            class="absolute top-full left-0 z-20 mt-1 w-56 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 shadow-lg overflow-hidden"
          >
            <label class="flex items-center gap-2 p-2 hover:bg-header-50 dark:hover:bg-header-800 cursor-pointer border-b border-header-200 dark:border-header-700">
              <input
                type="checkbox"
                :checked="allCategoriesSelected"
                :indeterminate="filterCategories.length > 0 && !allCategoriesSelected"
                class="rounded border-header-300 dark:border-header-700 text-primary-600 focus:ring-primary-500"
                @change="toggleAllCategories"
              >
              <span class="text-sm font-medium text-header-900 dark:text-header-50">{{ t('journal.filters.category_all') }}</span>
            </label>
            <label
              v-for="category in journalCategories"
              :key="category.key"
              class="flex items-center gap-2 p-2 hover:bg-header-50 dark:hover:bg-header-800 cursor-pointer"
            >
              <input
                type="checkbox"
                :checked="filterCategories.includes(category.key)"
                class="rounded border-header-300 dark:border-header-700 text-primary-600 focus:ring-primary-500"
                @change="toggleCategory(category.key)"
              >
              <span class="text-sm text-header-900 dark:text-header-200">{{ t(`journal.categories.${category.key}`) }}</span>
            </label>
          </div>
        </div>

        <!-- Season filter -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-header-600 dark:text-header-300">{{ t('journal.filters.season') }}</label>
          <select
            v-model="filterSeason"
            class="px-3 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option value="">{{ t('journal.filters.season_all') }}</option>
            <option v-for="s in seasons" :key="s.code" :value="s.code">
              {{ s.code }}{{ s.active ? ' ★' : '' }}
            </option>
          </select>
        </div>

        <!-- Competition filter (text input) -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-header-600 dark:text-header-300">{{ t('journal.filters.competition') }}</label>
          <input
            v-model="filterCompetition"
            type="text"
            :placeholder="t('journal.filters.competition_placeholder')"
            class="w-32 px-3 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
        </div>

        <!-- Date from -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-header-600 dark:text-header-300">{{ t('journal.filters.date_from') }}</label>
          <input
            v-model="filterDateFrom"
            type="date"
            class="px-2 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
        </div>

        <!-- Date to -->
        <div class="flex flex-col gap-1">
          <label class="text-xs font-medium text-header-600 dark:text-header-300">{{ t('journal.filters.date_to') }}</label>
          <input
            v-model="filterDateTo"
            type="date"
            class="px-2 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
        </div>
      </template>
    </AdminToolbar>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-12">
      <UIcon name="i-heroicons-arrow-path" class="w-8 h-8 animate-spin text-header-600 dark:text-header-300" />
    </div>

    <!-- Desktop Table -->
    <div v-else class="hidden lg:block sticky-thead">
      <table v-if="entries.length > 0" class="min-w-full divide-y divide-header-200 dark:divide-header-700 bg-white dark:bg-header-900 rounded-lg shadow-sm">
        <thead class="bg-header-50 dark:bg-header-900">
          <tr>
            <th class="px-3 py-3 text-left text-xs font-medium text-header-600 dark:text-header-300 uppercase">{{ t('journal.table.date') }}</th>
            <th class="px-3 py-3 text-left text-xs font-medium text-header-600 dark:text-header-300 uppercase">{{ t('journal.table.identite') }}</th>
            <th class="px-3 py-3 text-left text-xs font-medium text-header-600 dark:text-header-300 uppercase">{{ t('journal.table.action') }}</th>
            <th class="px-3 py-3 text-left text-xs font-medium text-header-600 dark:text-header-300 uppercase">{{ t('journal.table.detail') }}</th>
            <th class="px-3 py-3 text-left text-xs font-medium text-header-600 dark:text-header-300 uppercase">{{ t('journal.table.competition') }}</th>
            <th class="px-3 py-3 text-left text-xs font-medium text-header-600 dark:text-header-300 uppercase">{{ t('journal.table.gameday') }}</th>
            <th class="px-3 py-3 text-left text-xs font-medium text-header-600 dark:text-header-300 uppercase">{{ t('journal.table.match') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-header-200 dark:divide-header-700">
          <tr v-for="entry in entries" :key="entry.id" class="hover:bg-header-50 dark:hover:bg-header-800">
            <td class="px-3 py-2 text-sm text-header-900 dark:text-header-50 whitespace-nowrap">
              {{ formatDate(entry.date) }}
            </td>
            <td class="px-3 py-2">
              <div class="text-sm font-medium text-header-900 dark:text-header-50" :title="entry.userFonction ?? ''">
                <template v-if="entry.isDeleted">
                  <span class="italic text-header-500 dark:text-header-400">{{ entry.userCode }}</span>
                  <span class="ml-1 text-xs text-header-500 dark:text-header-400">({{ t('journal.deleted_account') }})</span>
                </template>
                <template v-else>{{ entry.userIdentite }}</template>
              </div>
            </td>
            <td class="px-3 py-2 text-sm text-header-900 dark:text-header-50">
              {{ entry.action }}
            </td>
            <td class="px-3 py-2 text-sm text-header-900 dark:text-header-50 max-w-md truncate" :title="entry.journal || ''">
              {{ entry.journal || '' }}
            </td>
            <td class="px-3 py-2 text-sm text-header-900 dark:text-header-50 whitespace-nowrap">
              <span v-if="entry.competition" class="font-semibold">{{ entry.competition }}</span>
              <span v-if="entry.competition && entry.saison"> - </span>
              <span v-if="entry.saison">{{ entry.saison }}</span>
            </td>
            <td class="px-3 py-2 text-sm text-header-900 dark:text-header-50">
              {{ entry.journee ?? '' }}
            </td>
            <td class="px-3 py-2 text-sm text-header-900 dark:text-header-50">
              {{ entry.match ?? '' }}
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="text-center py-12 text-header-600 dark:text-header-300">
        {{ hasActiveFilters ? t('journal.empty_filtered') : t('journal.empty') }}
      </div>
    </div>

    <!-- Mobile Cards -->
    <AdminCardList
      :loading="loading"
      :empty="entries.length === 0"
      :loading-text="t('journal.loading')"
      :empty-text="hasActiveFilters ? t('journal.empty_filtered') : t('journal.empty')"
    >
      <AdminCard
        v-for="entry in entries"
        :key="entry.id"
      >
        <template #header>
          <span class="text-sm text-header-900 dark:text-header-50">{{ formatDate(entry.date) }}</span>
        </template>
        <template #header-right>
          <span v-if="formatCompSaison(entry)" class="text-xs font-medium text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-950 px-2 py-0.5 rounded">
            {{ formatCompSaison(entry) }}
          </span>
        </template>
        <div class="space-y-1 text-sm">
          <div class="font-medium text-header-900 dark:text-header-50" :title="entry.userFonction ?? ''">
            <template v-if="entry.isDeleted">
              <span class="italic text-header-500 dark:text-header-400">{{ entry.userCode }}</span>
              <span class="ml-1 text-xs text-header-500 dark:text-header-400">({{ t('journal.deleted_account') }})</span>
            </template>
            <template v-else>{{ entry.userIdentite }}</template>
          </div>
          <div class="text-header-900 dark:text-header-50">{{ entry.action }}</div>
          <div v-if="entry.journal" class="text-header-600 dark:text-header-300">{{ entry.journal }}</div>
          <div v-if="entry.journee || entry.match" class="text-xs text-header-600 dark:text-header-300">
            <span v-if="entry.journee">{{ t('journal.table.gameday') }}: {{ entry.journee }}</span>
            <span v-if="entry.journee && entry.match"> · </span>
            <span v-if="entry.match">{{ t('journal.table.match') }}: {{ entry.match }}</span>
          </div>
        </div>
      </AdminCard>
    </AdminCardList>

    <!-- Pagination -->
    <AdminPagination
      v-if="total > 0"
      :page="page"
      :total-pages="totalPages"
      :total="total"
      :limit="limit"
      :limit-options="[25, 50, 100, 200]"
      :showing-text="t('journal.pagination.showing', { from: '{from}', to: '{to}', total: '{total}' })"
      :items-per-page-text="t('journal.pagination.items_per_page')"
      @update:page="page = $event"
      @update:limit="limit = $event; page = 1"
    />

    <AdminScrollToTop />
  </div>
</template>
