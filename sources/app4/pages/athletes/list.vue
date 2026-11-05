<script setup lang="ts">
import type { AthletesListFilters, AthletesListItem, AthletesListResponse } from '~/types/athletes-list'
import { athletesListParams, athletesListTotalPages } from '~/utils/athletesList'

// Athletes list: find the players whose birth date is still to be completed (PAGE_ATHLETES.md § 2.6).
definePageMeta({
  layout: 'admin',
  middleware: 'auth'
})

const { t, locale } = useI18n()
const api = useApi()

const SEARCH_DEBOUNCE_MS = 300
const LIMIT_OPTIONS = [25, 50, 100, 200]
const FILTER_KEYS = ['unknownBirthDate', 'international', 'engagedThisSeason'] as const
const FILTER_LABELS: Record<typeof FILTER_KEYS[number], string> = {
  unknownBirthDate: 'athletes_list.filters.unknown_birthdate',
  international: 'athletes_list.filters.international',
  engagedThisSeason: 'athletes_list.filters.engaged_this_season',
}

const filters = ref<AthletesListFilters>({ q: '', unknownBirthDate: true, international: false, engagedThisSeason: false })
const items = ref<AthletesListItem[]>([])
const total = ref(0)
const page = ref(1)
const limit = ref(50)
const loading = ref(false)
const totalPages = computed(() => athletesListTotalPages(total.value, limit.value))

async function load() {
  loading.value = true
  try {
    const data = await api.get<AthletesListResponse>('/admin/athletes', athletesListParams(filters.value, page.value, limit.value))
    items.value = data.items
    total.value = data.total
  } catch { /* useApi handles toast */ }
  finally { loading.value = false }
}

function sheetLink(matric: number) {
  return { path: '/athletes', query: { matric: String(matric) } }
}

function formatBirthDate(date: string) {
  return new Date(`${date}T00:00:00`).toLocaleDateString(locale.value === 'fr' ? 'fr-FR' : 'en-GB')
}

// A filter change goes back to page 1; the page watcher then reloads (or we reload when already there).
function reloadFromFirstPage() {
  if (page.value === 1) load()
  else page.value = 1
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(() => filters.value.q, () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(reloadFromFirstPage, SEARCH_DEBOUNCE_MS)
})
watch(() => FILTER_KEYS.map(key => filters.value[key]), reloadFromFirstPage)
watch([page, limit], load)

onMounted(load)
onBeforeUnmount(() => { if (searchTimer) clearTimeout(searchTimer) })
</script>

<template>
  <div>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
      <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">
        {{ t('athletes_list.title') }}
      </h1>
      <NuxtLink
        to="/athletes"
        class="flex items-center gap-2 text-sm font-medium px-3 py-1.5 rounded-lg border border-header-300 dark:border-header-700 text-header-900 dark:text-header-50 hover:bg-header-50 dark:hover:bg-header-800"
      >
        <UIcon name="i-heroicons-magnifying-glass" class="w-4 h-4" />
        {{ t('athletes_list.back_to_search') }}
      </NuxtLink>
    </div>

    <!-- Filters -->
    <div class="mb-4 bg-white dark:bg-header-900 border border-header-200 dark:border-header-700 rounded-lg p-4 flex flex-col lg:flex-row lg:items-center gap-3">
      <input
        v-model="filters.q"
        type="search"
        :placeholder="t('athletes_list.search_placeholder')"
        class="w-full lg:w-72 px-3 py-1.5 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
      >
      <div class="flex flex-wrap gap-x-5 gap-y-2">
        <label v-for="key in FILTER_KEYS" :key="key" class="flex items-center gap-2 text-sm text-header-900 dark:text-header-50 cursor-pointer">
          <input v-model="filters[key]" type="checkbox" class="rounded border-header-300 dark:border-header-700 text-primary-600 focus:ring-primary-500">
          {{ t(FILTER_LABELS[key]) }}
        </label>
      </div>
      <div class="lg:ml-auto text-sm font-semibold text-header-900 dark:text-header-50" data-testid="athletes-list-total">
        {{ t('athletes_list.total', { count: total }) }}
      </div>
    </div>

    <!-- List -->
    <div class="bg-white dark:bg-header-900 border border-header-200 dark:border-header-700 rounded-lg overflow-hidden">
      <div v-if="loading && items.length === 0" class="p-6 text-center">
        <UIcon name="i-heroicons-arrow-path" class="w-6 h-6 animate-spin text-header-400" />
      </div>
      <div v-else-if="items.length === 0" class="p-6 text-center text-sm text-header-600 dark:text-header-300">
        {{ t('athletes_list.empty') }}
      </div>
      <template v-else>
        <!-- Desktop table -->
        <table class="hidden md:table w-full text-sm" :class="{ 'opacity-60': loading }">
          <thead class="bg-header-50 dark:bg-header-800 text-left text-header-900 dark:text-header-50">
            <tr>
              <th class="px-4 py-2 font-semibold">{{ t('athletes_list.columns.licence') }}</th>
              <th class="px-4 py-2 font-semibold">{{ t('athletes_list.columns.nom') }}</th>
              <th class="px-4 py-2 font-semibold">{{ t('athletes_list.columns.sexe') }}</th>
              <th class="px-4 py-2 font-semibold">{{ t('athletes_list.columns.naissance') }}</th>
              <th class="px-4 py-2 font-semibold">{{ t('athletes_list.columns.club') }}</th>
              <th class="px-4 py-2" />
            </tr>
          </thead>
          <tbody class="divide-y divide-header-200 dark:divide-header-700">
            <tr v-for="athlete in items" :key="athlete.matric" class="hover:bg-header-50 dark:hover:bg-header-800">
              <td class="px-4 py-2 font-mono text-header-900 dark:text-header-50">
                {{ athlete.matric }}
                <span v-if="athlete.international" class="ml-1 text-xs px-1.5 py-0.5 rounded bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300">
                  {{ t('athletes_list.international_badge') }}
                </span>
              </td>
              <td class="px-4 py-2 text-header-900 dark:text-header-50">
                <NuxtLink :to="sheetLink(athlete.matric)" class="font-medium hover:underline">{{ athlete.nom }} {{ athlete.prenom }}</NuxtLink>
              </td>
              <td class="px-4 py-2 text-header-900 dark:text-header-50">{{ athlete.sexe }}</td>
              <td class="px-4 py-2">
                <span v-if="athlete.naissance" class="text-header-900 dark:text-header-50">{{ formatBirthDate(athlete.naissance) }}</span>
                <span v-else class="italic text-warning-700 dark:text-warning-300">{{ t('athletes_list.unknown_birthdate') }}</span>
              </td>
              <td class="px-4 py-2 text-header-900 dark:text-header-50">{{ athlete.club || athlete.codeClub }}</td>
              <td class="px-4 py-2 text-right">
                <NuxtLink :to="sheetLink(athlete.matric)" :title="t('athletes_list.open_sheet')" class="inline-flex text-primary-600 dark:text-primary-400 hover:text-primary-800">
                  <UIcon name="i-heroicons-arrow-top-right-on-square" class="w-4 h-4" />
                </NuxtLink>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Mobile cards -->
        <div class="md:hidden divide-y divide-header-200 dark:divide-header-700">
          <NuxtLink
            v-for="athlete in items"
            :key="athlete.matric"
            :to="sheetLink(athlete.matric)"
            class="block px-4 py-3 hover:bg-header-50 dark:hover:bg-header-800"
          >
            <div class="font-medium text-header-900 dark:text-header-50">{{ athlete.nom }} {{ athlete.prenom }} ({{ athlete.sexe }})</div>
            <div class="text-xs text-header-600 dark:text-header-300 mt-0.5">
              {{ athlete.matric }}
              <span v-if="athlete.international"> · {{ t('athletes_list.international_badge') }}</span>
              · <span v-if="athlete.naissance">{{ formatBirthDate(athlete.naissance) }}</span>
              <span v-else class="italic text-warning-700 dark:text-warning-300">{{ t('athletes_list.unknown_birthdate') }}</span>
              <span v-if="athlete.club"> · {{ athlete.club }}</span>
            </div>
          </NuxtLink>
        </div>
      </template>

      <AdminPagination
        v-if="total > 0"
        :page="page"
        :total-pages="totalPages"
        :total="total"
        :limit="limit"
        :limit-options="LIMIT_OPTIONS"
        :showing-text="t('athletes_list.pagination.showing', { from: '{from}', to: '{to}', total: '{total}' })"
        :items-per-page-text="t('athletes_list.pagination.items_per_page')"
        @update:page="page = $event"
        @update:limit="page = 1; limit = $event"
      />
    </div>
  </div>
</template>
