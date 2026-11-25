<script setup lang="ts">
import type { Gameday, GamedayFormData, GamedayBulkCalendarData } from '~/types/gamedays'
import type { PaginatedResponse } from '~/types'
import { canShowSchemaView, gamedaysViewQuery, parseGamedaysView, type GamedaysView } from '~/utils/gamedaysView'

definePageMeta({
  layout: 'admin',
  middleware: 'auth',
})

const { t, locale } = useI18n()
const api = useApi()
const toast = useToast()
const workContext = useWorkContextStore()
const authStore = useAuthStore()

// ─── State ───
const loading = ref(false)
const gamedays = ref<Gameday[]>([])
const total = ref(0)
const page = ref(1)
const totalPages = ref(0)

const hasContextFilter = computed(() => !!(workContext.pageCompetitionCodeAll || workContext.pageEventGroupSelection))
const limit = ref(hasContextFilter.value ? 0 : 25)

// Filters
// ?search=ID: deep link to one gameday (e.g. from the club ranking alerts)
const searchQuery = ref(typeof useRoute().query.search === 'string' ? useRoute().query.search as string : '')
const selectedMonth = ref('')
const selectedSort = ref('date_asc')

// Selection
const selectedIds = ref<number[]>([])

// Modals
const formModalOpen = ref(false)
const deleteConfirmOpen = ref(false)
const duplicateConfirmOpen = ref(false)
const bulkDeleteConfirmOpen = ref(false)
const bulkPublishConfirmOpen = ref(false)
const bulkCalendarModalOpen = ref(false)
const officialsModalOpen = ref(false)

// Form
const editingGameday = ref<Gameday | null>(null)
const formData = ref<GamedayFormData>(getDefaultFormData())
const formError = ref('')
const formSaving = ref(false)

// Duplicate
const duplicateGameday = ref<Gameday | null>(null)
const duplicateIncludeMatches = ref(false)

// Bulk calendar
const bulkCalendarData = ref<Omit<GamedayBulkCalendarData, 'ids'>>({
  nom: '',
  dateDebut: '',
  dateFin: '',
  lieu: '',
  departement: '',
})

// Bulk officials

// Officials modal: Désignations de la journée (FEATURE_DESIGNATIONS.md § 8.4, ticket designations/08)
const officialsGameday = ref<Gameday | null>(null)
/** Colonnes legacy des officiels : recopiées depuis les Désignations, jamais envoyées par le formulaire (DES-11). */
const OFFICIAL_FIELDS = ['responsableInsc', 'responsableR1', 'delegue', 'chefArbitre', 'repAthletes', 'arbNj1', 'arbNj2', 'arbNj3', 'arbNj4', 'arbNj5'] as const
// Le code du club organisateur suit le libellé : une saisie libre le vide, le choix d'un club de la liste le renseigne
const onOrganisateurInput = (value: string) => {
  if (value !== formData.value.organisateur) formData.value.codeOrganisateur = ''
  formData.value.organisateur = value
}

const withoutOfficials = <T extends Record<string, unknown>>(data: T) =>
  Object.fromEntries(Object.entries(data).filter(([key]) => !(OFFICIAL_FIELDS as readonly string[]).includes(key)))

// Bulk actions dropdown
const bulkActionsOpen = ref(false)
const bulkActionsRef = ref<HTMLDivElement | null>(null)

// Inline editing
const editingCell = ref<{ id: number; field: string } | null>(null)
const editingValue = ref('')
const editingOriginalValue = ref('')

// ─── Permissions ───
const canEdit = computed(() => authStore.profile <= 4)
const canSelect = computed(() => authStore.profile <= 3)

// A gameday is read-only when its competition is ended (statut END), which also
// locks the competition. The list can mix competitions, so this is per-row.
const canEditGameday = (g: Gameday) => canEdit.value && g.competitionStatut !== 'END'

// ─── Views (§ 5.3): gameday list, or schema of the competition selected in the header ───
const route = useRoute()
const router = useRouter()
const view = computed<GamedaysView>({
  get: () => parseGamedaysView(route.query.view),
  set: (value) => { void router.replace({ query: gamedaysViewQuery(route.query, value) }) },
})
const schemaAvailable = computed(() => canShowSchemaView(workContext.pageCompetitionCodeAll))

// Row « Schéma » button: select the row's competition, then show its schema in this tab
function goToSchema(competitionCode: string) {
  workContext.setPageCompetitionAll(competitionCode)
  view.value = 'schema'
}

// ─── Computed ───
// Check if a single CP competition is selected (to show Niveau/Tour/Equipes columns)
const showCPColumns = computed(() => {
  if (!workContext.pageCompetitionCodeAll) return false
  const comp = gamedays.value.find(g => g.codeCompetition === workContext.pageCompetitionCodeAll)
  return comp?.competitionTypeClt === 'CP'
})

// ─── Default form data ───
function getDefaultFormData(): GamedayFormData {
  return {
    codeCompetition: '',
    codeSaison: workContext.season || '',
    phase: '',
    niveau: 1,
    etape: 1,
    nbEquipes: 1,
    type: 'C',
    dateDebut: '',
    dateFin: '',
    nom: '',
    libelle: '',
    lieu: '',
    departement: '',
    planEau: '',
    organisateur: '',
    codeOrganisateur: '',
    responsableInsc: '',
    responsableR1: '',
    delegue: '',
    chefArbitre: '',
    repAthletes: '',
    arbNj1: '',
    arbNj2: '',
    arbNj3: '',
    arbNj4: '',
    arbNj5: '',
  }
}

// ─── Load data ───
const loadGamedays = async () => {
  if (!workContext.season || !hasContextFilter.value) {
    gamedays.value = []
    total.value = 0
    totalPages.value = 0
    return
  }

  loading.value = true
  try {
    const params: Record<string, string | number> = {
      season: workContext.season,
      page: page.value,
      limit: limit.value,
      sort: selectedSort.value,
    }

    // Competition filter
    if (workContext.pageCompetitionCodeAll) {
      // A specific competition is selected
      params.competitions = workContext.pageCompetitionCodeAll
    } else if (workContext.pageEventGroupType === 'group') {
      // "All competitions" with a group selected: resolve group to competition codes
      const group = workContext.uniqueGroups.find(g => g.code === workContext.pageEventGroupValue)
      if (group) {
        const contextCodes = new Set(workContext.competitionCodes)
        const groupCodes = group.competitions.filter(c => contextCodes.has(c))
        if (groupCodes.length > 0) params.competitions = groupCodes.join(',')
      }
    } else if (workContext.pageEventGroupType === 'event') {
      // "All competitions" with an event selected
      params.event = workContext.pageEventGroupValue
    } else if (workContext.hasValidContext && workContext.competitionCodes.length > 0) {
      params.competitions = workContext.competitionCodes.join(',')
    }
    if (selectedMonth.value) {
      params.month = selectedMonth.value
    }
    if (searchQuery.value.trim()) {
      params.search = searchQuery.value.trim()
    }

    const response = await api.get<PaginatedResponse<Gameday>>('/admin/gamedays', params)
    gamedays.value = response.items
    total.value = response.total
    totalPages.value = response.totalPages

    // Clear selection on data change
    selectedIds.value = []
  } catch (error) {
    console.error('Error loading gamedays:', error)
  } finally {
    loading.value = false
  }
}

// ─── Close dropdown on outside click ───
const onClickOutside = (e: MouseEvent) => {
  if (bulkActionsRef.value && !bulkActionsRef.value.contains(e.target as Node)) {
    bulkActionsOpen.value = false
  }
}
onMounted(async () => {
  await workContext.initContext()
  // Deep link (club ranking alerts): ?competition=CODE&search=ID
  const linked = route.query.competition
  if (typeof linked === 'string' && linked) workContext.setPageCompetitionAll(linked)
  document.addEventListener('click', onClickOutside)
})
onUnmounted(() => document.removeEventListener('click', onClickOutside))

// ─── Watchers ───
watch(() => [workContext.initialized, workContext.season], () => {
  if (workContext.initialized && workContext.season) {
    loadGamedays()
  }
}, { immediate: true })

watch([page, limit, selectedSort], () => {
  loadGamedays()
})

watch([() => workContext.pageCompetitionCodeAll, () => workContext.pageEventGroupSelection, selectedMonth], () => {
  page.value = 1
  limit.value = (workContext.pageCompetitionCodeAll || workContext.pageEventGroupSelection) ? 0 : 25
  loadGamedays()
})

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout> | null = null
watch(searchQuery, () => {
  if (searchTimeout) clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    page.value = 1
    loadGamedays()
  }, 300)
})

// ─── Format helpers ───
const formatDate = (date: string | null) => {
  if (!date || date === '0000-00-00') return '-'
  const d = new Date(date)
  if (isNaN(d.getTime())) return '-'
  if (locale.value === 'fr') {
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' })
  }
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const formatDateRange = (debut: string | null, fin: string | null) => {
  if (!debut && !fin) return '-'
  if (!fin || debut === fin) return formatDate(debut)
  return `${formatDate(debut)} - ${formatDate(fin)}`
}

// ─── Selection ───
const toggleSelectAll = () => {
  if (selectedIds.value.length === gamedays.value.length) {
    selectedIds.value = []
  } else {
    selectedIds.value = gamedays.value.map(g => g.id)
  }
}

const toggleSelect = (id: number) => {
  const idx = selectedIds.value.indexOf(id)
  if (idx > -1) {
    selectedIds.value.splice(idx, 1)
  } else {
    selectedIds.value.push(id)
  }
}

// ─── Toggle Publication ───
const togglePublication = async (gameday: Gameday) => {
  try {
    const response = await api.patch<{ publication: boolean }>(`/admin/gamedays/${gameday.id}/publication`)
    gameday.publication = response.publication
    toast.add({ title: t('common.saved'), color: 'success' })
  } catch {
    // Error already shown by useApi
  }
}

// ─── Toggle Type ───
const toggleType = async (gameday: Gameday) => {
  try {
    const response = await api.patch<{ type: string }>(`/admin/gamedays/${gameday.id}/type`)
    gameday.type = response.type as 'C' | 'E'
    toast.add({ title: t('common.saved'), color: 'success' })
  } catch {
    // Error already shown by useApi
  }
}

// ─── Inline Editing ───
const inlineFieldMap: Record<string, keyof Gameday> = {
  Phase: 'phase',
  Niveau: 'niveau',
  Etape: 'etape',
  Nbequipes: 'nbEquipes',
  Nom: 'nom',
  Date_debut: 'dateDebut',
  Date_fin: 'dateFin',
  Lieu: 'lieu',
  Departement: 'departement',
}

const startInlineEdit = (gameday: Gameday, field: string) => {
  if (!canEditGameday(gameday)) return
  editingCell.value = { id: gameday.id, field }
  const prop = inlineFieldMap[field]
  let val = prop ? String(gameday[prop] ?? '') : ''
  // For date fields, ensure YYYY-MM-DD format for input[type=date]
  if ((field === 'Date_debut' || field === 'Date_fin') && val.length > 10) {
    val = val.substring(0, 10)
  }
  editingValue.value = val
  editingOriginalValue.value = val
  nextTick(() => {
    const el = document.getElementById(`inline-${gameday.id}-${field}`)
    if (el) {
      el.focus()
      if (el instanceof HTMLInputElement && el.type !== 'date') el.select()
      if (el instanceof HTMLInputElement && el.type === 'date') {
        try { el.showPicker() } catch { /* ignore */ }
      }
    }
  })
}

const saveInlineEdit = async () => {
  if (!editingCell.value) return
  const { id, field } = editingCell.value
  const value = editingValue.value

  // Close editing
  editingCell.value = null

  // Only PATCH if value actually changed
  if (value === editingOriginalValue.value) return

  try {
    await api.patch(`/admin/gamedays/${id}/inline`, { field, value })
    // Refresh the row locally
    const gameday = gamedays.value.find(g => g.id === id)
    if (gameday) {
      const prop = inlineFieldMap[field]
      if (prop) {
        const numericFields = ['niveau', 'etape', 'nbEquipes']
        if (numericFields.includes(prop)) {
          ;(gameday as Record<string, unknown>)[prop] = value ? parseInt(value) : null
        } else {
          ;(gameday as Record<string, unknown>)[prop] = value || null
        }
      }
    }
    toast.add({ title: t('common.saved'), color: 'success' })
  } catch {
    // Error already shown by useApi
  }
}

const cancelInlineEdit = () => {
  editingCell.value = null
}

const handleInlineKeydown = (e: KeyboardEvent) => {
  if (e.key === 'Enter') saveInlineEdit()
  else if (e.key === 'Escape') cancelInlineEdit()
}

// ─── Modal: Add/Edit ───
const openAddModal = () => {
  editingGameday.value = null
  formData.value = getDefaultFormData()
  formData.value.codeSaison = workContext.season || ''
  // Pre-select competition from current filter, or if only one in context
  if (workContext.pageCompetitionCodeAll) {
    formData.value.codeCompetition = workContext.pageCompetitionCodeAll
  }
  else if (workContext.competitionCodes.length === 1) {
    formData.value.codeCompetition = workContext.competitionCodes[0] || ''
  }
  formError.value = ''
  formModalOpen.value = true
}

const openEditModal = (gameday: Gameday) => {
  editingGameday.value = gameday
  formData.value = {
    codeCompetition: gameday.codeCompetition,
    codeSaison: gameday.codeSaison,
    phase: gameday.phase || '',
    niveau: gameday.niveau ?? 1,
    etape: gameday.etape,
    nbEquipes: gameday.nbEquipes,
    type: gameday.type,
    dateDebut: gameday.dateDebut || '',
    dateFin: gameday.dateFin || '',
    nom: gameday.nom || '',
    libelle: gameday.libelle || '',
    lieu: gameday.lieu || '',
    departement: gameday.departement || '',
    planEau: gameday.planEau || '',
    organisateur: gameday.organisateur || '',
    codeOrganisateur: gameday.codeOrganisateur || '',
    responsableInsc: gameday.responsableInsc || '',
    responsableR1: gameday.responsableR1 || '',
    delegue: gameday.delegue || '',
    chefArbitre: gameday.chefArbitre || '',
    repAthletes: gameday.repAthletes || '',
    arbNj1: gameday.arbNj1 || '',
    arbNj2: gameday.arbNj2 || '',
    arbNj3: gameday.arbNj3 || '',
    arbNj4: gameday.arbNj4 || '',
    arbNj5: gameday.arbNj5 || '',
  }
  formError.value = ''
  formModalOpen.value = true
}

const submitForm = async () => {
  formError.value = ''

  if (!formData.value.codeCompetition || !formData.value.codeSaison || !formData.value.phase) {
    formError.value = 'Compétition, saison et phase sont obligatoires.'
    return
  }

  formSaving.value = true
  try {
    if (editingGameday.value) {
      await api.put(`/admin/gamedays/${editingGameday.value.id}`, withoutOfficials(formData.value))
      toast.add({ title: t('common.success'), description: t('gamedays.updated'), color: 'success' })
    } else {
      await api.post('/admin/gamedays', withoutOfficials(formData.value))
      toast.add({ title: t('common.success'), description: t('gamedays.added'), color: 'success' })
    }
    formModalOpen.value = false
    await loadGamedays()
  } catch (error: unknown) {
    formError.value = (error as { message?: string })?.message || t('common.error')
  } finally {
    formSaving.value = false
  }
}

// ─── Duplicate ───
const openDuplicateConfirm = (gameday: Gameday) => {
  duplicateGameday.value = gameday
  duplicateIncludeMatches.value = false
  duplicateConfirmOpen.value = true
}

const confirmDuplicate = async () => {
  if (!duplicateGameday.value) return
  formSaving.value = true
  try {
    await api.post(`/admin/gamedays/${duplicateGameday.value.id}/duplicate`, {
      includeMatches: duplicateIncludeMatches.value,
    })
    toast.add({ title: t('common.success'), description: t('gamedays.duplicated'), color: 'success' })
    duplicateConfirmOpen.value = false
    await loadGamedays()
  } catch {
    // Error already shown
  } finally {
    formSaving.value = false
  }
}

// ─── Delete ───
const gamedayToDelete = ref<Gameday | null>(null)

const openDeleteConfirm = (gameday: Gameday) => {
  gamedayToDelete.value = gameday
  deleteConfirmOpen.value = true
}

const confirmDelete = async () => {
  if (!gamedayToDelete.value) return
  formSaving.value = true
  try {
    await api.del(`/admin/gamedays/${gamedayToDelete.value.id}`)
    toast.add({ title: t('common.success'), description: t('gamedays.deleted'), color: 'success' })
    deleteConfirmOpen.value = false
    await loadGamedays()
  } catch (error: unknown) {
    const code = (error as { code?: string })?.code
    if (code === 'HAS_MATCHES') {
      toast.add({ title: t('common.error'), description: t('gamedays.delete_error_matches'), color: 'error' })
    } else if (code === 'HAS_EVENTS') {
      toast.add({ title: t('common.error'), description: t('gamedays.delete_error_events'), color: 'error' })
    }
    deleteConfirmOpen.value = false
  } finally {
    formSaving.value = false
  }
}

// ─── Bulk Actions ───
const confirmBulkPublish = async () => {
  formSaving.value = true
  try {
    const response = await api.patch<{ updated: number; publication: boolean }>('/admin/gamedays/bulk/publication', {
      ids: selectedIds.value,
    })
    toast.add({
      title: t('common.success'),
      description: t('gamedays.bulk_published', { count: response.updated }),
      color: 'success',
    })
    bulkPublishConfirmOpen.value = false
    selectedIds.value = []
    await loadGamedays()
  } catch {
    // Error already shown
  } finally {
    formSaving.value = false
  }
}

const openBulkCalendarModal = () => {
  bulkCalendarData.value = { nom: '', dateDebut: '', dateFin: '', lieu: '', departement: '' }
  bulkCalendarModalOpen.value = true
}

const submitBulkCalendar = async () => {
  formSaving.value = true
  try {
    const response = await api.patch<{ updated: number }>('/admin/gamedays/bulk/calendar', {
      ids: selectedIds.value,
      ...bulkCalendarData.value,
    })
    toast.add({
      title: t('common.success'),
      description: t('gamedays.bulk_calendar_updated', { count: response.updated }),
      color: 'success',
    })
    bulkCalendarModalOpen.value = false
    selectedIds.value = []
    await loadGamedays()
  } catch {
    // Error already shown
  } finally {
    formSaving.value = false
  }
}

const confirmBulkDelete = async () => {
  formSaving.value = true
  try {
    const response = await api.del<{ deleted: number; skipped: { id: number; reason: string }[] }>('/admin/gamedays/bulk', {
      ids: selectedIds.value,
    })
    if (response.skipped && response.skipped.length > 0) {
      toast.add({
        title: t('common.success'),
        description: t('gamedays.bulk_deleted_partial', { deleted: response.deleted, skipped: response.skipped.length }),
        color: 'warning',
      })
    } else {
      toast.add({
        title: t('common.success'),
        description: t('gamedays.bulk_deleted', { deleted: response.deleted }),
        color: 'success',
      })
    }
    bulkDeleteConfirmOpen.value = false
    selectedIds.value = []
    await loadGamedays()
  } catch {
    // Error already shown
  } finally {
    formSaving.value = false
  }
}

// ─── Months list ───
const months = computed(() => {
  return Array.from({ length: 12 }, (_, i) => ({
    value: String(i + 1),
    label: t(`gamedays.months.${i + 1}`),
  }))
})

// ─── Sort options ───
const sortOptions = computed(() => [
  { value: 'date_asc', label: t('gamedays.sort.date_asc') },
  { value: 'date_desc', label: t('gamedays.sort.date_desc') },
  { value: 'name', label: t('gamedays.sort.name') },
  { value: 'number', label: t('gamedays.sort.number') },
  { value: 'level', label: t('gamedays.sort.level') },
])

// ─── Officials summary ───
const getOfficialsSummary = (g: Gameday): string => {
  const parts: string[] = []
  if (g.responsableInsc) parts.push(`RC: ${g.responsableInsc}`)
  if (g.responsableR1) parts.push(`R1: ${g.responsableR1}`)
  if (g.delegue) parts.push(`Del: ${g.delegue}`)
  if (g.chefArbitre) parts.push(`Ref: ${g.chefArbitre}`)
  return parts.length > 0 ? parts.join(', ') : '-'
}

// ─── Officials Modal (Feature 2) : bloc Désignations de la journée ───
const openOfficialsModal = (g: Gameday) => {
  officialsGameday.value = g
  officialsModalOpen.value = true
}

// Officials of the gameday: api2 `instances` (PDF_GOTENBERG.md § 7.2)
const { openDocument } = usePdf()
const printJurySheet = (gameday: Gameday | null) => {
  if (!gameday) return
  void openDocument('instances', {
    season: gameday.codeSaison,
    competition: gameday.codeCompetition,
    gameday: gameday.id,
  })
}

</script>

<template>
  <div>
    <!-- Page header -->
    <AdminPageHeader
      :title="t('gamedays.title')"
      :show-all-option="true"
      :competition-filtered-codes="workContext.pageFilteredCompetitionCodes"
      @event-group-change="() => { page = 1 }"
      @competition-change="() => { page = 1 }"
    >
      <template v-if="view === 'list'" #filters>
        <!-- Month filter -->
        <div class="min-w-36">
          <label class="block text-sm font-medium text-header-600 dark:text-header-300 mb-1">{{ t('gamedays.field.date_debut') }}</label>
          <select
            v-model="selectedMonth"
            class="w-full px-3 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500"
          >
            <option value="">{{ t('gamedays.all_months') }}</option>
            <option v-for="m in months" :key="m.value" :value="m.value">{{ m.label }}</option>
          </select>
        </div>

        <!-- Sort -->
        <div class="min-w-48">
          <label class="block text-sm font-medium text-header-600 dark:text-header-300 mb-1">{{ t('gamedays.sort.date_asc') }}</label>
          <select
            v-model="selectedSort"
            class="w-full px-3 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500"
          >
            <option v-for="opt in sortOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
          </select>
        </div>
      </template>
    </AdminPageHeader>

    <!-- Toolbar (search + add) -->
    <AdminToolbar
      v-if="view === 'list'"
      v-model:search="searchQuery"
      :search-placeholder="t('gamedays.search_placeholder')"
      :add-label="t('gamedays.add')"
      :show-add="canEdit && hasContextFilter"
      :selected-count="selectedIds.length"
      @add="openAddModal"
    >
      <template #left>
        <!-- Bulk actions dropdown -->
        <div v-if="canSelect && selectedIds.length > 0" ref="bulkActionsRef" class="relative">
          <button
            class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-950 border border-primary-200 dark:border-primary-700 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-800"
            @click="bulkActionsOpen = !bulkActionsOpen"
          >
            <UIcon name="heroicons:bolt" class="w-6 h-6" />
            {{ t('gamedays.bulk.actions') }}
            <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-sm font-medium bg-primary-100 dark:bg-primary-900 text-primary-800 dark:text-primary-200">
              {{ selectedIds.length }}
            </span>
            <UIcon name="heroicons:chevron-down" class="w-6 h-6 transition-transform" :class="{ 'rotate-180': bulkActionsOpen }" />
          </button>
          <div v-show="bulkActionsOpen" class="absolute z-20 mt-1 w-72 bg-white dark:bg-header-900 border border-header-200 dark:border-header-700 rounded-lg shadow-lg py-1 left-0">
            <!-- ── Toggle section ── -->
            <div class="px-3 py-1 text-xs font-semibold text-header-600 dark:text-header-300 uppercase tracking-wider">{{ t('gamedays.bulk.toggle_section') }}</div>
            <button
              class="w-full flex items-center gap-2 px-4 py-2 text-sm text-header-900 dark:text-header-50 hover:bg-header-50 dark:hover:bg-header-800"
              @click="bulkPublishConfirmOpen = true; bulkActionsOpen = false"
            >
              <UIcon name="heroicons:eye" class="w-5 h-5 text-success-500" />
              {{ t('gamedays.field.publication') }}
            </button>

            <!-- ── Edit section ── -->
            <div class="border-t border-header-100 dark:border-header-800 my-1" />
            <div class="px-3 py-1 text-xs font-semibold text-header-600 dark:text-header-300 uppercase tracking-wider">{{ t('gamedays.bulk.edit_section') }}</div>
            <button
              class="w-full flex items-center gap-2 px-4 py-2 text-sm text-header-900 dark:text-header-50 hover:bg-header-50 dark:hover:bg-header-800"
              @click="openBulkCalendarModal(); bulkActionsOpen = false"
            >
              <UIcon name="heroicons:calendar-days" class="w-5 h-5 text-primary-600 dark:text-primary-300" />
              {{ t('gamedays.calendar_public') }}
            </button>

            <!-- ── Danger section ── -->
            <div class="border-t border-header-100 dark:border-header-800 my-1" />
            <button
              class="w-full flex items-center gap-2 px-4 py-2 text-sm text-danger-600 dark:text-danger-300 hover:bg-danger-50 dark:hover:bg-danger-950"
              @click="bulkDeleteConfirmOpen = true; bulkActionsOpen = false"
            >
              <UIcon name="heroicons:trash" class="w-5 h-5" />
              {{ t('common.delete_selected') }}
            </button>
          </div>
        </div>
      </template>
      <template #before-search>
        <!-- Refresh button -->
        <button
          class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg hover:bg-header-50 dark:hover:bg-header-800"
          :title="t('common.refresh')"
          @click="loadGamedays"
        >
          <UIcon name="heroicons:arrow-path" class="w-5 h-5 text-header-600 dark:text-header-300" />
        </button>

        <!-- Liste / Schéma views -->
        <GamedaysViewSwitch v-model="view" :schema-available="schemaAvailable" />
      </template>
    </AdminToolbar>

    <!-- Desktop Table -->
    <div v-if="view === 'list'" class="hidden lg:block bg-white dark:bg-header-900 rounded-lg shadow">
      <div class="sticky-thead">
        <table class="min-w-full divide-y divide-header-200 dark:divide-header-700">
          <thead class="bg-header-50 dark:bg-header-900">
            <tr>
              <!-- Checkbox -->
              <th v-if="canSelect" class="w-10 px-2 py-3">
                <input
                  type="checkbox"
                  class="rounded border-header-300 dark:border-header-700"
                  :checked="selectedIds.length === gamedays.length && gamedays.length > 0"
                  :indeterminate="selectedIds.length > 0 && selectedIds.length < gamedays.length"
                  @change="toggleSelectAll"
                >
              </th>
              <!-- Publication -->
              <th class="w-10 px-2 py-3 text-center text-sm font-medium text-header-900 dark:text-header-50 uppercase">
                <UIcon name="heroicons:eye" class="w-6 h-6" />
              </th>
              <!-- Id -->
              <th class="px-2 py-3 text-left text-sm font-medium text-header-900 dark:text-header-50 uppercase">{{ t('gamedays.field.id') }}</th>
              <!-- Actions -->
              <th v-if="canEdit" class="w-20 px-2 py-3" />
              <!-- Competition / Phase -->
              <th class="px-2 py-3 text-left text-sm font-medium text-header-900 dark:text-header-50 uppercase">{{ t('gamedays.field.competition') }} / {{ t('gamedays.field.phase') }}</th>
              <!-- CP columns -->
              <th v-if="showCPColumns" class="px-2 py-3 text-center text-sm font-medium text-header-900 dark:text-header-50 uppercase">{{ t('gamedays.field.niveau') }}</th>
              <th v-if="showCPColumns" class="px-2 py-3 text-center text-sm font-medium text-header-900 dark:text-header-50 uppercase">{{ t('gamedays.field.etape') }}</th>
              <th v-if="showCPColumns" class="px-2 py-3 text-center text-sm font-medium text-header-900 dark:text-header-50 uppercase">{{ t('gamedays.field.nb_equipes') }}</th>
              <!-- Type -->
              <th class="w-10 px-2 py-3 text-center text-sm font-medium text-header-900 dark:text-header-50 uppercase">{{ t('gamedays.field.type') }}</th>
              <!-- Calendar public columns (green headers) -->
              <th class="px-2 py-3 text-left text-sm font-medium text-success-700 dark:text-success-300 uppercase bg-success-100 dark:bg-success-900">{{ t('gamedays.field.nom') }}</th>
              <th class="px-2 py-3 text-left text-sm font-medium text-success-700 dark:text-success-300 uppercase bg-success-100 dark:bg-success-900">{{ t('gamedays.field.date_debut') }}</th>
              <th class="px-2 py-3 text-left text-sm font-medium text-success-700 dark:text-success-300 uppercase bg-success-100 dark:bg-success-900">{{ t('gamedays.field.date_fin') }}</th>
              <th class="px-2 py-3 text-left text-sm font-medium text-success-700 dark:text-success-300 uppercase bg-success-100 dark:bg-success-900">{{ t('gamedays.field.lieu') }}</th>
              <th class="px-2 py-3 text-left text-sm font-medium text-success-700 dark:text-success-300 uppercase bg-success-100 dark:bg-success-900">{{ t('gamedays.field.departement') }}</th>
              <!-- Matches -->
              <th class="px-2 py-3 text-center text-sm font-medium text-header-900 dark:text-header-50 uppercase">{{ t('gamedays.field.matches') }}</th>
              <!-- Officials -->
              <th class="px-2 py-3 text-left text-sm font-medium text-header-900 dark:text-header-50 uppercase">{{ t('gamedays.field.officiels') }}</th>
              <!-- Delete -->
              <th v-if="canEdit" class="w-10 px-2 py-3" />
            </tr>
          </thead>
          <tbody class="bg-white dark:bg-header-900 divide-y divide-header-200 dark:divide-header-700">
            <!-- Loading -->
            <tr v-if="loading && gamedays.length === 0">
              <td :colspan="canSelect ? 17 : 16" class="px-4 py-8 text-center text-header-900 dark:text-header-50">
                <UIcon name="heroicons:arrow-path" class="w-6 h-6 animate-spin mx-auto mb-2" />
                {{ t('common.loading') }}
              </td>
            </tr>
            <!-- Empty: no context selected -->
            <tr v-else-if="!hasContextFilter">
              <td :colspan="canSelect ? 17 : 16" class="px-4 py-12 text-center text-header-600 dark:text-header-300">
                <UIcon name="heroicons:funnel" class="w-8 h-8 mx-auto mb-2 opacity-40" />
                <div class="text-sm">{{ t('gamedays.select_context') }}</div>
              </td>
            </tr>
            <!-- Empty: no results -->
            <tr v-else-if="gamedays.length === 0">
              <td :colspan="canSelect ? 17 : 16" class="px-4 py-8 text-center text-header-900 dark:text-header-50">
                {{ t('gamedays.no_results') }}
              </td>
            </tr>
            <!-- Rows -->
            <tr
              v-for="g in gamedays"
              :key="g.id"
              class="hover:bg-header-50 dark:hover:bg-header-800"
              :class="{ 'bg-primary-50 dark:bg-primary-950': selectedIds.includes(g.id) }"
            >
              <!-- Checkbox -->
              <td v-if="canSelect" class="px-2 py-2" @click.stop>
                <input
                  type="checkbox"
                  class="rounded border-header-300 dark:border-header-700"
                  :checked="selectedIds.includes(g.id)"
                  @change="toggleSelect(g.id)"
                >
              </td>
              <!-- Publication toggle -->
              <td class="px-2 py-2 text-center">
                <AdminToggleButton
                  :active="g.publication"
                  active-icon="heroicons:eye-solid"
                  inactive-icon="heroicons:eye-slash"
                  active-color="success"
                  :active-title="t('gamedays.published')"
                  :inactive-title="t('gamedays.unpublished')"
                  :disabled="!canEditGameday(g)"
                  @toggle="togglePublication(g)"
                />
              </td>
              <!-- Id -->
              <td class="px-2 py-2 text-sm text-header-900 dark:text-header-50 font-mono">{{ g.id }}</td>
              <!-- Actions -->
              <td v-if="canEdit" class="px-2 py-2" @click.stop>
                <div class="flex items-center gap-0.5">
                  <button v-if="canEditGameday(g)" :title="t('common.edit')" class="p-1 text-primary-600 dark:text-primary-300 hover:text-primary-800 dark:hover:text-primary-200" @click="openEditModal(g)">
                    <UIcon name="heroicons:pencil" class="w-6 h-6" />
                  </button>
                  <button v-if="canEditGameday(g)" :title="t('gamedays.duplicate')" class="p-1 text-header-600 dark:text-header-300 hover:text-header-900 dark:hover:text-header-50" @click="openDuplicateConfirm(g)">
                    <UIcon name="heroicons:document-duplicate" class="w-6 h-6" />
                  </button>
                  <button :title="t('schema.title')" class="p-1 text-header-600 dark:text-header-300 hover:text-header-900 dark:hover:text-header-50" @click="goToSchema(g.codeCompetition)">
                    <UIcon name="heroicons:rectangle-group" class="w-6 h-6" />
                  </button>
                </div>
              </td>
              <!-- Competition / Phase -->
              <td class="px-2 py-2 text-sm">
                <span class="font-medium text-header-900 dark:text-header-50 me-2">{{ g.codeCompetition }}</span>
                <!-- Inline editable Phase -->
                <template v-if="editingCell?.id === g.id && editingCell.field === 'Phase'">
                  <input
                    :id="`inline-${g.id}-Phase`"
                    v-model="editingValue"
                    type="text"
                    maxlength="30"
                    class="w-full px-1 py-0.5 text-sm border border-primary-400 rounded focus:ring-1 focus:ring-primary-500"
                    @keydown="handleInlineKeydown"
                    @blur="saveInlineEdit"
                  >
                </template>
                <template v-else>
                  <span
                    class="text-header-900 dark:text-header-50"
                    :class="canEditGameday(g) ? 'editable-cell' : ''"
                    @click="startInlineEdit(g, 'Phase')"
                  >{{ g.phase || '-' }}</span>
                </template>
              </td>
              <!-- CP columns (inline editable) -->
              <td v-if="showCPColumns" class="px-2 py-2 text-sm text-center">
                <template v-if="editingCell?.id === g.id && editingCell.field === 'Niveau'">
                  <input
                    :id="`inline-${g.id}-Niveau`"
                    v-model="editingValue"
                    type="number"
                    min="1"
                    class="w-12 px-1 py-0.5 text-sm text-center border border-primary-400 rounded"
                    @keydown="handleInlineKeydown"
                    @blur="saveInlineEdit"
                  >
                </template>
                <template v-else>
                  <span
                    :class="canEditGameday(g) ? 'editable-cell' : ''"
                    @click="startInlineEdit(g, 'Niveau')"
                  >{{ g.niveau ?? '-' }}</span>
                </template>
              </td>
              <td v-if="showCPColumns" class="px-2 py-2 text-sm text-center">
                <template v-if="editingCell?.id === g.id && editingCell.field === 'Etape'">
                  <input
                    :id="`inline-${g.id}-Etape`"
                    v-model="editingValue"
                    type="number"
                    min="1"
                    class="w-12 px-1 py-0.5 text-sm text-center border border-primary-400 rounded"
                    @keydown="handleInlineKeydown"
                    @blur="saveInlineEdit"
                  >
                </template>
                <template v-else>
                  <span
                    :class="canEditGameday(g) ? 'editable-cell' : ''"
                    @click="startInlineEdit(g, 'Etape')"
                  >{{ g.etape }}</span>
                </template>
              </td>
              <td v-if="showCPColumns" class="px-2 py-2 text-sm text-center">
                <template v-if="editingCell?.id === g.id && editingCell.field === 'Nbequipes'">
                  <input
                    :id="`inline-${g.id}-Nbequipes`"
                    v-model="editingValue"
                    type="number"
                    min="1"
                    class="w-12 px-1 py-0.5 text-sm text-center border border-primary-400 rounded"
                    @keydown="handleInlineKeydown"
                    @blur="saveInlineEdit"
                  >
                </template>
                <template v-else>
                  <span
                    :class="canEditGameday(g) ? 'editable-cell' : ''"
                    @click="startInlineEdit(g, 'Nbequipes')"
                  >{{ g.nbEquipes }}</span>
                </template>
              </td>
              <!-- Type toggle -->
              <td class="px-2 py-2 text-center">
                <button
                  :title="g.type === 'C' ? t('gamedays.field.type_c') : t('gamedays.field.type_e')"
                  class="p-1 rounded"
                  :class="canEditGameday(g) ? 'hover:bg-header-200 dark:hover:bg-header-700 cursor-pointer' : 'opacity-40 cursor-not-allowed'"
                  :disabled="!canEditGameday(g)"
                  @click="canEditGameday(g) && toggleType(g)"
                >
                  <UIcon
                    :name="g.type === 'C' ? 'heroicons:bars-3' : 'heroicons:arrows-right-left'"
                    class="w-6 h-6"
                    :class="g.type === 'C' ? 'text-primary-600 dark:text-primary-300' : 'text-orange-700 dark:text-orange-300'"
                  />
                </button>
              </td>
              <!-- Nom (calendar public - green bg, inline editable) -->
              <td class="px-2 py-2 text-sm bg-success-50 dark:bg-success-900">
                <template v-if="editingCell?.id === g.id && editingCell.field === 'Nom'">
                  <input
                    :id="`inline-${g.id}-Nom`"
                    v-model="editingValue"
                    type="text"
                    maxlength="80"
                    class="w-full px-1 py-0.5 text-sm border border-primary-400 rounded"
                    @keydown="handleInlineKeydown"
                    @blur="saveInlineEdit"
                  >
                </template>
                <template v-else>
                  <span
                    class="font-medium text-header-900 dark:text-header-50"
                    :class="canEditGameday(g) ? 'editable-cell' : ''"
                    @click="startInlineEdit(g, 'Nom')"
                  >{{ g.nom || '-' }}</span>
                </template>
              </td>
              <!-- Date début (calendar public, inline editable) -->
              <td class="px-2 py-2 text-sm bg-success-50 dark:bg-success-900 whitespace-nowrap">
                <template v-if="editingCell?.id === g.id && editingCell.field === 'Date_debut'">
                  <input
                    :id="`inline-${g.id}-Date_debut`"
                    v-model="editingValue"
                    type="date"
                    class="px-1 py-0.5 text-sm border border-primary-400 rounded focus:ring-1 focus:ring-primary-500"
                    @keydown="handleInlineKeydown"
                    @blur="saveInlineEdit"
                  >
                </template>
                <template v-else>
                  <span
                    class="text-header-900 dark:text-header-50"
                    :class="canEditGameday(g) ? 'editable-cell' : ''"
                    @click="startInlineEdit(g, 'Date_debut')"
                  >{{ formatDate(g.dateDebut) }}</span>
                </template>
              </td>
              <!-- Date fin (calendar public, inline editable) -->
              <td class="px-2 py-2 text-sm bg-success-50 dark:bg-success-900 whitespace-nowrap">
                <template v-if="editingCell?.id === g.id && editingCell.field === 'Date_fin'">
                  <input
                    :id="`inline-${g.id}-Date_fin`"
                    v-model="editingValue"
                    type="date"
                    class="px-1 py-0.5 text-sm border border-primary-400 rounded focus:ring-1 focus:ring-primary-500"
                    @keydown="handleInlineKeydown"
                    @blur="saveInlineEdit"
                  >
                </template>
                <template v-else>
                  <span
                    class="text-header-900 dark:text-header-50"
                    :class="canEditGameday(g) ? 'editable-cell' : ''"
                    @click="startInlineEdit(g, 'Date_fin')"
                  >{{ formatDate(g.dateFin) }}</span>
                </template>
              </td>
              <!-- Lieu (calendar public, inline editable) -->
              <td class="px-2 py-2 text-sm bg-success-50 dark:bg-success-900">
                <template v-if="editingCell?.id === g.id && editingCell.field === 'Lieu'">
                  <input
                    :id="`inline-${g.id}-Lieu`"
                    v-model="editingValue"
                    type="text"
                    maxlength="40"
                    class="w-full px-1 py-0.5 text-sm border border-primary-400 rounded"
                    @keydown="handleInlineKeydown"
                    @blur="saveInlineEdit"
                  >
                </template>
                <template v-else>
                  <span
                    :class="canEditGameday(g) ? 'editable-cell' : ''"
                    @click="startInlineEdit(g, 'Lieu')"
                  >{{ g.lieu || '-' }}</span>
                </template>
              </td>
              <!-- Departement (calendar public, inline editable) -->
              <td class="px-2 py-2 text-sm bg-success-50 dark:bg-success-900">
                <template v-if="editingCell?.id === g.id && editingCell.field === 'Departement'">
                  <input
                    :id="`inline-${g.id}-Departement`"
                    v-model="editingValue"
                    type="text"
                    maxlength="3"
                    class="w-12 px-1 py-0.5 text-sm border border-primary-400 rounded uppercase"
                    @keydown="handleInlineKeydown"
                    @blur="saveInlineEdit"
                  >
                </template>
                <template v-else>
                  <span
                    :class="canEditGameday(g) ? 'editable-cell' : ''"
                    @click="startInlineEdit(g, 'Departement')"
                  >{{ g.departement || '-' }}</span>
                </template>
              </td>
              <!-- Match count -->
              <td class="px-2 py-2 text-sm text-center">
                <NuxtLink
                  v-if="g.matchCount > 0"
                  :to="`/games?phase=${g.id}`"
                  class="link-value"
                >
                  {{ g.matchCount }}
                </NuxtLink>
                <span v-else class="text-header-600 dark:text-header-300">0</span>
              </td>
              <!-- Officials -->
              <td class="px-2 py-2 text-sm text-header-900 dark:text-header-50 max-w-48" @click.stop>
                <button
                  class="text-left hover:text-primary-600 dark:hover:text-primary-300 truncate max-w-full"
                  :title="getOfficialsSummary(g)"
                  @click="openOfficialsModal(g)"
                >
                  {{ getOfficialsSummary(g) }}
                </button>
              </td>
              <!-- Delete -->
              <td v-if="canEdit && g.matchCount === 0" class="px-2 py-2" @click.stop>
                <button
                  v-if="canEditGameday(g)"
                  class="p-1 text-danger-600 dark:text-danger-300 hover:text-danger-700 dark:hover:text-danger-300"
                  :title="t('common.delete')"
                  @click="openDeleteConfirm(g)"
                >
                  <UIcon name="heroicons:trash" class="w-6 h-6" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <AdminPagination
        v-model:page="page"
        v-model:limit="limit"
        :total="total"
        :total-pages="totalPages"
        :showing-text="t('gamedays.total', { count: total })"
        :limit-options="[10, 25, 50, 100]"
        show-all
      />
    </div>

    <!-- Mobile Cards -->
    <AdminCardList v-if="view === 'list'" class="lg:hidden" :loading="loading && gamedays.length === 0" :empty="gamedays.length === 0" :empty-text="hasContextFilter ? t('gamedays.no_results') : t('gamedays.select_context')">
      <AdminCard
        v-for="g in gamedays"
        :key="g.id"
        :selected="selectedIds.includes(g.id)"
        :show-checkbox="canSelect"
        :checked="selectedIds.includes(g.id)"
        @toggle-select="toggleSelect(g.id)"
      >
        <template #header>
          <div>
            <div class="font-bold text-header-900 dark:text-header-50">{{ g.codeCompetition }} - {{ g.phase || '?' }}</div>
            <div class="text-sm text-header-900 dark:text-header-50">#{{ g.id }}</div>
          </div>
        </template>
        <template #header-right>
          <AdminToggleButton
            :active="g.publication"
            active-icon="heroicons:eye-solid"
            inactive-icon="heroicons:eye-slash"
            active-color="success"
            @toggle="canEditGameday(g) && togglePublication(g)"
          />
        </template>

        <div class="space-y-1 text-sm">
          <div v-if="g.nom">
            <span class="text-success-700 dark:text-success-300 font-medium">{{ g.nom }}</span>
          </div>
          <div v-if="g.dateDebut" class="flex items-center gap-1">
            <UIcon name="heroicons:calendar" class="w-6 h-6 text-header-900 dark:text-header-50" />
            <span>{{ formatDateRange(g.dateDebut, g.dateFin) }}</span>
          </div>
          <div v-if="g.lieu" class="flex items-center gap-1">
            <UIcon name="heroicons:map-pin" class="w-6 h-6 text-header-900 dark:text-header-50" />
            <span>{{ g.lieu }} <span v-if="g.departement">({{ g.departement }})</span></span>
          </div>
          <div class="flex items-center gap-3 text-sm text-header-900 dark:text-header-50">
            <span>{{ g.type === 'C' ? t('gamedays.field.type_c') : t('gamedays.field.type_e') }}</span>
            <span v-if="g.matchCount > 0">{{ g.matchCount }} {{ t('gamedays.field.matches').toLowerCase() }}</span>
          </div>
          <button v-if="g.responsableInsc" class="text-sm text-header-900 dark:text-header-50 hover:text-primary-600 dark:hover:text-primary-300" @click.stop="openOfficialsModal(g)">
            RC: {{ g.responsableInsc }}
          </button>
        </div>

        <template #footer-right>
          <AdminActionButton v-if="canEditGameday(g)" icon="heroicons:pencil" @click="openEditModal(g)">
            {{ t('common.edit') }}
          </AdminActionButton>
          <AdminActionButton v-if="canEditGameday(g)" icon="heroicons:document-duplicate" @click="openDuplicateConfirm(g)">
            {{ t('gamedays.duplicated') }}
          </AdminActionButton>
          <AdminActionButton icon="heroicons:rectangle-group" @click="goToSchema(g.codeCompetition)">
            {{ t('schema.title') }}
          </AdminActionButton>
          <AdminActionButton v-if="canEditGameday(g) && g.matchCount === 0" variant="danger" icon="heroicons:trash" @click="openDeleteConfirm(g)">
            {{ t('common.delete') }}
          </AdminActionButton>
        </template>
      </AdminCard>

      <!-- Mobile Pagination -->
      <AdminPagination
        v-if="gamedays.length > 0"
        v-model:page="page"
        v-model:limit="limit"
        :total="total"
        :total-pages="totalPages"
        :showing-text="t('gamedays.total', { count: total })"
        class="mt-4 rounded-lg shadow"
      />
    </AdminCardList>

    <!-- Schema view (§ 5.3): the header competition selector picks the competition -->
    <SchemaView v-if="view === 'schema'" :competition="workContext.pageCompetitionCodeAll" :season="workContext.season || ''">
      <!-- Same place as in the list view: on the right, next to the level / type badges -->
      <template #toolbar-actions>
        <GamedaysViewSwitch v-model="view" :schema-available="schemaAvailable" />
      </template>
    </SchemaView>

    <!-- ═══════ ADD/EDIT MODAL ═══════ -->
    <AdminModal
      :open="formModalOpen"
      :title="editingGameday ? t('gamedays.edit') : t('gamedays.add')"
      max-width="xl"
      @close="formModalOpen = false"
    >
      <form class="space-y-4" @submit.prevent="submitForm">
        <!-- Error -->
        <div v-if="formError" class="p-3 bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 rounded-lg text-danger-800 dark:text-danger-200 text-sm">
          <UIcon name="heroicons:exclamation-triangle" class="w-6 h-6 inline mr-1" />
          {{ formError }}
        </div>

        <!-- Season + Competition -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.season') }} *</label>
            <input
              v-model="formData.codeSaison"
              type="text"
              :readonly="authStore.profile > 2"
              class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50"
              :class="authStore.profile > 2 ? 'bg-header-200 dark:bg-header-700' : ''"
            >
          </div>
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.competition') }} *</label>
            <AdminCompetitionGroupedSelect
              v-model="formData.codeCompetition"
              :disabled="authStore.profile > 2 && !!editingGameday"
            />
          </div>
        </div>

        <!-- Phase + Type -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.phase') }} *</label>
            <input
              v-model="formData.phase"
              type="text"
              maxlength="30"
              required
              class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500"
              list="phase-suggestions"
            >
            <datalist id="phase-suggestions">
              <option v-for="letter in 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split('')" :key="letter" :value="locale === 'fr' ? `Poule ${letter}` : `Group ${letter}`" />
              <option :value="locale === 'fr' ? '1/8 Finale' : 'Round of 16'" />
              <option :value="locale === 'fr' ? '1/4 Finale' : 'Quarter-final'" />
              <option :value="locale === 'fr' ? '1/2 Finale' : 'Semi-final'" />
              <option :value="locale === 'fr' ? 'Finale' : 'Final'" />
              <option :value="locale === 'fr' ? 'Barrage' : 'Playoff'" />
            </datalist>
          </div>
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.type') }}</label>
            <div class="flex items-center gap-4 mt-2">
              <label class="flex items-center gap-2">
                <input v-model="formData.type" type="radio" value="C" class="text-primary-600 dark:text-primary-300">
                <span class="text-sm">{{ t('gamedays.field.type_c') }}</span>
              </label>
              <label class="flex items-center gap-2">
                <input v-model="formData.type" type="radio" value="E" class="text-orange-700 dark:text-orange-300">
                <span class="text-sm">{{ t('gamedays.field.type_e') }}</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Niveau / Etape / NbEquipes -->
        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.niveau') }}</label>
            <select v-model.number="formData.niveau" class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50">
              <option v-for="n in 29" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.etape') }}</label>
            <select v-model.number="formData.etape" class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50">
              <option v-for="n in 19" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.nb_equipes') }}</label>
            <select v-model.number="formData.nbEquipes" class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50">
              <option v-for="n in 19" :key="n" :value="n">{{ n }}</option>
            </select>
          </div>
        </div>

        <!-- Dates -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.date_debut') }}</label>
            <input v-model="formData.dateDebut" type="date" class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50">
          </div>
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.date_fin') }}</label>
            <input v-model="formData.dateFin" type="date" class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50">
          </div>
        </div>

        <!-- Calendar public fields (highlighted) -->
        <div class="p-4 bg-success-50 dark:bg-success-900 rounded-lg border border-success-200 space-y-4">
          <h3 class="text-sm font-semibold text-success-800 dark:text-success-200">{{ t('gamedays.calendar_public') }}</h3>
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.nom') }}</label>
            <input v-model="formData.nom" type="text" maxlength="80" class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50">
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.lieu') }}</label>
              <AdminTextAutocomplete
                :model-value="formData.lieu"
                api-url="/admin/gamedays/autocomplete/communes"
                label-field="label"
                detail-field="detail"
                :placeholder="t('gamedays.field.lieu')"
                :maxlength="40"
                @update:model-value="formData.lieu = $event"
                @select="(item: any) => { if (item.departement) formData.departement = item.departement }"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.departement') }}</label>
              <input v-model="formData.departement" type="text" maxlength="3" class="w-24 px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 uppercase">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.plan_eau') }}</label>
            <input v-model="formData.planEau" type="text" maxlength="80" class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50">
          </div>
        </div>

        <!-- Organisateur -->
        <div>
          <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.organisateur') }}</label>
          <AdminTextAutocomplete
            :model-value="formData.organisateur"
            api-url="/admin/clubs/search-all"
            label-field="libelle"
            detail-field="code"
            :placeholder="t('gamedays.field.organisateur')"
            :maxlength="40"
            @update:model-value="onOrganisateurInput"
            @select="(club: { code: string }) => { formData.codeOrganisateur = club.code }"
          />
        </div>

        <!-- Officials: désignés dans le bloc Désignations (bouton Officiels de la ligne), plus saisis ici -->
        <p class="text-sm text-header-700 dark:text-header-200 border border-header-200 dark:border-header-700 rounded-lg px-4 py-3">
          {{ t('gamedays.officials_by_designations') }}
        </p>

        <!-- Actions -->
        <div class="flex justify-end gap-2 pt-4 border-t">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg hover:bg-header-50 dark:hover:bg-header-800"
            @click="formModalOpen = false"
          >
            {{ t('common.cancel') }}
          </button>
          <button
            type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            :disabled="formSaving"
          >
            {{ formSaving ? t('common.loading') : t('common.save') }}
          </button>
        </div>
      </form>
    </AdminModal>

    <!-- ═══════ DUPLICATE CONFIRM ═══════ -->
    <AdminModal
      :open="duplicateConfirmOpen"
      :title="t('gamedays.duplicate_confirm', { id: duplicateGameday?.id })"
      max-width="sm"
      @close="duplicateConfirmOpen = false"
    >
      <div class="space-y-4">
        <label class="flex items-center gap-2">
          <input v-model="duplicateIncludeMatches" type="checkbox" class="rounded border-header-300 dark:border-header-700 text-primary-600 dark:text-primary-300">
          <span class="text-sm">{{ t('gamedays.include_matches') }}</span>
        </label>
        <div class="flex justify-end gap-2 pt-4 border-t">
          <button
            class="px-4 py-2 text-sm font-medium text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg hover:bg-header-50 dark:hover:bg-header-800"
            @click="duplicateConfirmOpen = false"
          >
            {{ t('common.cancel') }}
          </button>
          <button
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            :disabled="formSaving"
            @click="confirmDuplicate"
          >
            {{ t('common.confirm') }}
          </button>
        </div>
      </div>
    </AdminModal>

    <!-- ═══════ DELETE CONFIRM ═══════ -->
    <AdminConfirmModal
      :open="deleteConfirmOpen"
      :title="t('gamedays.delete_confirm_title')"
      :message="t('gamedays.delete_confirm_message', { id: gamedayToDelete?.id, phase: gamedayToDelete?.phase || '?' })"
      :loading="formSaving"
      danger
      @close="deleteConfirmOpen = false"
      @confirm="confirmDelete"
    />

    <!-- ═══════ BULK PUBLISH CONFIRM ═══════ -->
    <AdminConfirmModal
      :open="bulkPublishConfirmOpen"
      :title="t('gamedays.field.publication')"
      :message="t('gamedays.publish_confirm')"
      :loading="formSaving"
      @close="bulkPublishConfirmOpen = false"
      @confirm="confirmBulkPublish"
    />

    <!-- ═══════ BULK DELETE CONFIRM ═══════ -->
    <AdminConfirmModal
      :open="bulkDeleteConfirmOpen"
      :title="t('common.delete_selected')"
      :message="t('gamedays.bulk_delete_confirm', { count: selectedIds.length })"
      :loading="formSaving"
      danger
      @close="bulkDeleteConfirmOpen = false"
      @confirm="confirmBulkDelete"
    />

    <!-- ═══════ BULK CALENDAR MODAL ═══════ -->
    <AdminModal
      :open="bulkCalendarModalOpen"
      :title="t('gamedays.bulk_calendar_title')"
      max-width="md"
      @close="bulkCalendarModalOpen = false"
    >
      <form class="space-y-4" @submit.prevent="submitBulkCalendar">
        <p class="text-sm text-header-900 dark:text-header-50">
          {{ t('gamedays.bulk_calendar_hint', { count: selectedIds.length }) }}
        </p>
        <div>
          <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.nom') }}</label>
          <input v-model="bulkCalendarData.nom" type="text" maxlength="80" class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50">
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.date_debut') }}</label>
            <input v-model="bulkCalendarData.dateDebut" type="date" class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50">
          </div>
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.date_fin') }}</label>
            <input v-model="bulkCalendarData.dateFin" type="date" class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50">
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.lieu') }}</label>
            <input v-model="bulkCalendarData.lieu" type="text" maxlength="40" class="w-full px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50">
          </div>
          <div>
            <label class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">{{ t('gamedays.field.departement') }}</label>
            <input v-model="bulkCalendarData.departement" type="text" maxlength="3" class="w-24 px-3 py-2 border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 uppercase">
          </div>
        </div>
        <div class="flex justify-end gap-2 pt-4 border-t">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg hover:bg-header-50 dark:hover:bg-header-800"
            @click="bulkCalendarModalOpen = false"
          >
            {{ t('common.cancel') }}
          </button>
          <button
            type="submit"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50"
            :disabled="formSaving"
          >
            {{ t('common.save') }}
          </button>
        </div>
      </form>
    </AdminModal>


    <!-- ═══════ OFFICIALS MODAL (Feature 2) ═══════ -->
    <AdminModal
      :open="officialsModalOpen"
      :title="t('gamedays.officials_title')"
      max-width="xl"
      @close="officialsModalOpen = false"
    >
      <div v-if="officialsGameday" class="space-y-4">
        <!-- Header info -->
        <div class="text-sm text-header-900 dark:text-header-50">
          <span class="font-medium text-header-900 dark:text-header-50">{{ officialsGameday.codeCompetition }} - {{ officialsGameday.phase }}</span>
          <span v-if="officialsGameday.nom" class="ml-2">| {{ officialsGameday.nom }}</span>
          <span v-if="officialsGameday.lieu" class="ml-2">| {{ officialsGameday.lieu }}</span>
        </div>

        <DesignationsScopePanel
          v-if="authStore.profile <= 5"
          :season="officialsGameday.codeSaison"
          scope-type="JOURNEE"
          :scope-id="officialsGameday.id"
          :label="`${officialsGameday.codeCompetition} - ${officialsGameday.phase}`"
          @changed="loadGamedays"
        />
        <p v-else class="text-sm">{{ getOfficialsSummary(officialsGameday) || '—' }}</p>

        <!-- Actions -->
        <div class="flex justify-between pt-4 border-t">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-danger-700 bg-white dark:bg-header-900 border border-danger-300 rounded-lg hover:bg-danger-50 dark:hover:bg-danger-950"
            @click="printJurySheet(officialsGameday)"
          >
            <UIcon name="heroicons:document-text" class="w-5 h-5 inline mr-1" />
            {{ t('gamedays.officials_print') }}
          </button>
          <div class="flex gap-2">
            <button
              type="button"
              class="px-4 py-2 text-sm font-medium text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg hover:bg-header-50 dark:hover:bg-header-800"
              @click="officialsModalOpen = false"
            >
              {{ t('common.close') }}
            </button>
          </div>
        </div>
      </div>
    </AdminModal>

    <!-- Scroll to top -->
    <AdminScrollToTop :title="t('common.scroll_to_top')" />
  </div>
</template>
