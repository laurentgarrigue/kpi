<template>
  <div class="container-fluid pb-16">
    <AppSecondaryNav show-event-info>
      <template #left>
        <div class="flex items-center gap-1 sm:gap-2">
          <label class="hidden sm:inline text-sm font-medium text-gray-700">{{ t('Teams.SelectTeam') }}:</label>
          <select
            v-model="selectedTeamId"
            @change="onTeamChange"
            class="px-2 sm:px-3 py-1 text-sm sm:text-base border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 min-w-40 sm:min-w-64 cursor-pointer"
          >
            <option value="">{{ t('Teams.PleaseSelectOne') }}</option>
            <option v-for="team in availableTeams" :key="team.team_id" :value="team.team_id">
              {{ team.label }}
            </option>
          </select>
        </div>
      </template>
      <template #right>
        <div class="flex items-center gap-1">
          <button
            v-if="prefs?.scr_team_id"
            @click="backToOverview"
            class="p-2 rounded-md hover:bg-gray-100 cursor-pointer"
            :title="t('Scrutineering.Overview.Title')"
          >
            <UIcon name="i-heroicons-table-cells" class="h-6 w-6" />
          </button>
          <button v-if="prefs?.scr_team_id && visibleButton" @click="handleRefresh" class="p-2 rounded-md hover:bg-gray-100 cursor-pointer">
            <UIcon name="i-heroicons-arrow-path" class="h-6 w-6" />
          </button>
        </div>
      </template>
    </AppSecondaryNav>

    <div v-if="user">
      <div v-if="authorized && user.profile <= 3">

        <!-- Overview (no team selected) -->
        <div v-if="!selectedTeamId && prefs?.lastEvent?.id">
          <div class="px-4 py-2 bg-gray-50 border-b flex items-center justify-between">
            <h1 class="text-lg font-bold text-gray-800">{{ t('Scrutineering.Overview.Title') }}</h1>
            <button
              type="button"
              class="p-2 rounded-md hover:bg-gray-100 cursor-pointer"
              :disabled="refreshingOverview"
              :title="t('Scrutineering.Overview.Refresh')"
              @click="refreshOverview"
            >
              <UIcon name="i-heroicons-arrow-path" :class="['h-6 w-6', refreshingOverview && 'animate-spin']" />
            </button>
          </div>
          <ScrOverview
            ref="overviewRef"
            :event-id="prefs.lastEvent.id"
            @select-team="onOverviewSelectTeam"
          />
        </div>

        <!-- Team detail view -->
        <div v-if="selectedTeamId">
          <!-- Team Name and Logo -->
          <div v-if="prefs?.scr_team_id" class="px-4 py-1 bg-gray-50 border-b">
            <div class="flex items-center justify-between gap-3">
              <div class="flex items-center gap-3">
                <img
                  v-if="prefs?.scr_team_logo"
                  class="h-12 w-12"
                  :src="`${baseUrl}/img/${prefs.scr_team_logo}`"
                  alt="Logo"
                />
                <h2 class="text-xl font-bold text-gray-800">{{ prefs?.scr_team_label }}</h2>
              </div>
              <h1 class="text-xl font-bold text-gray-800">{{ t('nav.Scrutineering') }}</h1>
            </div>
          </div>

        <div v-if="prefs?.scr_team_id" class="p-2">
          <div class="overflow-x-auto">
            <table class="min-w-full bg-white border border-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-1 py-2 text-left text-sm font-medium text-gray-700 border-b">
                    {{ t('Scrutineering.Player') }}
                  </th>
                  <th class="px-1 py-2 text-center text-sm font-medium text-gray-700 border-b">
                    {{ t('Scrutineering.Kayak') }}
                  </th>
                  <th class="px-1 py-2 text-center text-sm font-medium text-gray-700 border-b">
                    {{ t('Scrutineering.Vest') }}
                  </th>
                  <th class="px-1 py-2 text-center text-sm font-medium text-gray-700 border-b">
                    {{ t('Scrutineering.Helmet') }}
                  </th>
                  <th class="px-1 py-2 text-center text-sm font-medium text-gray-700 border-b">
                    {{ t('Scrutineering.Paddles') }}
                  </th>
                  <th class="px-1 py-1 text-center text-sm font-medium text-gray-700 border-b">
                    {{ t('Scrutineering.Comments') }}
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="player in players" :key="player.player_id" class="border-b hover:bg-gray-50">
                  <td class="px-2 py-2">
                    <span v-if="player.cap !== 'E'" class="inline-block px-2 py-1 text-xs font-bold text-white bg-gray-800 rounded">
                      {{ player.num }}
                    </span>
                    <span v-if="player.cap === 'E'" class="inline-block px-2 py-1 text-xs font-bold text-white bg-gray-800 rounded">
                      {{ t('Scrutineering.Coach') }}
                    </span>
                    <span class="text-xs md:text-sm lg:text-base ml-1">{{ player.last_name }} {{ player.first_name }}</span>
                    <span v-if="player.cap === 'C'" class="inline-block px-2 py-1 text-xs font-bold text-gray-900 bg-yellow-400 rounded ml-1">
                      C
                    </span>
                  </td>
                  <td v-if="player.cap !== 'E'" class="px-1 py-2 text-center border-r">
                    <button
                      type="button"
                      :class="getEquipmentButtonClass(player.kayak_status)"
                      @click="updatePlayer(player.player_id, 'kayak_status', player.kayak_status)"
                    >
                      <UIcon :name="getEquipmentIcon(player.kayak_status)" class="h-8 w-8" />
                    </button>
                  </td>
                  <td v-if="player.cap !== 'E'" class="px-1 py-2 text-center">
                    <button
                      type="button"
                      :class="getEquipmentButtonClass(player.vest_status)"
                      @click="updatePlayer(player.player_id, 'vest_status', player.vest_status)"
                    >
                      <UIcon :name="getEquipmentIcon(player.vest_status)" class="h-8 w-8" />
                    </button>
                  </td>
                  <td v-if="player.cap !== 'E'" class="px-1 py-2 text-center">
                    <button
                      type="button"
                      :class="getEquipmentButtonClass(player.helmet_status)"
                      @click="updatePlayer(player.player_id, 'helmet_status', player.helmet_status)"
                    >
                      <UIcon :name="getEquipmentIcon(player.helmet_status)" class="h-8 w-8" />
                    </button>
                  </td>
                  <td v-if="player.cap !== 'E'" class="px-1 py-2 text-center">
                    <button
                      type="button"
                      :class="getPaddleButtonClass(player.paddle_count)"
                      @click="updatePlayer(player.player_id, 'paddle_count', player.paddle_count)"
                    >
                      <b class="text-lg">{{ player.paddle_count || 0 }}</b>
                    </button>
                  </td>
                  <td v-if="player.cap !== 'E'" class="px-1 py-1 text-center">
                    <button
                      type="button"
                      @click="openCommentModal(player)"
                      class="px-2 py-1 text-xs text-left text-gray-700 border border-gray-300 rounded hover:bg-gray-50 w-full min-h-[3rem] max-h-[3rem] overflow-hidden line-clamp-3"
                      :title="player.comment || t('Scrutineering.AddComment')"
                    >
                      <span v-if="player.comment" class="block break-words">
                        {{ player.comment.substring(0, 25) }}{{ player.comment.length > 25 ? '...' : '' }}
                      </span>
                      <span v-else class="text-gray-400 italic">{{ t('Scrutineering.AddComment') }}</span>
                    </button>
                  </td>
                  <td v-if="player.cap === 'E'" colspan="5" class="px-4 py-2"></td>
                </tr>
              </tbody>
              <tfoot class="bg-gray-50">
                <tr class="border-t-2 border-gray-300">
                  <td class="px-2 py-2 text-right text-sm font-medium text-gray-700">
                    {{ t('Scrutineering.TotalValidPaddles') }}
                  </td>
                  <td class="px-1 py-2"></td>
                  <td class="px-1 py-2"></td>
                  <td class="px-1 py-2"></td>
                  <td class="px-1 py-2 text-center">
                    <span class="inline-flex items-center justify-center px-3 py-1 text-lg font-bold text-white bg-green-600 rounded">
                      {{ totalValidPaddles }}
                    </span>
                  </td>
                  <td class="px-1 py-2"></td>
                </tr>
              </tfoot>
            </table>
          </div>

          <div class="mt-4">
            <button
              type="button"
              @click="isResetModalOpen = true"
              class="px-4 py-2 text-sm text-red-600 border border-red-600 rounded hover:bg-red-50 flex items-center"
            >
              <UIcon name="i-heroicons-trash" class="h-5 w-5 mr-1" />
              {{ t('Scrutineering.ResetTeam') }}
            </button>
          </div>

          <div class="mt-4">
            <label class="text-sm font-medium text-gray-700 mb-2 block">
              <i>{{ t('Scrutineering.Legend') }}:</i>
            </label>
            <div class="inline-grid grid-cols-3 gap-2">
              <div class="flex items-center">
                <UIcon name="i-heroicons-clipboard" class="h-8 w-8 text-gray-400" />
                <span class="px-1 py-1 text-sm text-gray-600 font-bold">
                  {{ t('Scrutineering.NotChecked') }}
                </span>
              </div>
              <div class="flex items-center">
                <UIcon name="i-heroicons-check-circle-solid" class="h-8 w-8 text-green-600" />
                <span class="px-1 py-1 text-sm text-green-600 font-bold">
                  {{ t('Scrutineering.Valid') }}
                </span>
              </div>
              <div class="flex items-center gap-1">
                <div class="flex gap-0.5">
                  <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-gray-700 bg-gray-200 rounded">0</span>
                  <span class="text-gray-600 font-bold">/</span>
                  <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-green-600 rounded">6</span>
                </div>
                <span class="px-1 py-1 text-sm text-green-600 font-bold">
                  {{ t('Scrutineering.ValidPaddles') }}
                </span>
              </div>
              <div class="flex items-center">
                <UIcon name="i-heroicons-exclamation-circle-solid" class="h-8 w-8 text-red-600" />
                <span class="px-1 py-1 text-sm text-red-600 font-bold">
                  {{ t('Scrutineering.Cosmetic') }}
                </span>
              </div>
              <div class="flex items-center">
                <UIcon name="i-heroicons-exclamation-triangle-solid" class="h-8 w-8 text-red-600" />
                <span class="px-1 py-1 text-red-600 font-bold">
                  {{ t('Scrutineering.Safety') }}
                </span>
              </div>
              <div class="flex items-center">
                <UIcon name="i-heroicons-shield-exclamation-solid" class="h-8 w-8 text-red-600" />
                <span class="px-1 py-1 text-sm text-red-600 font-bold">
                  {{ t('Scrutineering.Technical') }}
                </span>
              </div>
            </div>
          </div>
        </div>
        </div><!-- end team detail view -->
      </div>

      <div v-else class="p-4">
        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4" role="alert">
          <div class="flex justify-between items-center">
            <p class="font-bold text-yellow-800">
              {{ t('Scrutineering.ChangeEvent') }}
            </p>
            <div class="flex gap-2">
              <button
                type="button"
                class="px-4 py-2 text-sm bg-white text-yellow-900 border border-yellow-400 rounded hover:bg-yellow-100 flex items-center"
                :disabled="refreshingRights"
                @click="refreshRights"
              >
                <UIcon name="i-heroicons-arrow-path" :class="['h-4 w-4 mr-1', refreshingRights && 'animate-spin']" />
                {{ t('Scrutineering.RefreshRights') }}
              </button>
              <button
                type="button"
                class="px-4 py-2 text-sm bg-yellow-400 text-yellow-900 rounded hover:bg-yellow-500 flex items-center"
                @click="navigateTo('/')"
              >
                <UIcon name="i-heroicons-arrow-left" class="h-4 w-4 mr-1" />
                {{ t('nav.ChangeEvent') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
    <CommentModal
      :is-open="isCommentModalOpen"
      :title="modalTitle"
      :comment="selectedComment"
      @close="closeCommentModal"
      @save="saveComment"
    />

    <!-- Reset confirmation modal -->
    <div
      v-if="isResetModalOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
      @click.self="isResetModalOpen = false"
    >
      <div class="bg-white rounded-lg p-6 w-full max-w-md mx-4">
        <div class="flex items-center gap-2 mb-4">
          <UIcon name="i-heroicons-exclamation-triangle" class="h-6 w-6 text-red-600" />
          <h3 class="text-lg font-semibold text-gray-900">{{ t('Scrutineering.ResetTeamConfirmTitle') }}</h3>
        </div>
        <p class="text-sm text-gray-600 mb-6">{{ t('Scrutineering.ResetTeamConfirm') }}</p>
        <div class="flex justify-end space-x-3">
          <button
            type="button"
            class="px-4 py-2 text-sm text-gray-700 border border-gray-300 rounded hover:bg-gray-50"
            :disabled="resetting"
            @click="isResetModalOpen = false"
          >
            {{ t('Scrutineering.Cancel') }}
          </button>
          <button
            type="button"
            class="px-4 py-2 text-sm text-white bg-red-600 rounded hover:bg-red-700 flex items-center disabled:opacity-50"
            :disabled="resetting"
            @click="confirmResetTeam"
          >
            <UIcon v-if="resetting" name="i-heroicons-arrow-path" class="h-4 w-4 mr-1 animate-spin" />
            {{ t('Scrutineering.Confirm') }}
          </button>
        </div>
      </div>
    </div>
    <AppFooter />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useScrutineering } from '~/composables/useScrutineering'
import { useUser } from '~/composables/useUser'
import { useStatus } from '~/composables/useStatus'

// Protect this page - require event selection
definePageMeta({
  middleware: 'event-guard'
})
import { usePrefs } from '~/composables/usePrefs'
import { usePreferenceStore } from '~/stores/preferenceStore'
import CommentModal from '~/components/CommentModal.vue'

const { t } = useI18n()
const { user, getUser } = useUser()
const { authorized, checkAuthorized, checkOnline } = useStatus()
const { prefs, getPrefs, updatePref } = usePrefs()
const { players, loadPlayers, updatePlayer, updateComment, resetTeam } = useScrutineering()
const { getApi } = useApi()
const preferenceStore = usePreferenceStore()
const visibleButton = ref(true)
const refreshingRights = ref(false)
const overviewRef = ref(null)
const refreshingOverview = ref(false)

const refreshOverview = async () => {
  refreshingOverview.value = true
  try {
    await overviewRef.value?.load()
  } finally {
    refreshingOverview.value = false
  }
}

const backToOverview = async () => {
  selectedTeamId.value = ''
  await updatePref({ scr_team_id: null, scr_team_label: null, scr_team_club: null, scr_team_logo: null })
  // Let the overview component re-mount, then refresh its data
  await nextTick()
  overviewRef.value?.load()
}

const onOverviewSelectTeam = async (team) => {
  selectedTeamId.value = team.team_id
  await updatePref({
    scr_team_id: team.team_id,
    scr_team_label: team.label,
    scr_team_club: team.club,
    scr_team_logo: team.logo
  })
  await loadPlayers()
}

const refreshRights = async () => {
  if (!checkOnline()) return
  refreshingRights.value = true
  try {
    const response = await getApi('/me')
    const data = await response.json()
    await preferenceStore.putItem('user', data.user)
    await checkAuthorized()
    if (authorized.value) {
      await loadTeams()
      if (prefs.value?.scr_team_id) {
        await loadPlayers()
      }
    }
  } catch {
    // error already handled by useApi (toast)
  } finally {
    refreshingRights.value = false
  }
}

// Page-specific SEO
useSeoMeta({
  title: 'Equipment Scrutineering - KPI Application',
  description: 'Team equipment verification and scrutineering management for kayak polo competitions. Staff access only.',
  ogTitle: 'Scrutineering - KPI Application',
  ogDescription: 'Equipment verification for canoe polo competitions',
  robots: 'noindex, nofollow' // Staff-only page
})

const handleRefresh = () => {
  visibleButton.value = false
  loadPlayers()
  setTimeout(() => {
    visibleButton.value = true
  }, 5000)
}
const runtimeConfig = useRuntimeConfig()
const baseUrl = runtimeConfig.public.backendBaseUrl

const isCommentModalOpen = ref(false)
const selectedPlayerId = ref(null)
const selectedComment = ref('')
const modalTitle = ref('')
const selectedTeamId = ref('')
const availableTeams = ref([])

const isResetModalOpen = ref(false)
const resetting = ref(false)

// Sum of validated paddles across non-coach players
const totalValidPaddles = computed(() =>
  players.value.reduce((sum, p) => {
    if (p.cap === 'E') return sum
    return sum + (Number(p.paddle_count) || 0)
  }, 0)
)

const confirmResetTeam = async () => {
  resetting.value = true
  try {
    await resetTeam()
    isResetModalOpen.value = false
  } finally {
    resetting.value = false
  }
}

// Load available teams
const loadTeams = async () => {
  if (!checkOnline()) {
    return
  }

  const eventMode = prefs.value?.eventMode
  const lastGroup = prefs.value?.lastGroup
  const lastSeason = prefs.value?.lastSeason
  const lastEvent = prefs.value?.lastEvent

  // Determine API URL based on mode
  let apiUrl
  if (eventMode === 'group' && lastGroup?.code && lastSeason) {
    apiUrl = `/group/${lastSeason}/${lastGroup.code}/teams`
  } else if (lastEvent?.id) {
    apiUrl = `/staff/${lastEvent.id}/teams`
  } else {
    console.error('No event or group selected')
    return
  }

  try {
    const response = await getApi(apiUrl)

    if (!response.ok) {
      throw new Error(`Failed to load teams: ${response.status}`)
    }

    const data = await response.json()
    availableTeams.value = data.sort((a, b) => a.label.localeCompare(b.label))
    selectedTeamId.value = prefs.value?.scr_team_id || ''
  } catch (error) {
    console.error('Error loading teams:', error)
  }
}

// Handle team change
const onTeamChange = async () => {
  if (!checkOnline() || !selectedTeamId.value) {
    return
  }

  const selectedTeam = availableTeams.value.find(t => t.team_id === selectedTeamId.value)
  if (selectedTeam) {
    await updatePref({
      scr_team_id: selectedTeam.team_id,
      scr_team_label: selectedTeam.label,
      scr_team_club: selectedTeam.club,
      scr_team_logo: selectedTeam.logo
    })
    await loadPlayers()
  }
}

onMounted(async () => {
  await getUser()
  await checkAuthorized()
  await getPrefs()
  await loadTeams()
  if (prefs.value?.scr_team_id) {
    loadPlayers()
  }
})

const getEquipmentButtonClass = (status) => {
  const baseClass = 'inline-flex items-center justify-center px-2 py-1 rounded'
  if (status === 1) return `${baseClass} text-green-600 hover:text-green-700`
  if (status > 1) return `${baseClass} text-red-600 hover:text-red-700`
  return `${baseClass} text-gray-400 text:bg-gray-500`
}

const getEquipmentIcon = (status) => {
  if (status < 1) return 'i-heroicons-clipboard'
  if (status === 1) return 'i-heroicons-check-circle-solid'
  if (status === 2) return 'i-heroicons-exclamation-circle-solid'
  if (status === 3) return 'i-heroicons-exclamation-triangle-solid'
  if (status === 4) return 'i-heroicons-shield-exclamation-solid'
  return 'i-heroicons-clipboard'
}

const getPaddleButtonClass = (count) => {
  const baseClass = 'inline-flex items-center justify-center px-3 py-1 text-sm rounded'
  if (count > 0) return `${baseClass} text-white bg-green-600 hover:bg-green-700`
  return `${baseClass} text-gray-700 bg-gray-200 hover:bg-gray-300`
}

const openCommentModal = (player) => {
  selectedPlayerId.value = player.player_id
  selectedComment.value = player.comment || ''
  modalTitle.value = `${player.last_name} ${player.first_name}`
  isCommentModalOpen.value = true
}

const closeCommentModal = () => {
  isCommentModalOpen.value = false
  selectedPlayerId.value = null
  selectedComment.value = ''
  modalTitle.value = ''
}

const saveComment = async (comment) => {
  if (selectedPlayerId.value) {
    await updateComment(selectedPlayerId.value, comment)
    closeCommentModal()
  }
}
</script>
