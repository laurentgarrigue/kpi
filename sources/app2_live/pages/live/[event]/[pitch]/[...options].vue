<template>
  <div class="live-display">
    <component
      :is="displayComponent"
      v-if="gameData"
      :zone="zone"
      :mode="mode"
      :game-data="gameData"
    />

    <div v-else class="h-screen w-screen flex items-center justify-center bg-gray-900 text-white">
      <div class="text-center">
        <div v-if="loading" class="animate-pulse">
          <UIcon name="i-heroicons-arrow-path" class="h-16 w-16 mx-auto mb-4 animate-spin" />
          <p class="text-xl">{{ t('Loading') }}...</p>
        </div>
        <div v-else-if="error" class="text-red-500">
          <UIcon name="i-heroicons-exclamation-triangle" class="h-16 w-16 mx-auto mb-4" />
          <p class="text-xl">{{ error }}</p>
        </div>
        <div v-else>
          <UIcon name="i-heroicons-tv" class="h-16 w-16 mx-auto mb-4 text-gray-600" />
          <p class="text-xl text-gray-500">{{ t('NoGame') }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { useRouteOptions } from '~/composables/useRouteOptions'
import { useGame } from '~/composables/useGame'
import { useGameStore } from '~/stores/gameStore'

import ScoreDisplay from '~/components/display/Score.vue'
import MainDisplay from '~/components/display/Main.vue'
import MatchDisplay from '~/components/display/Match.vue'

const route = useRoute()
const { t } = useI18n()
const { display, zone, mode, checkOptions } = useRouteOptions()
const {
  fetchGameIdForPitch,
  fetchGame,
  startGameRotation,
  startPitchCheck,
  enableWebSocketMode,
  loading,
  error,
  cleanup
} = useGame()
const gameStore = useGameStore()

const gameData = computed(() => gameStore.currentGame)

const eventId = computed(() => parseInt(route.params.event))
const pitch = computed(() => parseInt(route.params.pitch))

const displayComponent = computed(() => {
  const components = {
    'score': ScoreDisplay,
    'main': MainDisplay,
    'match': MatchDisplay
  }
  return components[display.value] || ScoreDisplay
})

onMounted(async () => {
  // Parse URL options
  checkOptions()

  if (!eventId.value || !pitch.value) {
    error.value = t('InvalidParameters')
    return
  }

  try {
    // 1. Try to load network config and setup WebSocket if available
    const { getNetworkConfig } = useApi()
    const { connect, setPitchFilter, connected } = useWebSocket()

    try {
      const networkConfig = await getNetworkConfig(eventId.value)

      // Extract network config from nested structure
      const wsConfig = networkConfig?.network?.global

      if (wsConfig && wsConfig.url) {
        console.log('Network config found, connecting to WebSocket:', wsConfig.url)

        // Set pitch filter before connecting (format: "eventId_pitch")
        setPitchFilter(eventId.value, pitch.value)

        // Connect to WebSocket (classic or STOMP based on config)
        connect({
          url: wsConfig.url,
          password: wsConfig.password || '',
          stomp: wsConfig.stomp === true, // Use STOMP only if explicitly set to true
          login: wsConfig.login || '',
          topics: wsConfig.topics || ['/game/chrono', '/game/period', '/game/data-game', '/game/player-info']
        })

        // Enable WebSocket mode (disables HTTP polling for score/chrono)
        enableWebSocketMode()
      } else {
        console.log('No network config found, will use HTTP polling')
      }
    } catch (err) {
      console.log('Network config not available, using HTTP polling fallback:', err)
    }

    // 2. Fetch initial game ID for this pitch
    const gameId = await fetchGameIdForPitch(eventId.value, pitch.value)

    if (gameId) {
      // 3. Fetch initial game data (global, score, chrono)
      await fetchGame(gameId)

      // 4. Start HTTP polling if WebSocket not connected
      // If WebSocket is connected, updates will come via WebSocket
      if (!connected.value) {
        console.log('Starting HTTP polling for score/chrono updates')
        startGameRotation(gameId)
      } else {
        console.log('WebSocket connected, score/chrono will update via WebSocket')
      }
    } else {
      error.value = t('NoGameForPitch')
    }

    // 5. Start periodic pitch check (every 30 seconds) to detect match changes
    startPitchCheck(eventId.value, pitch.value)

  } catch (err) {
    console.error('Error loading game:', err)
    error.value = t('ErrorLoadingGame')
  }
})

onUnmounted(() => {
  cleanup()
  const { disconnect } = useWebSocket()
  disconnect()
})

definePageMeta({
  layout: 'default'
})

useSeoMeta({
  title: 'KPI Live - Display',
  description: 'Live display for kayak polo match',
  robots: 'noindex, nofollow'
})
</script>

<style scoped>
.live-display {
  width: 100vw;
  height: 100vh;
  overflow: hidden;
}
</style>
