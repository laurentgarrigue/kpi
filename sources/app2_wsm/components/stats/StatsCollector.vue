<template>
  <div class="space-y-4">
    <div v-if="gameData" class="bg-blue-50 border border-blue-200 rounded p-4">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <div class="text-sm text-gray-600">{{ t('Stats.Period') }}</div>
          <div class="text-2xl font-bold">{{ gameData.period || 1 }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-600">{{ t('Stats.Timer') }}</div>
          <div class="text-2xl font-bold timer-display">{{ formattedTime }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-600">{{ t('Team') }} 1</div>
          <div class="text-2xl font-bold">{{ gameData.score1 || 0 }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-600">{{ t('Team') }} 2</div>
          <div class="text-2xl font-bold">{{ gameData.score2 || 0 }}</div>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
      <button
        v-for="action in actions"
        :key="action.key"
        @click="recordAction(action)"
        class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
      >
        {{ t(action.key) }}
      </button>
    </div>

    <div v-if="recordedActions.length > 0" class="bg-white border rounded p-4">
      <h4 class="font-semibold mb-3">{{ t('Stats.Actions') }} ({{ recordedActions.length }})</h4>
      <div class="space-y-2 max-h-48 overflow-y-auto">
        <div
          v-for="(action, index) in recordedActions"
          :key="index"
          class="flex items-center justify-between text-sm bg-gray-50 px-3 py-2 rounded"
        >
          <span>{{ formatTimestamp(action.timestamp) }}</span>
          <span class="font-medium">{{ t(action.type) }}</span>
        </div>
      </div>

      <button
        @click="submitStats"
        :disabled="submitting"
        class="mt-4 w-full px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50"
      >
        {{ submitting ? 'Envoi...' : t('Stats.Submit') }}
      </button>
    </div>
  </div>
</template>

<script setup>
import dayjs from 'dayjs'

const props = defineProps({
  eventId: {
    type: [Number, String],
    required: true,
  },
  pitch: {
    type: Number,
    required: true,
  },
})

const { t } = useI18n()
const { getApi, postApi } = useApi()
const { formatTimer } = useWebSocket()

const gameData = ref(null)
const recordedActions = ref([])
const submitting = ref(false)

const actions = [
  { key: 'Pass', type: 'pass' },
  { key: 'Shot', type: 'shot' },
  { key: 'Stop', type: 'stop' },
  { key: 'Neutral', type: 'neutral' },
  { key: 'Kickoff', type: 'kickoff' },
  { key: 'Kickoff_won', type: 'kickoff_won' },
  { key: 'Kickoff_lost', type: 'kickoff_lost' },
  { key: 'Shot_on', type: 'shot_on' },
  { key: 'Shot_off', type: 'shot_off' },
  { key: 'Shot_stop', type: 'shot_stop' },
]

const formattedTime = computed(() => {
  if (!gameData.value || !gameData.value.time) return '00:00.0'
  return formatTimer(gameData.value.time)
})

onMounted(async () => {
  await loadGameData()
})

async function loadGameData() {
  try {
    const data = await getApi(`/live/cache/event${props.eventId}_pitch${props.pitch}.json`)
    if (data) {
      gameData.value = data
    }
  } catch (error) {
    console.error('Failed to load game data:', error)
  }
}

function recordAction(action) {
  recordedActions.value.unshift({
    type: action.key,
    actionType: action.type,
    timestamp: new Date(),
    eventId: props.eventId,
    pitch: props.pitch,
  })
}

async function submitStats() {
  if (recordedActions.value.length === 0) return

  submitting.value = true

  try {
    await postApi('/api/wsm/stats', {
      eventId: props.eventId,
      pitch: props.pitch,
      actions: recordedActions.value,
    })

    // Clear actions after successful submission
    recordedActions.value = []

    alert('Statistiques envoyées avec succès!')
  } catch (error) {
    console.error('Failed to submit stats:', error)
    alert('Erreur lors de l\'envoi des statistiques')
  } finally {
    submitting.value = false
  }
}

function formatTimestamp(timestamp) {
  return dayjs(timestamp).format('HH:mm:ss')
}
</script>
