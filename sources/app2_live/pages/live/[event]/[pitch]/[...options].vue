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
const { fetchGameIdForPitch, fetchGame, loading, error, cleanup } = useGame()
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

  // Fetch game ID for pitch
  if (eventId.value && pitch.value) {
    try {
      const gameId = await fetchGameIdForPitch(eventId.value, pitch.value)

      if (gameId) {
        // Fetch game data
        await fetchGame(gameId)
      } else {
        error.value = t('NoGameForPitch')
      }
    } catch (err) {
      console.error('Error loading game:', err)
      error.value = t('ErrorLoadingGame')
    }
  } else {
    error.value = t('InvalidParameters')
  }
})

onUnmounted(() => {
  cleanup()
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
