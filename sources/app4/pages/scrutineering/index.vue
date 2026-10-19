<script setup lang="ts">
import type { OverviewStatus, ScrutineeringOverview } from '~/types/scrutineering'
import { resolveContext } from '~/utils/scrutineering'
import { teamViewPath } from '~/composables/useScrutineeringNavigation'

/** Scrutineering overview of the context (event, else competition): one cell per club and category (PAGE_SCRUTINEERING.md § 4). */
definePageMeta({ layout: 'admin', middleware: ['auth', 'scrutineering'] })

const { t } = useI18n()
const workContext = useWorkContextStore()
const scrutineeringApi = useScrutineeringApi()

const context = computed(() => resolveContext(workContext.pageEventGroupSelection, workContext.season, workContext.pageCompetitionCode))
const overview = ref<ScrutineeringOverview | null>(null)
const loading = ref(false)
const loadError = ref(false)

const STATUS_STYLES: Record<OverviewStatus, { icon: string, color: string }> = {
  complete: { icon: 'heroicons:check-circle-solid', color: 'text-success-700 dark:text-success-400' },
  partial: { icon: 'heroicons:ellipsis-horizontal-circle-solid', color: 'text-warning-600 dark:text-warning-400' },
  none: { icon: 'heroicons:clock', color: 'text-header-400 dark:text-header-500' },
}
const STATUSES = Object.keys(STATUS_STYLES) as OverviewStatus[]

// Club × category index: a club can field several teams in the same category
const cells = computed(() => {
  const index = new Map<string, ScrutineeringOverview['teams']>()
  for (const team of overview.value?.teams ?? []) {
    const key = `${team.club}||${team.category}`
    index.set(key, [...(index.get(key) ?? []), team])
  }
  return index
})
const teamsOf = (club: string, category: string) => cells.value.get(`${club}||${category}`) ?? []

async function load() {
  if (context.value === null) {
    overview.value = null
    return
  }
  loading.value = true
  loadError.value = false
  try {
    overview.value = await scrutineeringApi.overview(context.value)
  }
  catch {
    loadError.value = true
  }
  finally {
    loading.value = false
  }
}

// Same context with a new value (another event or competition) reloads too
watch(() => JSON.stringify(context.value), load)
onMounted(async () => {
  await workContext.initContext()
  await load()
})
</script>

<template>
  <div>
    <AdminPageHeader :title="t('scrutineering.title')" :competition-filtered-codes="workContext.pageFilteredCompetitionCodes" />

    <div class="mb-4 flex flex-wrap items-end gap-3">
      <button
        v-if="context !== null"
        type="button"
        class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg hover:bg-header-50 dark:hover:bg-header-800"
        :disabled="loading"
        @click="load"
      >
        <UIcon name="heroicons:arrow-path" :class="['w-5 h-5', loading && 'animate-spin']" />
        {{ t('scrutineering.refresh') }}
      </button>
    </div>

    <p v-if="context === null" class="p-4 rounded-lg bg-white dark:bg-header-900 text-header-600 dark:text-header-300">
      {{ t('scrutineering.choose_context') }}
    </p>
    <p v-else-if="loadError" class="p-4 rounded-lg bg-danger-50 dark:bg-danger-950 text-danger-700 dark:text-danger-300">
      {{ t('scrutineering.load_error') }}
    </p>
    <p v-else-if="overview && overview.teams.length === 0" class="p-4 rounded-lg bg-white dark:bg-header-900 text-header-600 dark:text-header-300">
      {{ t('scrutineering.no_teams') }}
    </p>

    <template v-else-if="overview">
      <div class="overflow-x-auto bg-white dark:bg-header-900 rounded-lg shadow">
        <table class="min-w-full text-sm">
          <thead class="bg-header-100 dark:bg-header-800">
            <tr>
              <th class="sticky left-0 z-10 px-3 py-2 text-left font-semibold bg-header-100 dark:bg-header-800 min-w-40">
                {{ t('scrutineering.overview_club') }}
              </th>
              <th v-for="category in overview.categories" :key="category" class="px-3 py-2 text-center font-semibold min-w-40">
                {{ category }}
              </th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="club in overview.clubs" :key="club.code" class="border-t border-header-200 dark:border-header-800">
              <td class="sticky left-0 z-10 px-3 py-2 font-medium bg-white dark:bg-header-900">{{ club.label }}</td>
              <td v-for="category in overview.categories" :key="category" class="px-2 py-1 text-center">
                <div v-if="teamsOf(club.code, category).length" class="flex flex-col gap-1">
                  <NuxtLink
                    v-for="team in teamsOf(club.code, category)"
                    :key="team.team_id"
                    :to="teamViewPath('scrutineering', team.team_id)"
                    class="flex items-center gap-1.5 px-2 py-2 rounded hover:bg-header-100 dark:hover:bg-header-800 text-left"
                    :class="STATUS_STYLES[team.status].color"
                    :title="t(`scrutineering.status_${team.status}`)"
                  >
                    <UIcon :name="STATUS_STYLES[team.status].icon" class="w-5 h-5 shrink-0" />
                    <span class="text-xs leading-tight">{{ team.label }}</span>
                  </NuxtLink>
                </div>
                <span v-else class="text-header-300 dark:text-header-600">—</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex flex-wrap gap-4 text-sm">
        <div v-for="status in STATUSES" :key="status" class="flex items-center gap-1.5" :class="STATUS_STYLES[status].color">
          <UIcon :name="STATUS_STYLES[status].icon" class="w-5 h-5" />
          <span class="text-header-700 dark:text-header-200">{{ t(`scrutineering.status_${status}`) }}</span>
        </div>
      </div>
    </template>
  </div>
</template>
