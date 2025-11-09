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
          <div v-if="teams.teamA.logo" class="team-logo" v-html="logo48(teams.teamA.logo)" />
          <div class="team-name text-right">{{ teamName(teams.teamA.name, zone) }}</div>
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
            {{ formatPossession(matchPossession) }}s
          </div>
        </div>

        <!-- Team B -->
        <div class="col-span-3 flex items-center justify-start space-x-4">
          <div v-if="pen2 > 0" class="flex space-x-1">
            <div v-for="i in pen2" :key="`pen2-${i}`" class="penalty-indicator scored" />
          </div>
          <div class="team-name text-left">{{ teamName(teams.teamB.name, zone) }}</div>
          <div v-if="teams.teamB.logo" class="team-logo" v-html="logo48(teams.teamB.logo)" />
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
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
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
const { teamName, logo48, playerPhoto, eventIcon, formatPeriod, formatPossession, msToMMSS } = useFormat()

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

onMounted(() => {
  console.log('Main.vue mounted with gameData:', props.gameData)
  updateFromStore()
})

onUnmounted(() => {
  // Cleanup handled by parent page
})
</script>

<style scoped>
.static {
  animation: none !important;
}

/* Team names */
.team-name {
  font-size: 2rem;
  font-weight: 700;
  text-transform: uppercase;
}

/* Score display */
.score-display {
  font-family: '7segments', 'Courier New', monospace;
  font-size: 5rem;
  font-weight: 700;
  line-height: 1;
  color: #00ff00;
  text-shadow: 0 0 20px rgba(0, 255, 0, 0.8);
}

/* Timer display */
.timer-display {
  font-family: 'LiquidCrystal', 'Courier New', monospace;
  font-size: 2.5rem;
  font-weight: 700;
  color: #00ff00;
  text-shadow: 0 0 15px rgba(0, 255, 0, 0.6);
}

.timer-red {
  color: #ff0000 !important;
  text-shadow: 0 0 15px rgba(255, 0, 0, 0.6) !important;
}

/* Period badge */
.period-badge {
  font-size: 1.5rem;
  font-weight: 600;
  padding: 0.25rem 0.75rem;
  background-color: rgba(59, 130, 246, 0.3);
  border-radius: 0.375rem;
  border: 2px solid rgb(59, 130, 246);
}

/* Penalty indicators */
.penalty-indicator {
  width: 1.5rem;
  height: 1.5rem;
  border-radius: 50%;
  background-color: rgb(59, 130, 246);
  box-shadow: 0 0 10px rgba(59, 130, 246, 0.8);
}

/* Category badge */
.category-badge {
  font-size: 2rem;
  font-weight: 700;
  text-transform: uppercase;
  padding: 0.5rem 2rem;
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.3), rgba(147, 51, 234, 0.3));
  border-radius: 0.5rem;
  border: 2px solid rgb(59, 130, 246);
}

/* Event banner */
.event-banner {
  position: fixed;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  background: linear-gradient(135deg, rgba(0, 0, 0, 0.9), rgba(31, 41, 55, 0.9));
  padding: 1.5rem 3rem;
  border-radius: 1rem;
  border: 3px solid #fbbf24;
  box-shadow: 0 10px 40px rgba(251, 191, 36, 0.5);
  max-width: 80%;
}

.event-icon {
  width: 4rem;
  height: 4rem;
}

.player-photo {
  width: 4rem;
  height: 4rem;
  border-radius: 50%;
  border: 2px solid #fbbf24;
}

/* Team logo */
.team-logo {
  width: 3rem;
  height: 3rem;
}

/* Additional styles are in assets/css/app.css */
</style>
