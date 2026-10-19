<script setup lang="ts">
import type { LastUpdateInfo } from '~/types/presence'
import type { EquipmentParameter, ScrutineeringPlayer, ScrutineeringSheet } from '~/types/scrutineering'
import { contextEventId, controlPlayers, isStaff, nextEquipmentValue, totalValidPaddles } from '~/utils/scrutineering'

/** Equipment control of a team (PAGE_SCRUTINEERING.md § 3): kayak, vest, helmet, paddles and comment per player. */
definePageMeta({ layout: 'admin', middleware: ['auth', 'scrutineering'] })

const COMMENT_MAX_LENGTH = 255
const COMMENT_PREVIEW_LENGTH = 25
const REFRESH_COOLDOWN_MS = 5000
const STATUS_COLUMNS: { parameter: Exclude<EquipmentParameter, 'paddle_count'>, labelKey: string }[] = [
  { parameter: 'kayak_status', labelKey: 'scrutineering.kayak' },
  { parameter: 'vest_status', labelKey: 'scrutineering.vest' },
  { parameter: 'helmet_status', labelKey: 'scrutineering.helmet' },
]
const LEGEND_STATUSES = [0, 1, 2, 3, 4]

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const config = useRuntimeConfig()
const workContext = useWorkContextStore()
const scrutineeringApi = useScrutineeringApi()

const teamId = computed(() => Number.parseInt(route.params.teamId as string, 10))
const commentPlayer = ref<ScrutineeringPlayer | null>(null)
const commentText = ref('')
const commentInput = ref<HTMLTextAreaElement | null>(null)
const resetModalOpen = ref(false)
const modalOpen = computed(() => commentPlayer.value !== null || resetModalOpen.value)
const navigation = useScrutineeringNavigation(teamId, 'scrutineering', modalOpen)

const sheet = ref<ScrutineeringSheet | null>(null)
const loading = ref(false)
const loadError = ref(false)
const refreshCoolingDown = ref(false)
const resetting = ref(false)
const savingComment = ref(false)

const players = computed(() => controlPlayers(sheet.value?.players ?? []))
const paddlesTotal = computed(() => totalValidPaddles(players.value))
const logoUrl = computed(() => (sheet.value?.team.logo ? `${config.public.legacyBaseUrl}/img/${sheet.value.team.logo}` : ''))
const controlPdfUrl = computed(() => {
  const team = sheet.value?.team
  if (!team) return ''
  const params = new URLSearchParams({ equipe: String(team.team_id), compet: team.competition_code, season: team.season })
  return `${config.public.legacyBaseUrl}/admin/FeuilleControle.php?${params}`
})

// Event of the journal: the event of the context, when the team is one of its teams (§ 5)
const journalEventId = computed(() => (navigation.current.value ? contextEventId(navigation.context.value) : null))

async function load() {
  loading.value = true
  loadError.value = false
  try {
    sheet.value = await scrutineeringApi.sheet(teamId.value)
    syncCompetitionFilter()
  }
  catch {
    sheet.value = null
    loadError.value = true
  }
  finally {
    loading.value = false
  }
}

// The competition filter follows the team displayed, so that without an event the navigation covers its competition
function syncCompetitionFilter() {
  const code = sheet.value?.team.competition_code
  if (code && workContext.pageCompetitionCode !== code) {
    workContext.setPageCompetition(code)
  }
}

// Competition filter changed: open the first team of the newly selected competition
async function onCompetitionChange() {
  const code = workContext.pageCompetitionCode
  if (!sheet.value || !code || code === sheet.value.team.competition_code) return
  try {
    if (!(await navigation.goToCompetition(code))) {
      toast.add({ title: t('common.error'), description: t('scrutineering.no_teams'), color: 'error', duration: 3000 })
    }
  }
  catch {
    toast.add({ title: t('scrutineering.load_error'), color: 'error', duration: 3000 })
  }
}

function refresh() {
  refreshCoolingDown.value = true
  load()
  setTimeout(() => { refreshCoolingDown.value = false }, REFRESH_COOLDOWN_MS)
}

async function cycle(player: ScrutineeringPlayer, parameter: EquipmentParameter) {
  const value = nextEquipmentValue(parameter, player[parameter])
  try {
    const { lastUpdate } = await scrutineeringApi.setEquipment(teamId.value, player.player_id, parameter, value, journalEventId.value)
    player[parameter] = value
    setLastUpdate(lastUpdate)
  }
  catch {
    toast.add({ title: t('scrutineering.save_error'), color: 'error', duration: 3000 })
  }
}

async function openComment(player: ScrutineeringPlayer) {
  commentPlayer.value = player
  commentText.value = player.comment
  await nextTick()
  commentInput.value?.focus()
}

async function saveComment() {
  const player = commentPlayer.value
  if (!player) return
  savingComment.value = true
  try {
    const { comment, lastUpdate } = await scrutineeringApi.setComment(teamId.value, player.player_id, commentText.value, journalEventId.value)
    player.comment = comment
    setLastUpdate(lastUpdate)
    commentPlayer.value = null
  }
  catch {
    toast.add({ title: t('scrutineering.save_error'), color: 'error', duration: 3000 })
  }
  finally {
    savingComment.value = false
  }
}

function setLastUpdate(lastUpdate: LastUpdateInfo | null) {
  if (sheet.value) sheet.value.lastUpdate = lastUpdate
}

async function confirmReset() {
  resetting.value = true
  try {
    await scrutineeringApi.reset(teamId.value, journalEventId.value)
    resetModalOpen.value = false
    toast.add({ title: t('scrutineering.reset_done'), color: 'success', duration: 2000 })
    await load()
  }
  catch {
    toast.add({ title: t('scrutineering.save_error'), color: 'error', duration: 3000 })
  }
  finally {
    resetting.value = false
  }
}

const commentPreview = (comment: string) =>
  comment.length > COMMENT_PREVIEW_LENGTH ? `${comment.slice(0, COMMENT_PREVIEW_LENGTH)}…` : comment

const playerName = (player: ScrutineeringPlayer) => `${player.last_name} ${player.first_name}`

watch(teamId, load)
onMounted(async () => {
  await workContext.initContext()
  await load()
})
</script>

<template>
  <div>
    <AdminPageHeader
      :title="t('scrutineering.title_team')"
      :competition-filtered-codes="workContext.pageFilteredCompetitionCodes"
      :has-notices="!!sheet?.team.locked"
      @competition-change="onCompetitionChange"
    >
      <template #notices>
        <div
          v-if="sheet?.team.locked"
          class="flex items-center gap-2 p-2 bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-900 rounded text-sm text-amber-800 dark:text-amber-200"
        >
          <UIcon name="heroicons:lock-closed" class="w-4 h-4 shrink-0" />
          {{ t('scrutineering.locked_notice') }}
        </div>
      </template>
    </AdminPageHeader>

    <!-- Team navigation, with the actions of the screen on the same row -->
    <AdminScrutineeringTeamNav :navigation="navigation" :team-id="teamId" view="scrutineering">
      <template #actions>
        <button
          type="button"
          class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg hover:bg-header-50 dark:hover:bg-header-800 disabled:opacity-50"
          :disabled="refreshCoolingDown || loading"
          @click="refresh"
        >
          <UIcon name="heroicons:arrow-path" :class="['w-5 h-5', loading && 'animate-spin']" />
          {{ t('scrutineering.refresh') }}
        </button>
        <a
          v-if="controlPdfUrl"
          :href="controlPdfUrl"
          target="_blank"
          rel="noopener"
          class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg hover:bg-header-50 dark:hover:bg-header-800"
        >
          <UIcon name="heroicons:document-text" class="w-5 h-5" />
          {{ t('scrutineering.pdf_control') }}
        </a>
      </template>
      <template #actions-end>
        <NuxtLink
          to="/scrutineering"
          class="flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg hover:bg-header-50 dark:hover:bg-header-800"
        >
          <UIcon name="heroicons:table-cells" class="w-5 h-5" />
          {{ t('scrutineering.overview') }}
        </NuxtLink>
      </template>
    </AdminScrutineeringTeamNav>

    <p v-if="loadError" class="p-4 rounded-lg bg-danger-50 dark:bg-danger-950 text-danger-700 dark:text-danger-300">
      {{ t('scrutineering.load_error') }}
    </p>

    <template v-else-if="sheet">
      <div class="mb-3 flex items-center gap-3">
        <img v-if="logoUrl" :src="logoUrl" alt="" class="h-12 w-12 object-contain">
        <div>
          <h2 class="text-xl font-bold text-header-900 dark:text-header-50">{{ sheet.team.label }}</h2>
          <p class="text-sm text-header-600 dark:text-header-300">
            {{ sheet.team.competition_code }} - {{ sheet.team.competition_label }} · {{ sheet.team.club_label }}
          </p>
        </div>
      </div>

      <p v-if="players.length === 0" class="p-4 rounded-lg bg-white dark:bg-header-900 text-header-600 dark:text-header-300">
        {{ t('scrutineering.no_players') }}
      </p>

      <div v-else class="overflow-x-auto bg-white dark:bg-header-900 rounded-lg shadow">
        <table class="min-w-full text-sm" data-testid="scrutineering-table">
          <thead class="bg-header-100 dark:bg-header-800">
            <tr>
              <th class="px-2 py-2 text-left font-semibold">{{ t('scrutineering.player') }}</th>
              <th v-for="column in STATUS_COLUMNS" :key="column.parameter" class="px-1 py-2 text-center font-semibold">{{ t(column.labelKey) }}</th>
              <th class="px-1 py-2 text-center font-semibold">{{ t('scrutineering.paddles') }}</th>
              <th class="px-1 py-2 text-center font-semibold">{{ t('scrutineering.comment') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="player in players" :key="player.player_id" class="border-t border-header-200 dark:border-header-800">
              <td class="px-2 py-2">
                <span class="inline-block min-w-7 px-2 py-1 text-xs font-bold text-center text-white bg-header-700 dark:bg-header-600 rounded">
                  {{ isStaff(player) ? t('scrutineering.coach') : (player.num ?? '') }}
                </span>
                <span class="ml-1">{{ playerName(player) }}</span>
                <span v-if="player.cap === 'C'" class="ml-1 inline-block px-2 py-1 text-xs font-bold text-header-900 bg-yellow-400 rounded">C</span>
              </td>
              <template v-if="!isStaff(player)">
                <td v-for="column in STATUS_COLUMNS" :key="column.parameter" class="px-1 py-1 text-center">
                  <button
                    type="button"
                    class="inline-flex items-center justify-center min-w-11 min-h-11 rounded hover:bg-header-100 dark:hover:bg-header-800"
                    :title="t(`scrutineering.legend_${player[column.parameter]}`)"
                    :data-testid="`${column.parameter}-${player.player_id}`"
                    @click="cycle(player, column.parameter)"
                  >
                    <AdminScrutineeringStatusIcon :status="player[column.parameter]" />
                  </button>
                </td>
                <td class="px-1 py-1 text-center">
                  <button
                    type="button"
                    class="inline-flex items-center justify-center min-w-11 min-h-11 px-3 text-lg font-bold rounded"
                    :class="player.paddle_count > 0 ? 'text-white bg-success-600 hover:bg-success-700' : 'text-header-700 dark:text-header-200 bg-header-200 dark:bg-header-700 hover:bg-header-300'"
                    :data-testid="`paddle_count-${player.player_id}`"
                    @click="cycle(player, 'paddle_count')"
                  >
                    {{ player.paddle_count }}
                  </button>
                </td>
                <td class="px-1 py-1">
                  <button
                    type="button"
                    class="w-full min-w-32 min-h-11 px-2 py-1 text-xs text-left border border-header-300 dark:border-header-700 rounded hover:bg-header-50 dark:hover:bg-header-800"
                    :title="player.comment || t('scrutineering.add_comment')"
                    @click="openComment(player)"
                  >
                    <span v-if="player.comment">{{ commentPreview(player.comment) }}</span>
                    <span v-else class="italic text-header-400">{{ t('scrutineering.add_comment') }}</span>
                  </button>
                </td>
              </template>
              <td v-else colspan="5" />
            </tr>
          </tbody>
          <tfoot class="bg-header-50 dark:bg-header-800 border-t-2 border-header-300 dark:border-header-700">
            <tr>
              <td :colspan="1 + STATUS_COLUMNS.length" class="px-2 py-2 text-right font-medium">{{ t('scrutineering.total_paddles') }}</td>
              <td class="px-1 py-2 text-center">
                <span class="inline-flex items-center justify-center px-3 py-1 text-lg font-bold text-white bg-success-600 rounded" data-testid="paddles-total">
                  {{ paddlesTotal }}
                </span>
              </td>
              <td />
            </tr>
          </tfoot>
        </table>
      </div>

      <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="mb-2 text-sm italic text-header-600 dark:text-header-300">{{ t('scrutineering.legend') }} :</p>
          <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 text-sm">
            <div v-for="status in LEGEND_STATUSES" :key="status" class="flex items-center gap-1">
              <AdminScrutineeringStatusIcon :status="status" size="w-6 h-6" />
              <span>{{ t(`scrutineering.legend_${status}`) }}</span>
            </div>
            <div class="flex items-center gap-1">
              <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-success-600 rounded">6</span>
              <span>{{ t('scrutineering.legend_paddles') }}</span>
            </div>
          </div>
        </div>
        <button
          type="button"
          class="flex items-center gap-1 px-4 py-2 text-sm text-danger-600 dark:text-danger-400 border border-danger-600 dark:border-danger-400 rounded-lg hover:bg-danger-50 dark:hover:bg-danger-950"
          @click="resetModalOpen = true"
        >
          <UIcon name="heroicons:trash" class="w-5 h-5" />
          {{ t('scrutineering.reset') }}
        </button>
      </div>
      <AdminTeamLastUpdate v-if="sheet?.lastUpdate" :last-update="sheet.lastUpdate" class="mt-4" />
    </template>

    <AdminModal
      :open="commentPlayer !== null"
      :title="commentPlayer ? t('scrutineering.comment_title', { name: playerName(commentPlayer) }) : ''"
      @close="commentPlayer = null"
    >
      <textarea
        ref="commentInput"
        v-model="commentText"
        rows="4"
        :maxlength="COMMENT_MAX_LENGTH"
        class="w-full px-3 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900"
      />
      <p class="mt-1 text-xs text-right text-header-500">{{ t('scrutineering.comment_max', { count: commentText.length }) }}</p>
      <template #footer>
        <button type="button" class="px-4 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg" @click="commentPlayer = null">
          {{ t('common.cancel') }}
        </button>
        <button type="button" class="px-4 py-2 text-sm text-white bg-primary-600 rounded-lg hover:bg-primary-700 disabled:opacity-50" :disabled="savingComment" @click="saveComment">
          {{ t('common.save') }}
        </button>
      </template>
    </AdminModal>

    <AdminConfirmModal
      :open="resetModalOpen"
      :title="t('scrutineering.reset_title')"
      :message="t('scrutineering.reset_message')"
      :confirm-text="t('scrutineering.reset')"
      :cancel-text="t('common.cancel')"
      :loading="resetting"
      danger
      @close="resetModalOpen = false"
      @confirm="confirmReset"
    />
  </div>
</template>
