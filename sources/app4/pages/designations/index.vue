<script setup lang="ts">
import type { PlayerAutocomplete } from '~/types'
import { canAddHolder, countStatuses, gamedayDates, holderLabel, scopeOfRow, statusClasses, type CoverageCell, type CoverageHolder, type CoverageMatrix, type RoleDefinition, type RowKind } from '~/utils/designations'

/**
 * Suivi des Désignations, profils 1 et 2 (DOC/specs/FEATURE_DESIGNATIONS.md § 4, ticket designations/07) : matrice de
 * couverture (compétitions, journées, équipes × rôles), ajout et retrait depuis une case, récapitulatif par personne
 * et par club. Les états et l'héritage viennent de api2.
 */
definePageMeta({
  layout: 'admin',
  middleware: ['auth'],
})

type Tab = 'matrix' | 'summary'
type Level = 'all' | 'national' | 'other'
interface SummaryPerson { matric: number, name: string, club: string | null, designations: Record<string, number>, gamedays: Record<string, number> }
interface SummaryClub { club: string | null, people: number, designations: Record<string, number>, gamedays: Record<string, number> }
interface Target { kind: RowKind, id: string | number, role: string, label: string }

const { t } = useI18n()
const api = useApi()
const toast = useToast()
const authStore = useAuthStore()
const workContext = useWorkContextStore()

if (authStore.profile > 2) {
  navigateTo('/')
}

const tab = ref<Tab>('matrix')
const level = ref<Level>('national')
const onlyGaps = ref(false)
const loading = ref(false)
const matrix = ref<CoverageMatrix | null>(null)
const roles = ref<Record<string, RoleDefinition>>({})
const summary = ref<{ people: SummaryPerson[], clubs: SummaryClub[] } | null>(null)
const summaryFrom = ref('')
const summaryTo = ref('')
const target = ref<Target | null>(null)
const person = ref<PlayerAutocomplete | null>(null)
const draft = reactive({ email: '', authority: 'CNA', order: 1, quality: '' })
const saving = ref(false)
const removing = ref<CoverageHolder | null>(null)

const season = computed(() => workContext.season)
const counts = computed(() => (matrix.value ? countStatuses(matrix.value) : { red: 0, orange: 0 }))
const roleLabel = (code: string) => roles.value[code]?.label ?? code
const targetRole = computed(() => (target.value ? roles.value[target.value.role] : undefined))
const summaryRoles = computed(() => Object.keys(roles.value))

const loadRoles = async () => {
  roles.value = await api.get<Record<string, RoleDefinition>>('/admin/designations/roles')
}

const loadMatrix = async () => {
  if (!season.value) return
  loading.value = true
  try {
    matrix.value = await api.get<CoverageMatrix>('/admin/designations/coverage', { season: season.value, level: level.value, onlyGaps: onlyGaps.value ? '1' : '0' })
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

const reload = () => (tab.value === 'matrix' ? loadMatrix() : loadSummary())

const openAdd = (kind: RowKind, id: string | number, role: string, label: string) => {
  target.value = { kind, id, role, label }
  person.value = null
  Object.assign(draft, { email: '', authority: 'CNA', order: 1, quality: '' })
}

const save = async () => {
  if (!target.value || !person.value || !season.value) return
  saving.value = true
  try {
    const created = await api.post<{ account: 'created' | 'existing' | 'none' }>('/admin/designations', {
      season: season.value,
      role: target.value.role,
      ...scopeOfRow(target.value.kind, target.value.id),
      matric: person.value.matric,
      authority: draft.authority,
      order: draft.order,
      quality: draft.quality || null,
      email: draft.email || null,
    })
    toast.add({ title: t(`designations.account.${created.account}`), color: created.account === 'none' && targetRole.value?.mandateProfile ? 'warning' : 'success' })
    target.value = null
    await loadMatrix()
  } catch {
    // useApi affiche déjà l'erreur
  } finally {
    saving.value = false
  }
}

const confirmRemove = async () => {
  if (!removing.value) return
  try {
    await api.del(`/admin/designations/${removing.value.designationId}`)
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

watch([season, level, onlyGaps], () => loadMatrix())
watch(tab, () => reload())
onMounted(async () => {
  await workContext.initContext?.()
  await loadRoles()
  await loadMatrix()
})
</script>

<template>
  <div>
    <AdminPageHeader :title="t('designations.title')" />

    <div class="flex flex-wrap items-center gap-3 mb-4">
      <div class="inline-flex rounded-md shadow-sm" role="tablist">
        <button
          v-for="name in (['matrix', 'summary'] as Tab[])"
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
    </div>

    <!-- Matrice de couverture (SUI-01 à SUI-05) -->
    <template v-if="tab === 'matrix'">
      <div class="flex flex-wrap items-center gap-4 mb-4">
        <label class="text-sm">
          {{ t('designations.level.label') }}
          <select v-model="level" class="ml-2 rounded border-header-300 dark:bg-header-900 dark:border-header-700 text-sm">
            <option v-for="value in (['national', 'other', 'all'] as Level[])" :key="value" :value="value">{{ t(`designations.level.${value}`) }}</option>
          </select>
        </label>
        <label class="text-sm inline-flex items-center gap-2">
          <input v-model="onlyGaps" type="checkbox" class="rounded">
          {{ t('designations.only_gaps') }}
        </label>
        <span class="text-sm"><span class="px-2 py-0.5 rounded bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-100">{{ t('designations.count.red', { n: counts.red }) }}</span></span>
        <span class="text-sm"><span class="px-2 py-0.5 rounded bg-orange-100 text-orange-900 dark:bg-orange-900/40 dark:text-orange-100">{{ t('designations.count.orange', { n: counts.orange }) }}</span></span>
      </div>

      <p v-if="loading" class="text-sm text-header-500">{{ t('common.loading') }}</p>
      <p v-else-if="matrix && matrix.competitions.length === 0" class="text-sm text-header-500">{{ t('designations.nothing') }}</p>

      <div v-for="competition in matrix?.competitions ?? []" :key="competition.code" class="mb-6 overflow-x-auto">
        <table class="min-w-full text-sm border border-header-200 dark:border-header-700">
          <caption class="text-left font-semibold py-2">
            {{ competition.code }} — {{ competition.label }}
          </caption>
          <thead>
            <tr class="bg-header-50 dark:bg-header-800">
              <th scope="col" class="px-2 py-1 text-left">{{ t('designations.column.line') }}</th>
              <th v-for="role in matrix?.gamedayRoles ?? []" :key="role" scope="col" class="px-2 py-1 text-left">{{ roleLabel(role) }}</th>
            </tr>
          </thead>
          <tbody>
            <!-- RC de la compétition -->
            <tr>
              <th scope="row" class="px-2 py-1 text-left font-medium">{{ roleLabel('RC') }}</th>
              <td :colspan="matrix?.gamedayRoles.length" class="px-2 py-1" :class="statusClasses(competition.cells.RC.status)" :title="cellTitle(competition.cells.RC)">
                <DesignationsCellHolders
                  :cell="competition.cells.RC"
                  kind="competition"
                  :can-add="canAddHolder(competition.cells.RC, 'competition', roles.RC)"
                  @add="openAdd('competition', competition.code, 'RC', competition.label)"
                  @remove="removing = $event"
                />
              </td>
            </tr>
            <!-- Journées : rôles de journée, héritage visible (SUI-03) -->
            <tr v-for="gameday in competition.gamedays" :key="gameday.id">
              <th scope="row" class="px-2 py-1 text-left font-normal whitespace-nowrap">
                {{ gamedayDates(gameday.start, gameday.end) }} · {{ gameday.label }}<span v-if="gameday.place"> · {{ gameday.place }}</span>
              </th>
              <td v-for="role in matrix?.gamedayRoles ?? []" :key="role" class="px-2 py-1 align-top" :class="statusClasses(gameday.cells[role].status)" :title="cellTitle(gameday.cells[role])">
                <DesignationsCellHolders
                  :cell="gameday.cells[role]"
                  kind="gameday"
                  :can-add="canAddHolder(gameday.cells[role], 'gameday', roles[role])"
                  @add="openAdd('gameday', gameday.id, role, `${competition.code} · ${gameday.label}`)"
                  @remove="removing = $event"
                />
              </td>
            </tr>
            <!-- Équipes sans responsable (SUI-02) -->
            <tr v-for="team in competition.teams" :key="`team-${team.id}`">
              <th scope="row" class="px-2 py-1 text-left font-normal">{{ t('designations.team', { team: team.label }) }}</th>
              <td :colspan="matrix?.gamedayRoles.length" class="px-2 py-1" :class="statusClasses(team.cells.RESP_EQUIPE.status)" :title="cellTitle(team.cells.RESP_EQUIPE)">
                <DesignationsCellHolders
                  :cell="team.cells.RESP_EQUIPE"
                  kind="team"
                  :can-add="canAddHolder(team.cells.RESP_EQUIPE, 'team', roles.RESP_EQUIPE)"
                  @add="openAdd('team', team.id, 'RESP_EQUIPE', team.label)"
                  @remove="removing = $event"
                />
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- Récapitulatif par personne et par club (SUI-06) -->
    <template v-else>
      <form class="flex flex-wrap items-end gap-4 mb-4" @submit.prevent="loadSummary">
        <label class="text-sm">{{ t('designations.period.from') }}
          <input v-model="summaryFrom" type="date" class="ml-2 rounded border-header-300 dark:bg-header-900 dark:border-header-700 text-sm">
        </label>
        <label class="text-sm">{{ t('designations.period.to') }}
          <input v-model="summaryTo" type="date" class="ml-2 rounded border-header-300 dark:bg-header-900 dark:border-header-700 text-sm">
        </label>
        <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm">{{ t('designations.period.apply') }}</button>
      </form>
      <p class="text-sm text-header-600 dark:text-header-300 mb-2">{{ t('designations.summary_help') }}</p>

      <template v-for="section in (['people', 'clubs'] as const)" :key="section">
        <h2 class="font-semibold mt-6 mb-2">{{ t(`designations.summary.${section}`) }}</h2>
        <div class="overflow-x-auto">
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
                  <template v-if="'matric' in row">{{ row.name }} <span class="text-header-500">({{ row.club ?? '—' }})</span></template>
                  <template v-else>{{ row.club ?? t('designations.no_club') }}</template>
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

    <!-- Désigner depuis une case -->
    <AdminModal :open="target !== null" :title="target ? t('designations.add.title', { role: roleLabel(target.role), line: target.label }) : ''" @close="target = null">
      <form v-if="target" class="space-y-4" @submit.prevent="save">
        <div>
          <label class="block text-sm font-medium mb-1">{{ t('designations.add.person') }} *</label>
          <AdminPlayerAutocomplete v-model="person" :placeholder="t('designations.add.person_placeholder')" />
        </div>
        <div v-if="targetRole?.mandateProfile">
          <label class="block text-sm font-medium mb-1" for="designation-email">{{ t('designations.add.email') }}</label>
          <input id="designation-email" v-model="draft.email" type="email" class="w-full rounded border-header-300 dark:bg-header-900 dark:border-header-700" aria-describedby="designation-email-help">
          <p id="designation-email-help" class="text-xs text-header-500 mt-1">{{ t('designations.add.email_help') }}</p>
        </div>
        <div v-if="(targetRole?.authorities.length ?? 0) > 1">
          <label class="block text-sm font-medium mb-1" for="designation-authority">{{ t('designations.add.authority') }}</label>
          <select id="designation-authority" v-model="draft.authority" class="w-full rounded border-header-300 dark:bg-header-900 dark:border-header-700">
            <option v-for="authority in targetRole?.authorities ?? []" :key="authority" :value="authority">{{ t(`designations.authority.${authority}`) }}</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-medium mb-1" for="designation-order">{{ t('designations.add.order') }}</label>
            <input id="designation-order" v-model.number="draft.order" type="number" min="1" max="99" class="w-full rounded border-header-300 dark:bg-header-900 dark:border-header-700">
          </div>
          <div>
            <label class="block text-sm font-medium mb-1" for="designation-quality">{{ t('designations.add.quality') }}</label>
            <input id="designation-quality" v-model="draft.quality" type="text" maxlength="100" class="w-full rounded border-header-300 dark:bg-header-900 dark:border-header-700">
          </div>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" class="px-3 py-1.5 rounded border border-header-300 dark:border-header-700 text-sm" @click="target = null">{{ t('common.cancel') }}</button>
          <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm disabled:opacity-50" :disabled="!person || saving">{{ t('designations.add.submit') }}</button>
        </div>
      </form>
    </AdminModal>

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
