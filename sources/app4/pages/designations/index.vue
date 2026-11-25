<script setup lang="ts">
import { canAddHolder, countStatuses, designationIdsOf, gamedayDates, gamedayRows, holderLabel, statusClasses, visibleRoles, type CoverageCell, type CoverageHolder, type CoverageMatrix, type DesignationTarget, type RoleDefinition, type RowKind } from '~/utils/designations'

/**
 * Suivi des Désignations, profils 1 et 2 (DOC/specs/FEATURE_DESIGNATIONS.md § 4, ticket designations/07) : matrice de
 * couverture (compétitions, journées, équipes × rôles), ajout et retrait depuis une case, récapitulatif par personne
 * et par club. Les états et l'héritage viennent de api2.
 */
definePageMeta({
  layout: 'admin',
  middleware: ['auth'],
})

type Tab = 'matrix' | 'summary' | 'facts'
interface SummaryPerson { matric: number, name: string, club: string | null, clubLabel: string, designations: Record<string, number>, gamedays: Record<string, number> }
interface SummaryClub { club: string | null, clubLabel: string, people: number, designations: Record<string, number>, gamedays: Record<string, number> }

const { t } = useI18n()
const api = useApi()
const toast = useToast()
const authStore = useAuthStore()
const workContext = useWorkContextStore()

if (authStore.profile > 2) {
  navigateTo('/')
}

const tab = ref<Tab>('matrix')
const onlyGaps = ref(false)
const competitionCodes = ref<string[]>([])
const chosenRoles = ref<string[]>([])
/** Rôles des responsables (premier tableau de chaque compétition) ; les autres sont des colonnes de journée. */
const MANAGER_ROLES = ['RC', 'RESP_EQUIPE']
const loading = ref(false)
const matrix = ref<CoverageMatrix | null>(null)
const roles = ref<Record<string, RoleDefinition>>({})
const summary = ref<{ people: SummaryPerson[], clubs: SummaryClub[] } | null>(null)
const summaryFrom = ref('')
const summaryTo = ref('')
const target = ref<DesignationTarget | null>(null)
/** Tableaux du récapitulatif repliés (tous dépliés par défaut). */
const collapsedSummary = ref<Set<string>>(new Set())
const toggleSummary = (section: string) => {
  const next = new Set(collapsedSummary.value)
  if (!next.delete(section)) next.add(section)
  collapsedSummary.value = next
}
const downloadSummary = async (section: 'people' | 'clubs', format: 'csv' | 'xlsx') => {
  const params = new URLSearchParams({ season: season.value, section, format })
  if (summaryFrom.value) params.set('from', summaryFrom.value)
  if (summaryTo.value) params.set('to', summaryTo.value)
  const buffer = await api.getBlob(`/admin/designations/summary?${params}`)
  const type = format === 'csv' ? 'text/csv' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
  const link = document.createElement('a')
  link.href = URL.createObjectURL(new Blob([buffer], { type }))
  link.download = `recapitulatif-${section}-${season.value}.${format}`
  link.click()
  URL.revokeObjectURL(link.href)
}
const removing = ref<CoverageHolder | null>(null)

const season = computed(() => workContext.season)
const counts = computed(() => (matrix.value ? countStatuses(matrix.value) : { red: 0, orange: 0 }))
const roleLabel = (code: string) => roles.value[code]?.label ?? code
const summaryRoles = computed(() => Object.keys(roles.value))
const pickableRoles = computed(() => [...MANAGER_ROLES, ...(matrix.value?.gamedayRoles ?? [])].map(code => ({ code, label: roleLabel(code) })))
const shownGamedayRoles = computed(() => visibleRoles(matrix.value?.gamedayRoles ?? [], chosenRoles.value))
const showsManager = (role: string) => chosenRoles.value.length === 0 || chosenRoles.value.includes(role)
/** Le tableau des responsables n'occupe une colonne que s'il est affiché ; sinon les journées prennent toute la largeur. */
const showsManagerTable = computed(() => showsManager('RC') || showsManager('RESP_EQUIPE'))

const loadRoles = async () => {
  roles.value = await api.get<Record<string, RoleDefinition>>('/admin/designations/roles')
}

const loadMatrix = async () => {
  if (!season.value) return
  loading.value = true
  try {
    matrix.value = await api.get<CoverageMatrix>('/admin/designations/coverage', {
      season: season.value,
      onlyGaps: onlyGaps.value ? '1' : '0',
      competitions: competitionCodes.value.join(','),
    })
  } finally {
    loading.value = false
  }
}

const loadSummary = async () => {
  if (!season.value) return
  loading.value = true
  try {
    const params: Record<string, string> = { season: season.value }
    if (summaryFrom.value) params.from = summaryFrom.value
    if (summaryTo.value) params.to = summaryTo.value
    summary.value = await api.get('/admin/designations/summary', params)
  } finally {
    loading.value = false
  }
}

const reload = () => (tab.value === 'matrix' ? loadMatrix() : tab.value === 'summary' ? loadSummary() : undefined)

/** Une ligne de phases regroupées (tournoi) désigne sur chacune de ses phases. */
const openAdd = (kind: RowKind, ids: (string | number)[], role: string, label: string) => {
  target.value = { kind, ids, role, label }
}

// Copie des RC d'une autre saison (reprise de l'ancienne page RC, retirée : ticket designations/08)
const copySource = ref('')
const copyOpen = ref(false)
const otherSeasons = computed(() => workContext.seasons.map(s => s.code).filter(code => code !== season.value))
const copyRc = async () => {
  if (!copySource.value || !season.value) return
  const result = await api.post<{ copied: number, skipped: number }>('/admin/operations/seasons/copy-rc', { sourceCode: copySource.value, targetCode: season.value })
  toast.add({ title: t('designations.copy_rc.done', { copied: result.copied, skipped: result.skipped }), color: 'success' })
  copySource.value = ''
  copyOpen.value = false
  await loadMatrix()
}

const saved = async () => {
  target.value = null
  await loadMatrix()
}

const confirmRemove = async () => {
  if (!removing.value) return
  try {
    for (const id of designationIdsOf(removing.value)) {
      await api.del(`/admin/designations/${id}`)
    }
    toast.add({ title: t('designations.removed'), color: 'success' })
    removing.value = null
    await loadMatrix()
  } catch {
    removing.value = null
  }
}

const cellTitle = (cell: CoverageCell) => cell.holders
  .map(holder => `${holderLabel(holder)} — ${t(`designations.origin.${holder.origin}`)}${holder.warnings.map(w => ` · ${t(`designations.warning.${w}`)}`).join('')}`)
  .join('\n')

watch([season, onlyGaps, competitionCodes], () => loadMatrix())
watch(tab, () => reload())
onMounted(async () => {
  await workContext.initContext?.()
  await loadRoles()
  await loadMatrix()
})
</script>

<template>
  <div>
    <AdminPageHeader :title="t('designations.title')" :show-filters="false" />

    <div class="flex flex-wrap items-center gap-3 mb-4">
      <div class="inline-flex rounded-md shadow-sm" role="tablist">
        <button
          v-for="name in (['matrix', 'summary', 'facts'] as Tab[])"
          :key="name"
          type="button"
          role="tab"
          :aria-selected="tab === name"
          class="px-4 py-2 text-sm font-medium border first:rounded-l-md last:rounded-r-md"
          :class="tab === name ? 'bg-primary-600 text-white border-primary-600' : 'bg-white dark:bg-header-900 text-header-700 dark:text-header-200 border-header-300 dark:border-header-700'"
          @click="tab = name"
        >
          {{ t(`designations.tab.${name}`) }}
        </button>
      </div>
      <span class="text-sm text-header-600 dark:text-header-300">{{ t('designations.season', { season }) }}</span>
      <button
        type="button"
        class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium rounded-lg border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 hover:bg-header-50 dark:hover:bg-header-800"
        @click="copyOpen = true"
      >
        <UIcon name="heroicons:document-duplicate" class="w-5 h-5" />
        {{ t('designations.copy_rc.button') }}
      </button>
    </div>

    <!-- Matrice de couverture (SUI-01 à SUI-05) -->
    <template v-if="tab === 'matrix'">
      <div class="flex flex-wrap items-center gap-4 mb-4">
        <AdminCompetitionSectionPicker v-model="competitionCodes" :season="season" />
        <DesignationsRolePicker v-model="chosenRoles" :roles="pickableRoles" />
        <label class="text-sm inline-flex items-center gap-2">
          <input v-model="onlyGaps" type="checkbox" class="rounded">
          {{ t('designations.only_gaps') }}
        </label>
        <span class="text-sm"><span class="px-2 py-0.5 rounded bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100">{{ t('designations.count.red', { n: counts.red }) }}</span></span>
        <span class="text-sm"><span class="px-2 py-0.5 rounded bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100">{{ t('designations.count.orange', { n: counts.orange }) }}</span></span>
      </div>

      <p v-if="loading" class="text-sm text-header-500">{{ t('common.loading') }}</p>
      <p v-else-if="matrix && matrix.competitions.length === 0" class="text-sm text-header-500">{{ t('designations.nothing') }}</p>

      <section v-for="competition in matrix?.competitions ?? []" :key="competition.code" class="mb-8">
        <h2 class="font-semibold mb-2">{{ competition.code }} — {{ competition.label }}</h2>
        <div class="grid gap-4" :class="{ 'xl:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]': showsManagerTable && shownGamedayRoles.length && competition.gamedays.length }">
          <!-- Responsables : RC et responsables d'équipe (SUI-02) -->
          <div v-if="showsManagerTable" class="overflow-x-auto">
            <table class="min-w-full text-sm border border-header-200 dark:border-header-700">
              <caption class="text-left text-xs uppercase tracking-wide text-header-500 py-1">{{ t('designations.table.managers') }}</caption>
              <thead>
                <tr class="bg-header-50 dark:bg-header-800">
                  <th scope="col" class="px-2 py-1 text-left">{{ t('designations.column.line') }}</th>
                  <th scope="col" class="px-2 py-1 text-left">{{ t('designations.column.holders') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="showsManager('RC')">
                  <th scope="row" class="px-2 py-1 text-left font-medium">{{ roleLabel('RC') }}</th>
                  <td class="px-2 py-1" :class="statusClasses(competition.cells.RC.status)" :title="cellTitle(competition.cells.RC)">
                    <DesignationsCellHolders
                      :cell="competition.cells.RC"
                      kind="competition"
                      :can-add="canAddHolder(competition.cells.RC, 'competition', roles.RC)"
                      @add="openAdd('competition', [competition.code], 'RC', competition.label)"
                      @remove="removing = $event"
                    />
                  </td>
                </tr>
                <template v-if="showsManager('RESP_EQUIPE')">
                  <tr v-for="team in competition.teams" :key="`team-${team.id}`">
                    <th scope="row" class="px-2 py-1 text-left font-normal">{{ team.label }}<span v-if="team.club" class="ml-1 font-mono text-xs text-header-500 dark:text-header-300">({{ team.club }})</span></th>
                    <td class="px-2 py-1" :class="statusClasses(team.cells.RESP_EQUIPE.status)" :title="cellTitle(team.cells.RESP_EQUIPE)">
                      <DesignationsCellHolders
                        :cell="team.cells.RESP_EQUIPE"
                        kind="team"
                        :can-add="canAddHolder(team.cells.RESP_EQUIPE, 'team', roles.RESP_EQUIPE)"
                        @add="openAdd('team', [team.id], 'RESP_EQUIPE', team.label)"
                        @remove="removing = $event"
                      />
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>

          <!-- Journées × rôles ; phases d'un tournoi regroupées par dates et lieu (SUI-03 : héritage visible) -->
          <div v-if="shownGamedayRoles.length && competition.gamedays.length" class="overflow-x-auto">
            <table class="min-w-full text-sm border border-header-200 dark:border-header-700">
              <caption class="text-left text-xs uppercase tracking-wide text-header-500 py-1">{{ t('designations.table.gamedays') }}</caption>
              <thead>
                <tr class="bg-header-50 dark:bg-header-800">
                  <th scope="col" class="px-2 py-1 text-left">{{ t('designations.column.gameday') }}</th>
                  <th scope="col" class="px-2 py-1 text-left">{{ t('designations.column.organiser') }}</th>
                  <th v-for="role in shownGamedayRoles" :key="role" scope="col" class="px-2 py-1 text-left">{{ roleLabel(role) }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in gamedayRows(competition)" :key="row.key">
                  <th scope="row" class="px-2 py-1 text-left font-normal">
                    <span class="whitespace-nowrap">{{ gamedayDates(row.start, row.end) }}</span><span v-if="row.place"> · {{ row.place }}</span>
                    <span class="block text-xs text-header-500">{{ row.labels.length > 1 ? t('designations.phases', { n: row.labels.length }) : row.labels[0] }}</span>
                  </th>
                  <td class="px-2 py-1 align-top" :title="row.organiser.code">{{ row.organiser.label }}</td>
                  <td v-for="role in shownGamedayRoles" :key="role" class="px-2 py-1 align-top" :class="statusClasses(row.cells[role].status)" :title="cellTitle(row.cells[role])">
                    <DesignationsCellHolders
                      :cell="row.cells[role]"
                      kind="gameday"
                      :can-add="canAddHolder(row.cells[role], 'gameday', roles[role])"
                      @add="openAdd('gameday', row.ids, role, `${competition.code} · ${row.labels.length > 1 ? t('designations.phases', { n: row.labels.length }) : row.labels[0]}`)"
                      @remove="removing = $event"
                    />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    </template>

    <!-- Relevé des faits pour les rétributions (REL-01 à REL-05) -->
    <DesignationsFactsStatement v-else-if="tab === 'facts'" :season="season" />

    <!-- Récapitulatif par personne et par club (SUI-06) -->
    <template v-else>
      <form class="flex flex-wrap items-end gap-4 mb-4" @submit.prevent="loadSummary">
        <label class="text-sm">{{ t('designations.period.from') }}
          <input v-model="summaryFrom" type="date" class="ml-2 rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm">
        </label>
        <label class="text-sm">{{ t('designations.period.to') }}
          <input v-model="summaryTo" type="date" class="ml-2 rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm">
        </label>
        <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm">{{ t('designations.period.apply') }}</button>
      </form>
      <p class="text-sm text-header-600 dark:text-header-300 mb-2">{{ t('designations.summary_help') }}</p>

      <template v-for="section in (['people', 'clubs'] as const)" :key="section">
        <div class="flex items-center gap-2 mt-6 mb-2 bg-header-200 dark:bg-header-700 px-4 py-2 rounded">
          <button type="button" class="flex items-center gap-2 flex-1 text-left cursor-pointer" :aria-expanded="!collapsedSummary.has(section)" @click="toggleSummary(section)">
            <UIcon name="heroicons:chevron-right" class="w-4 h-4 text-header-900 dark:text-header-50 transition-transform" :class="{ 'rotate-90': !collapsedSummary.has(section) }" />
            <h2 class="text-sm font-semibold text-header-900 dark:text-header-50">{{ t(`designations.summary.${section}`) }}</h2>
          </button>
          <button type="button" class="px-3 py-1 text-sm rounded-lg border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50" @click="downloadSummary(section, 'csv')">CSV</button>
          <button type="button" class="px-3 py-1 text-sm rounded-lg border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50" @click="downloadSummary(section, 'xlsx')">Excel</button>
        </div>
        <div v-show="!collapsedSummary.has(section)" class="overflow-x-auto">
          <table class="min-w-full text-sm border border-header-200 dark:border-header-700">
            <thead>
              <tr class="bg-header-50 dark:bg-header-800">
                <th scope="col" class="px-2 py-1 text-left">{{ t(section === 'people' ? 'designations.column.person' : 'designations.column.club') }}</th>
                <th v-if="section === 'clubs'" scope="col" class="px-2 py-1 text-right">{{ t('designations.column.people') }}</th>
                <th v-for="role in summaryRoles" :key="role" scope="col" class="px-2 py-1 text-right">{{ roleLabel(role) }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in (section === 'people' ? summary?.people : summary?.clubs) ?? []" :key="'matric' in row ? row.matric : (row.club ?? '-')">
                <th scope="row" class="px-2 py-1 text-left font-normal">
                  <template v-if="'matric' in row">{{ row.name }} <span class="text-header-500">({{ row.club ? (row.clubLabel ? `${row.clubLabel} · ${row.club}` : row.club) : '—' }})</span></template>
                  <template v-else-if="row.club">{{ row.clubLabel }} <span class="text-header-500">({{ row.club }})</span></template>
                  <template v-else>{{ t('designations.no_club') }}</template>
                </th>
                <td v-if="'people' in row" class="px-2 py-1 text-right">{{ row.people }}</td>
                <td v-for="role in summaryRoles" :key="role" class="px-2 py-1 text-right tabular-nums">
                  <template v-if="row.designations[role]">
                    {{ row.designations[role] }}<span v-if="row.gamedays[role]" class="text-header-500"> · {{ t('designations.gamedays_short', { n: row.gamedays[role] }) }}</span>
                  </template>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </template>

    <!-- Copier les RC d'une autre saison -->
    <AdminModal :open="copyOpen" :title="t('designations.copy_rc.title', { season })" @close="copyOpen = false">
      <form class="space-y-4" @submit.prevent="copyRc">
        <div>
          <label for="copy-rc-source" class="block text-sm font-medium mb-1">{{ t('designations.copy_rc.label') }}</label>
          <select id="copy-rc-source" v-model="copySource" required class="w-full rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm">
            <option value="">—</option>
            <option v-for="code in otherSeasons" :key="code" :value="code">{{ code }}</option>
          </select>
          <p class="text-xs text-header-500 mt-1">{{ t('designations.copy_rc.help', { season }) }}</p>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="px-3 py-1.5 rounded border border-header-300 dark:border-header-700 text-sm" @click="copyOpen = false">{{ t('common.cancel') }}</button>
          <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm disabled:opacity-50" :disabled="!copySource">{{ t('designations.copy_rc.submit') }}</button>
        </div>
      </form>
    </AdminModal>

    <!-- Désigner depuis une case -->
    <DesignationsAddModal :target="target" :season="season" :roles="roles" @close="target = null" @saved="saved" />

    <AdminConfirmModal
      :open="removing !== null"
      :title="t('designations.remove.title')"
      :message="removing ? t('designations.remove.message', { name: holderLabel(removing) }) : ''"
      :confirm-text="t('designations.remove.confirm')"
      :cancel-text="t('common.cancel')"
      @confirm="confirmRemove"
      @close="removing = null"
    />
  </div>
</template>
