<template>
  <div class="h-screen w-screen flex flex-col items-center justify-center bg-gray-900 text-white overflow-hidden">
    <!-- Score Banner -->
    <div
      :id="styleZone"
      class="w-full max-w-7xl px-8 py-6 animate__animated animate__fadeInDown"
      :class="{ 'static': mode === 'static' }"
    >
      <div class="grid grid-cols-7 gap-4 items-center">
        <!-- Team A -->
        <div class="col-span-3 flex items-center justify-end space-x-4">
          <div v-if="zone === 'inter' && teams.teamA.logo" class="team-logo" v-html="logo48(teams.teamA.logo)" />
          <div class="team-name text-right">{{ teamName(teams.teamA.name) }}</div>
          <div v-if="pen1 > 0" class="flex space-x-1">
            <div v-for="i in pen1" :key="`pen1-${i}`" class="penalty-indicator scored" />
          </div>
        </div>

        <!-- Center: Scores & Timer -->
        <div class="col-span-1 flex flex-col items-center space-y-2">
          <div class="flex items-center justify-center space-x-4">
            <div class="score-display">{{ score.scoreA }}</div>
            <div class="text-4xl">-</div>
            <div class="score-display">{{ score.scoreB }}</div>
          </div>

          <div class="timer-display" :class="{ 'timer-red': !matchHorlogeStarted }">
            {{ matchHorloge }}
          </div>

          <div class="period-badge">{{ matchPeriode }}</div>

          <div v-if="matchPossession" class="possession-timer text-yellow-400">
            {{ matchPossession }}s
          </div>
        </div>

        <!-- Team B -->
        <div class="col-span-3 flex items-center justify-start space-x-4">
          <div v-if="pen2 > 0" class="flex space-x-1">
            <div v-for="i in pen2" :key="`pen2-${i}`" class="penalty-indicator scored" />
          </div>
          <div class="team-name text-left">{{ teamName(teams.teamB.name) }}</div>
          <div v-if="zone === 'inter' && teams.teamB.logo" class="team-logo" v-html="logo48(teams.teamB.logo)" />
        </div>
      </div>
    </div>

    <!-- Category Badge -->
    <div class="category-badge mt-4 animate__animated animate__fadeInUp" :class="{ 'static': mode === 'static' }">
      {{ currentGame?.c_label || currentGame?.categ || '' }}
    </div>

    <!-- Event Banner (Goals, Cards, etc.) -->
    <div
      v-if="currentEvent && ['full', 'events', 'static'].includes(mode)"
      class="event-banner animate__animated animate__bounceIn"
    >
      <div class="flex items-center space-x-6">
        <img
          v-if="currentEvent.type"
          :src="eventIcon(currentEvent.type)"
          alt="Event"
          class="event-icon"
        />
        <div class="flex-1 text-center">
          <div class="text-2xl font-bold">{{ currentEvent.line1 || '' }}</div>
          <div class="text-xl mt-2">{{ currentEvent.line2 || '' }}</div>
        </div>
        <img
          v-if="currentEvent.playerPhoto"
          :src="playerPhoto(currentEvent.playerPhoto)"
          alt="Player"
          class="player-photo"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useGameStore } from '~/stores/gameStore'
import { useFormat } from '~/composables/useFormat'
import { useWebSocket } from '~/composables/useWebSocket'
import { useGame } from '~/composables/useGame'

const props = defineProps({
  zone: {
    type: String,
    required: true,
    default: 'inter'
  },
  mode: {
    type: String,
    required: true,
    default: 'full'
  },
  gameData: {
    type: Object,
    default: null
  }
})

const gameStore = useGameStore()
const { teamName, logo48, playerPhoto, eventIcon, formatPeriod, msToMMSS } = useFormat()
const { startGameRotation, stopGameRotation } = useGame()
const { connectStomp, disconnect } = useWebSocket()

const matchHorloge = ref('00:00')
const matchHorlogeStarted = ref(false)
const matchPeriode = ref('M1')
const matchPossession = ref(0)
const pen1 = ref(0)
const pen2 = ref(0)

const styleZone = computed(() => {
  return props.zone === 'club' ? 'ban_score_club' : 'ban_score'
})

const score = computed(() => gameStore.score)
const teams = computed(() => gameStore.teams)
const currentGame = computed(() => gameStore.currentGame)
const currentEvent = computed(() => gameStore.currentEvent)

// Update local refs from store
const updateFromStore = () => {
  matchHorloge.value = score.value.timer || '00:00'
  matchPeriode.value = formatPeriod(score.value.period || 'M1')
  matchPossession.value = score.value.possession || 0
  pen1.value = score.value.penaltiesA?.length || 0
  pen2.value = score.value.penaltiesB?.length || 0
  matchHorlogeStarted.value = matchHorloge.value !== '00:00'
}

// Watch store changes
watch(() => score.value, () => {
  updateFromStore()
}, { deep: true })

onMounted(async () => {
  if (props.gameData && props.gameData.id_match) {
    // Load game data
    await gameStore.loadGame(props.gameData)

    // Start periodic score updates
    startGameRotation(props.gameData.id_match)

    // Initialize WebSocket connection
    // (Optional - can be enabled if network config is available)
    // const { getNetworkConfig } = useApi()
    // try {
    //   const networkConfig = await getNetworkConfig(props.gameData.d_id)
    //   if (networkConfig && networkConfig.url) {
    //     connectStomp({
    //       url: networkConfig.url,
    //       login: networkConfig.login,
    //       password: networkConfig.password,
    //       topics: ['/game/chrono', '/game/period', '/game/data-game', '/game/player-info']
    //     })
    //   }
    // } catch (err) {
    //   console.error('Network config not available:', err)
    // }

    updateFromStore()
  }
})

onUnmounted(() => {
  stopGameRotation()
  disconnect()
})
</script>

<style scoped>
.static {
  animation: none !important;
}

/* Additional styles are in assets/css/app.css */
</style>
