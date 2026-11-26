<script setup lang="ts">
import {
  canManageSuspensions, filterSuspensions, ruleLabel, suspensionText,
  type CardSuspension, type CardSuspensionsResponse, type SuspensionCatalog, type SuspensionFilter,
} from '~/utils/cardSuspensions'

/**
 * Suspensions détectées par les cumuls de cartons (DOC/specs/PAGE_SUSPENSIONS.md ; FEATURE_CARD_SUSPENSIONS.md S14,
 * S19) : profils 1 et 2. Les règles viennent du catalogue d'api2.
 */
definePageMeta({
  layout: 'admin',
  middleware: ['auth'],
})

const { t, locale } = useI18n()
const api = useApi()
const authStore = useAuthStore()
const workContext = useWorkContextStore()

const STATES: SuspensionFilter[] = ['ACTIVE', 'LIFTED', 'CANCELLED', 'ALL']

const season = computed(() => workContext.season)
const allowed = computed(() => canManageSuspensions(authStore.profile))
const catalog = ref<SuspensionCatalog | null>(null)
const suspensions = ref<CardSuspension[]>([])
const loading = ref(false)
const stateFilter = ref<SuspensionFilter>('ACTIVE')
const search = ref('')
const lifting = ref<CardSuspension | null>(null)
const showRules = ref(false)

const shown = computed(() => filterSuspensions(suspensions.value, stateFilter.value, search.value))

const load = async () => {
  if (!season.value || !allowed.value) return
  loading.value = true
  try {
    const response = await api.get<CardSuspensionsResponse>('/admin/card-suspensions', { season: season.value })
    catalog.value = response.catalog
    suspensions.value = response.suspensions
  } finally {
    loading.value = false
  }
}

const suspensionLabel = (item: CardSuspension) => {
  const text = suspensionText(item)
  const label = t(`card_suspensions.matches.${text.key}`, { matches: text.matches, total: text.total })
  return item.appealJury ? `${label} ${t('card_suspensions.appeal_jury')}` : label
}

const otherRules = (item: CardSuspension) => item.rules.filter(code => code !== item.rule).map(code => ruleLabel(catalog.value, code, locale.value))

const confirmLift = async () => {
  const item = lifting.value
  lifting.value = null
  if (!item) return
  try {
    await api.post(`/admin/card-suspensions/${item.id}/lift`, {})
    await load()
  } catch {
    // useApi affiche l'erreur (409 : déjà levée ou annulée)
  }
}

watch(season, load)
onMounted(load)
</script>

<template>
  <div>
    <AdminPageHeader :title="t('card_suspensions.title')" :show-filters="false" />

    <p v-if="!allowed" class="text-sm text-header-500">{{ t('card_suspensions.forbidden') }}</p>
    <template v-else>
      <p class="text-sm text-header-600 dark:text-header-300 mb-4">{{ t('card_suspensions.intro', { season }) }}</p>

      <div class="flex flex-wrap items-center gap-4 mb-4">
        <label class="text-sm">
          {{ t('card_suspensions.filter.state') }}
          <select v-model="stateFilter" class="ml-2 rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm">
            <option v-for="state in STATES" :key="state" :value="state">{{ t(`card_suspensions.state.${state}`) }}</option>
          </select>
        </label>
        <label class="text-sm">
          {{ t('card_suspensions.filter.search') }}
          <input v-model="search" type="search" class="ml-2 rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm">
        </label>
      </div>

      <p v-if="loading" class="text-sm text-header-500">{{ t('common.loading') }}</p>
      <p v-else-if="shown.length === 0" class="text-sm text-header-500">{{ t('card_suspensions.empty') }}</p>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm border border-header-200 dark:border-header-700">
          <thead>
            <tr class="bg-header-50 dark:bg-header-800">
              <th scope="col" class="px-2 py-1 text-left">{{ t('card_suspensions.column.player') }}</th>
              <th scope="col" class="px-2 py-1 text-left">{{ t('card_suspensions.column.team') }}</th>
              <th scope="col" class="px-2 py-1 text-left">{{ t('card_suspensions.column.match') }}</th>
              <th scope="col" class="px-2 py-1 text-left">{{ t('card_suspensions.column.rule') }}</th>
              <th scope="col" class="px-2 py-1 text-left">{{ t('card_suspensions.column.suspension') }}</th>
              <th scope="col" class="px-2 py-1 text-left">{{ t('card_suspensions.column.state') }}</th>
              <th scope="col" class="px-2 py-1 text-left">{{ t('card_suspensions.column.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in shown" :key="item.id" class="border-t border-header-200 dark:border-header-700 align-top">
              <th scope="row" class="px-2 py-1 text-left font-normal">
                <span class="font-medium">{{ item.name || item.matric }}</span>
                <span class="block text-xs text-header-500">{{ item.matric }}<template v-if="item.club"> · {{ item.club }}</template></span>
              </th>
              <td class="px-2 py-1">
                <NuxtLink v-if="item.team" :to="`/presence/team/${item.team.id}`" class="underline">{{ item.team.label ?? item.team.id }}</NuxtLink>
                <span v-else>—</span>
              </td>
              <td class="px-2 py-1">
                {{ item.competition.label ?? item.competition.code }}
                <span class="block text-xs text-header-500">{{ t('card_suspensions.match', { number: item.match.number ?? item.match.id, date: item.match.date ?? '' }) }}</span>
              </td>
              <td class="px-2 py-1">
                {{ ruleLabel(catalog, item.rule, locale) }} <span class="text-xs text-header-500">({{ t('card_suspensions.tally', { tally: item.tally }) }})</span>
                <span v-for="other in otherRules(item)" :key="other" class="block text-xs text-header-500">+ {{ other }}</span>
              </td>
              <td class="px-2 py-1">{{ suspensionLabel(item) }}</td>
              <td class="px-2 py-1">
                <template v-if="item.state === 'LIFTED'">{{ t('card_suspensions.lifted', { date: item.liftedAt?.slice(0, 10), by: item.liftedBy }) }}</template>
                <template v-else-if="item.state === 'CANCELLED'">{{ t('card_suspensions.cancelled', { date: item.cancelledAt?.slice(0, 10) }) }}</template>
                <template v-else>{{ t('card_suspensions.state.ACTIVE') }}</template>
                <span v-if="!item.notifiedAt" class="block text-xs text-header-500">{{ t('card_suspensions.not_notified') }}</span>
              </td>
              <td class="px-2 py-1 whitespace-nowrap">
                <button
                  v-if="item.state === 'ACTIVE'"
                  type="button"
                  class="px-2 py-1 rounded bg-primary-600 text-white text-xs hover:bg-primary-700"
                  @click="lifting = item"
                >
                  {{ t('card_suspensions.lift') }}
                </button>
                <NuxtLink v-if="item.reportId" :to="`/reports/${item.reportId}`" class="ml-2 underline text-xs">{{ t('card_suspensions.report') }}</NuxtLink>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <section class="mt-6">
        <button type="button" class="text-sm font-semibold inline-flex items-center gap-1" :aria-expanded="showRules" @click="showRules = !showRules">
          <UIcon :name="showRules ? 'heroicons:chevron-down' : 'heroicons:chevron-right'" class="w-4 h-4" />
          {{ t('card_suspensions.rules.title') }}
        </button>
        <template v-if="showRules">
          <p v-if="!catalog" class="text-sm text-header-500 mt-2">{{ t('card_suspensions.rules.none') }}</p>
          <ul v-else class="mt-2 text-sm list-disc pl-6">
            <li v-for="rule in catalog.rules" :key="rule.code">
              {{ locale === 'en' ? rule.labelEn : rule.labelFr }} :
              {{ t(`card_suspensions.matches.${rule.matches === 1 ? 'one' : 'many'}`, { matches: rule.matches }) }}<template v-if="rule.appealJury"> {{ t('card_suspensions.appeal_jury') }}</template>
            </li>
          </ul>
        </template>
      </section>
    </template>

    <AdminConfirmModal
      :open="lifting !== null"
      :title="t('card_suspensions.lift_title')"
      :message="t('card_suspensions.lift_message', { name: lifting?.name ?? '' })"
      :confirm-text="t('card_suspensions.lift')"
      :cancel-text="t('common.cancel')"
      @confirm="confirmLift"
      @close="lifting = null"
    />
  </div>
</template>
