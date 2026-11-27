<script setup lang="ts">
import { COMPETITION_STATUS_CLASSES, canAddHolder, canEditRole, isCompetitionEnded, countStatuses, designationIdsOf, gamedayDates, gamedayRows, holderLabel, statusClasses, visibleRoles, type CoverageCell, type CoverageHolder, type CoverageMatrix, type DesignationTarget, type RoleDefinition, type RowKind } from '~/utils/designations'

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

if (authStore.profile > 3) {
  navigateTo('/')
}

/** Récapitulatif, relevé des faits et copie des RC : profils 1 et 2 seulement (le profil 3 a le suivi). */
const canSeeStatements = computed(() => authStore.profile <= 2)
const tabs = computed<Tab[]>(() => (canSeeStatements.value ? ['matrix', 'summary', 'facts'] : ['matrix']))
const tab = ref<Tab>('matrix')
/** Colonnes et tableaux masqués par le menu Affichage (rien de masqué par défaut). */
const DISPLAY_STORAGE_KEY = 'app4_designations_display'
const loadDisplay = (): { hiddenRoles?: string[], onlyGaps?: boolean, competitionCodes?: string[] } => {
  try { return JSON.parse(localStorage.getItem(DISPLAY_STORAGE_KEY) ?? '{}') } catch { return {} }
}
const savedDisplay = loadDisplay()
const hiddenRoles = ref<string[]>(Array.isArray(savedDisplay.hiddenRoles) ? savedDisplay.hiddenRoles : [])
const onlyGaps = ref(savedDisplay.onlyGaps === true)
const competitionCodes = ref<string[]>(Array.isArray(savedDisplay.competitionCodes) ? savedDisplay.competitionCodes : [])
const SECTION_STORAGE_KEY = 'app4_designations_section'
watch([hiddenRoles, onlyGaps, competitionCodes], () => {
  try { localStorage.setItem(DISPLAY_STORAGE_KEY, JSON.stringify({ hiddenRoles: hiddenRoles.value, onlyGaps: onlyGaps.value, competitionCodes: competitionCodes.value })) } catch { /* ignore */ }
})
const displayOpen = ref(false)
const displayRef = ref<HTMLDivElement | null>(null)
const isShown = (code: string) => !hiddenRoles.value.includes(code)
const toggleRole = (code: string) => {
  hiddenRoles.value = isShown(code) ? [...hiddenRoles.value, code] : hiddenRoles.value.filter(c => c !== code)
}
const onClickOutside = (e: MouseEvent) => {
  if (displayRef.value && !displayRef.value.contains(e.target as Node)) displayOpen.value = false
}
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
/** Verrou d'une ligne : compétition terminée, ou rôle que le profil connecté ne peut pas désigner (consultation seule). */
const isReadonly = (competition: { statut: string }, role: string) => isCompetitionEnded(competition) || !canEditRole(roles.value[role], authStore.profile)
const roleLabel = (code: string) => roles.value[code]?.label ?? code
const summaryRoles = computed(() => Object.keys(roles.value))
/** Colonne « Club organisateur » du tableau des journées, choisie dans le menu Rôles comme un rôle. */
const ORGANISER_COLUMN = 'ORGANISER'
const pickableGroups = computed(() => [
  { key: 'managers', label: t('designations.roles_picker.managers_group'), roles: MANAGER_ROLES.map(code => ({ code, label: roleLabel(code) })) },
  {
    key: 'gamedays',
    label: t('designations.roles_picker.gamedays_group'),
    roles: [ORGANISER_COLUMN, ...(matrix.value?.gamedayRoles ?? [])].map(code => ({ code, label: code === ORGANISER_COLUMN ? t('designations.column.organiser') : roleLabel(code) })),
  },
])
const showsOrganiser = computed(() => isShown(ORGANISER_COLUMN))
const activeDisplayCount = computed(() => (onlyGaps.value ? 1 : 0) + pickableGroups.value.reduce((sum, group) => sum + group.roles.filter(role => isShown(role.code)).length, 0))
const shownGamedayRoles = computed(() => visibleRoles(matrix.value?.gamedayRoles ?? [], hiddenRoles.value))
const showsManager = (role: string) => isShown(role)
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

/** Club organisateur d'une ligne de journées (une journée, ou les phases d'un tournoi) en cours de modification. */
const editingOrganiser = ref<{ ids: number[], label: string } | null>(null)
const organiserModalTitle = computed(() => editingOrganiser.value?.label ?? '')
const ORGANISER_LABEL_MAX = 40
const clearingOrganiser = ref<{ ids: number[], label: string } | null>(null)
const confirmClearOrganiser = async () => {
  const target = clearingOrganiser.value
  clearingOrganiser.value = null
  if (target) await saveOrganiser(target.ids, null)
}
const saveOrganiser = async (ids: number[], club: { code: string, libelle: string } | null) => {
  try {
    for (const id of ids) {
      await api.put(`/admin/gamedays/${id}`, club
        ? { organisateur: club.libelle.slice(0, ORGANISER_LABEL_MAX), codeOrganisateur: club.code }
        : { organisateur: '', codeOrganisateur: '' })
    }
    toast.add({ title: t('designations.organiser_saved'), color: 'success' })
    editingOrganiser.value = null
    await loadMatrix()
  } catch {
    editingOrganiser.value = null
  }
}

/** Lieu et département d'une ligne de journées (une journée, ou les phases d'un tournoi) en cours de modification. */
const editingPlace = ref<{ ids: number[], label: string, place: string, department: string } | null>(null)
const PLACE_MAX = 40
const DEPARTMENT_MAX = 3
const openPlaceEdit = (row: { ids: number[], place: string, department: string, start: string | null, end: string | null }) => {
  editingPlace.value = { ids: row.ids, label: gamedayDates(row.start, row.end), place: row.place, department: row.department }
}
/** Sans lieu, pas de département : un lieu supprimé ou vidé emporte son département. */
const savePlaceOf = async (ids: number[], place: string, department: string) => {
  const cleanPlace = place.trim().slice(0, PLACE_MAX)
  const cleanDepartment = cleanPlace === '' ? '' : department.trim().toUpperCase().slice(0, DEPARTMENT_MAX)
  try {
    for (const id of ids) {
      await api.put(`/admin/gamedays/${id}`, { lieu: cleanPlace, departement: cleanDepartment })
    }
    toast.add({ title: t('designations.place_saved'), color: 'success' })
    await loadMatrix()
  } finally {
    editingPlace.value = null
  }
}
const savePlace = () => (editingPlace.value ? savePlaceOf(editingPlace.value.ids, editingPlace.value.place, editingPlace.value.department) : undefined)
const clearingPlace = ref<{ ids: number[], label: string } | null>(null)
const confirmClearPlace = async () => {
  const target = clearingPlace.value
  clearingPlace.value = null
  if (target) await savePlaceOf(target.ids, '', '')
}
watch(() => editingPlace.value?.place, (place) => {
  if (editingPlace.value && (place ?? '').trim() === '') editingPlace.value.department = ''
})

const cellTitle = (cell: CoverageCell) => cell.holders
  .map(holder => `${holderLabel(holder)} — ${t(`designations.origin.${holder.origin}`)}${holder.warnings.map(w => ` · ${t(`designations.warning.${w}`)}`).join('')}`)
  .join('\n')

watch([season, onlyGaps, competitionCodes], () => loadMatrix())
watch(tab, () => reload())
onUnmounted(() => document.removeEventListener('click', onClickOutside))
onMounted(async () => {
  document.addEventListener('click', onClickOutside)
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
          v-for="name in tabs"
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
        v-if="canSeeStatements"
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
        <AdminCompetitionSectionPicker v-model="competitionCodes" :season="season" :storage-key="SECTION_STORAGE_KEY" />
        <!-- Affichage : filtre des lignes + tableaux et colonnes affichés -->
        <div ref="displayRef" class="relative">
          <button
            type="button"
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border"
            :class="activeDisplayCount > 0 ? 'text-primary-700 dark:text-primary-200 border-primary-400 bg-primary-50 dark:bg-primary-950' : 'text-header-900 dark:text-header-200 bg-white dark:bg-header-900 border-header-300 dark:border-header-700 hover:bg-header-50 dark:hover:bg-header-800'"
            @click="displayOpen = !displayOpen"
          >
            <UIcon name="heroicons:adjustments-horizontal" class="w-5 h-5" />
            {{ t('designations.display.title') }}
            <span v-if="activeDisplayCount > 0" class="inline-flex items-center justify-center min-w-5 h-5 px-1 text-sm font-semibold text-white bg-primary-600 rounded-full">{{ activeDisplayCount }}</span>
            <UIcon name="heroicons:chevron-down" class="w-4 h-4 transition-transform" :class="{ 'rotate-180': displayOpen }" />
          </button>
          <div v-show="displayOpen" class="absolute z-20 mt-1 w-64 bg-white dark:bg-header-900 border border-header-200 dark:border-header-700 rounded-lg shadow-lg py-1 left-0">
            <div class="px-3 py-1 text-xs font-semibold text-header-900 dark:text-header-300 uppercase tracking-wider">{{ t('designations.display.filters_section') }}</div>
            <label class="flex items-center gap-2 px-4 py-2 text-sm text-header-900 dark:text-header-50 cursor-pointer hover:bg-header-50 dark:hover:bg-header-800">
              <input v-model="onlyGaps" type="checkbox" class="rounded border-header-300 dark:border-header-700 text-primary-600">
              {{ t('designations.only_gaps') }}
            </label>
            <template v-for="group in pickableGroups" :key="group.key">
              <div class="mt-1 px-3 py-1 text-xs font-semibold text-header-900 dark:text-header-300 uppercase tracking-wider border-t border-header-100 dark:border-header-800">{{ group.label }}</div>
              <label
                v-for="role in group.roles"
                :key="role.code"
                class="flex items-center gap-2 px-4 py-2 text-sm text-header-900 dark:text-header-50 cursor-pointer hover:bg-header-50 dark:hover:bg-header-800"
              >
                <input type="checkbox" :checked="isShown(role.code)" class="rounded border-header-300 dark:border-header-700 text-primary-600" @change="toggleRole(role.code)">
                {{ role.label }}
              </label>
            </template>
          </div>
        </div>
        <span class="text-sm"><span class="px-2 py-0.5 rounded bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100">{{ t('designations.count.red', { n: counts.red }) }}</span></span>
        <span class="text-sm"><span class="px-2 py-0.5 rounded bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100">{{ t('designations.count.orange', { n: counts.orange }) }}</span></span>
      </div>

      <p v-if="loading" class="text-sm text-header-500">{{ t('common.loading') }}</p>
      <p v-else-if="matrix && matrix.competitions.length === 0" class="text-sm text-header-500">{{ t('designations.nothing') }}</p>

      <section v-for="competition in matrix?.competitions ?? []" :key="competition.code" class="mb-8">
        <h2 class="font-semibold mb-2 flex flex-wrap items-center gap-2">
          {{ competition.code }} — {{ competition.label }}
          <span v-if="competition.statut" class="px-2 py-0.5 text-xs font-medium rounded uppercase" :class="COMPETITION_STATUS_CLASSES[competition.statut]" :title="isCompetitionEnded(competition) ? t('competition.ended_description') : ''">
            {{ t(`competitions.status.${competition.statut}`) }}
          </span>
        </h2>
        <div class="space-y-4">
          <!-- Responsables : RC et responsables d'équipe (SUI-02) -->
          <div v-if="showsManagerTable" class="overflow-x-auto lg:w-1/2">
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
                      :can-add="!isReadonly(competition, 'RC') && canAddHolder(competition.cells.RC, 'competition', roles.RC)"
                      :readonly="isReadonly(competition, 'RC')"
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
                        :can-add="!isReadonly(competition, 'RESP_EQUIPE') && canAddHolder(team.cells.RESP_EQUIPE, 'team', roles.RESP_EQUIPE)"
                        :readonly="isReadonly(competition, 'RESP_EQUIPE')"
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
          <div v-if="(shownGamedayRoles.length || showsOrganiser) && competition.gamedays.length" class="overflow-x-auto">
            <table class="min-w-full text-sm border border-header-200 dark:border-header-700">
              <caption class="text-left text-xs uppercase tracking-wide text-header-500 py-1">{{ t('designations.table.gamedays') }}</caption>
              <thead>
                <tr class="bg-header-50 dark:bg-header-800">
                  <th scope="col" class="px-2 py-1 text-left">{{ t('designations.column.gameday') }}</th>
                  <th v-if="showsOrganiser" scope="col" class="px-2 py-1 text-left">{{ t('designations.column.organiser') }}</th>
                  <th v-for="role in shownGamedayRoles" :key="role" scope="col" class="px-2 py-1 text-left">{{ roleLabel(role) }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in gamedayRows(competition)" :key="row.key">
                  <th scope="row" class="px-2 py-1 text-left font-normal">
                    <span class="whitespace-nowrap">{{ gamedayDates(row.start, row.end) }}</span><span v-if="row.place"> · {{ row.place }}</span><span v-if="row.department" class="text-header-500"> ({{ row.department }})</span>
                    <button v-if="!isCompetitionEnded(competition)" type="button" class="ml-1 align-middle text-primary-600 dark:text-primary-300 hover:text-primary-800 dark:hover:text-primary-200" :title="t('designations.place_edit')" @click="openPlaceEdit(row)">
                      <UIcon name="heroicons:pencil-square" class="w-5 h-5" />
                    </button>
                    <button v-if="row.place && !isCompetitionEnded(competition)" type="button" class="ml-1 align-middle text-danger-600 dark:text-danger-300 hover:text-danger-800 dark:hover:text-danger-200" :title="t('designations.place_clear')" @click="clearingPlace = { ids: row.ids, label: row.place }">
                      <UIcon name="heroicons:trash" class="w-5 h-5" />
                    </button>
                    <span class="block text-xs text-header-500">{{ row.labels.length > 1 ? t('designations.phases', { n: row.labels.length }) : row.labels[0] }}</span>
                  </th>
                  <td v-if="showsOrganiser" class="px-2 py-1 align-top min-w-40" :class="row.organiser.code ? statusClasses('green') : ''" :title="row.organiser.code">
                    <div class="flex items-center gap-1">
                      <span>{{ row.organiser.label || '-' }}</span>
                      <button v-if="!isCompetitionEnded(competition)" type="button" class="text-primary-600 dark:text-primary-300 hover:text-primary-800 dark:hover:text-primary-200" :title="t('designations.organiser_edit')" @click="editingOrganiser = { ids: row.ids, label: `${gamedayDates(row.start, row.end)}${row.place ? ' · ' + row.place : ''}` }">
                        <UIcon name="heroicons:pencil-square" class="w-5 h-5" />
                      </button>
                      <button v-if="row.organiser.label && !isCompetitionEnded(competition)" type="button" class="text-danger-600 dark:text-danger-300 hover:text-danger-800 dark:hover:text-danger-200" :title="t('designations.organiser_clear')" @click="clearingOrganiser = { ids: row.ids, label: row.organiser.label }">
                        <UIcon name="heroicons:trash" class="w-5 h-5" />
                      </button>
                    </div>
                  </td>
                  <td v-for="role in shownGamedayRoles" :key="role" class="px-2 py-1 align-top" :class="statusClasses(row.cells[role].status)" :title="cellTitle(row.cells[role])">
                    <DesignationsCellHolders
                      :cell="row.cells[role]"
                      kind="gameday"
                      :can-add="!isReadonly(competition, role) && canAddHolder(row.cells[role], 'gameday', roles[role])"
                      :readonly="isReadonly(competition, role)"
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

    <!-- Club organisateur : recherche dans une fenêtre, le tableau ne doit pas rogner la liste de résultats -->
    <AdminModal :open="editingOrganiser !== null" focus-first-field :title="t('designations.organiser_edit')" @close="editingOrganiser = null">
      <div v-if="editingOrganiser" class="space-y-3 min-h-72">
        <p class="text-sm text-header-600 dark:text-header-300">{{ organiserModalTitle }}</p>
        <AdminTextAutocomplete
          model-value=""
          api-url="/admin/clubs/search-all"
          label-field="libelle"
          detail-field="code"
          :placeholder="t('designations.column.organiser')"
          @select="(club: { code: string, libelle: string }) => saveOrganiser(editingOrganiser!.ids, club)"
        />
      </div>
    </AdminModal>

    <!-- Lieu et département : commune en autocomplete (elle renseigne le département), modifiables à la main -->
    <AdminModal :open="editingPlace !== null" focus-first-field :title="t('designations.place_edit')" @close="editingPlace = null">
      <form v-if="editingPlace" class="space-y-3 min-h-72" @submit.prevent="savePlace">
        <p class="text-sm text-header-600 dark:text-header-300">{{ editingPlace.label }}</p>
        <div>
          <label class="block text-sm font-medium mb-1">{{ t('designations.column.place') }}</label>
          <AdminTextAutocomplete
            v-model="editingPlace.place"
            api-url="/admin/gamedays/autocomplete/communes"
            label-field="label"
            detail-field="detail"
            :placeholder="t('designations.column.place')"
            :maxlength="PLACE_MAX"
            @select="(item: { departement?: string }) => { if (item.departement && editingPlace) editingPlace.department = item.departement }"
          />
        </div>
        <div>
          <label for="place-department" class="block text-sm font-medium mb-1">{{ t('designations.column.department') }}</label>
          <input id="place-department" v-model="editingPlace.department" type="text" :maxlength="DEPARTMENT_MAX" class="w-24 px-3 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 uppercase">
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="px-3 py-1.5 rounded border border-header-300 dark:border-header-700 text-sm" @click="editingPlace = null">{{ t('common.cancel') }}</button>
          <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm">{{ t('common.save') }}</button>
        </div>
      </form>
    </AdminModal>

    <!-- Désigner depuis une case -->
    <DesignationsAddModal :target="target" :season="season" :roles="roles" @close="target = null" @saved="saved" />

    <AdminConfirmModal
      :open="clearingOrganiser !== null"
      :title="t('designations.organiser_clear_title')"
      :message="t('designations.organiser_clear_message', { club: clearingOrganiser?.label ?? '' })"
      :confirm-text="t('designations.remove.confirm')"
      :cancel-text="t('common.cancel')"
      @confirm="confirmClearOrganiser"
      @close="clearingOrganiser = null"
    />
    <AdminConfirmModal
      :open="clearingPlace !== null"
      :title="t('designations.place_clear_title')"
      :message="t('designations.place_clear_message', { place: clearingPlace?.label ?? '' })"
      :confirm-text="t('designations.remove.confirm')"
      :cancel-text="t('common.cancel')"
      @confirm="confirmClearPlace"
      @close="clearingPlace = null"
    />
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
