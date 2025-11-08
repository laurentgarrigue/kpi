<template>
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ t('Faker.Title') }}</h1>

    <div class="bg-white rounded-lg shadow p-6">
      <p class="text-gray-600 mb-6">
        Générateur de données de test pour simuler des scénarios de match sans matériel réel.
      </p>

      <div class="space-y-4 mb-6">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Broker URL</label>
          <input
            v-model="brokerUrl"
            type="text"
            placeholder="ws://localhost:8080"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
          />
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Topic</label>
          <input
            v-model="topic"
            type="text"
            placeholder="/game/data-game"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
          />
        </div>
      </div>

      <div class="mb-6">
        <h3 class="font-semibold mb-3">{{ t('Faker.PresetMessages') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <button
            v-for="(preset, index) in presetMessages"
            :key="index"
            @click="sendPreset(preset)"
            :disabled="!connected"
            class="px-4 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition disabled:opacity-50 disabled:cursor-not-allowed text-left"
          >
            <div class="font-medium">{{ preset.name }}</div>
            <div class="text-xs opacity-75">{{ preset.description }}</div>
          </button>
        </div>
      </div>

      <div class="mb-6">
        <h3 class="font-semibold mb-3">{{ t('Faker.CustomMessage') }}</h3>
        <textarea
          v-model="customMessage"
          rows="5"
          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 font-mono text-sm"
          placeholder='{"type": "goal", "team": 1, "player": 5}'
        ></textarea>
        <button
          @click="sendCustom"
          :disabled="!connected || !customMessage"
          class="mt-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ t('Faker.SendMessage') }}
        </button>
      </div>

      <div class="flex items-center gap-4">
        <button
          v-if="!connected"
          @click="connect"
          class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-semibold"
        >
          {{ t('Manager.Connect') }}
        </button>
        <button
          v-else
          @click="disconnect"
          class="px-6 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition font-semibold"
        >
          {{ t('Manager.Disconnect') }}
        </button>

        <div
          v-if="connected"
          class="flex items-center text-green-600"
        >
          <div class="h-3 w-3 bg-green-500 rounded-full mr-2 animate-pulse"></div>
          <span class="font-medium">{{ t('Manager.Connected') }}</span>
        </div>
      </div>

      <div v-if="messages.length > 0" class="mt-6">
        <h3 class="font-semibold mb-3">Sent Messages ({{ messages.length }})</h3>
        <div class="bg-gray-900 text-green-400 rounded-lg p-4 max-h-64 overflow-y-auto message-log">
          <div v-for="(msg, index) in messages" :key="index" class="mb-2">
            <span class="text-gray-500">[{{ formatTime(msg.timestamp) }}]</span> {{ msg.data }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import dayjs from 'dayjs'

const { t } = useI18n()
const { createConnection, connect: wsConnect, disconnect: wsDisconnect, send } = useWebSocket()

const brokerUrl = ref('ws://localhost:8080')
const topic = ref('/game/data-game')
const connected = ref(false)
const customMessage = ref('')
const messages = ref([])
const connectionId = 'faker'

const presetMessages = [
  {
    name: 'Game Start',
    description: 'Démarrer le match',
    data: { type: 'game-state', state: 'started', period: 1 },
  },
  {
    name: 'Goal Team 1',
    description: 'But équipe 1',
    data: { type: 'goal', team: 1, player: 5, time: 300000 },
  },
  {
    name: 'Goal Team 2',
    description: 'But équipe 2',
    data: { type: 'goal', team: 2, player: 3, time: 450000 },
  },
  {
    name: 'Yellow Card',
    description: 'Carton jaune',
    data: { type: 'card', card: 'yellow', team: 1, player: 7 },
  },
  {
    name: 'Period End',
    description: 'Fin de période',
    data: { type: 'period', period: 1, status: 'ended' },
  },
  {
    name: 'Chrono Update',
    description: 'Mise à jour chrono',
    data: { type: 'chrono', time: 600000, running: true },
  },
]

function connect() {
  createConnection({
    id: connectionId,
    url: brokerUrl.value,
    type: 'stomp',
    topics: [topic.value],
    onConnect: () => {
      connected.value = true
    },
    onDisconnect: () => {
      connected.value = false
    },
  })

  wsConnect(connectionId)
}

function disconnect() {
  wsDisconnect(connectionId)
  connected.value = false
}

function sendPreset(preset) {
  const msg = {
    timestamp: new Date(),
    data: JSON.stringify(preset.data),
  }
  messages.value.unshift(msg)
  send(connectionId, topic.value, preset.data)
}

function sendCustom() {
  try {
    const parsed = JSON.parse(customMessage.value)
    const msg = {
      timestamp: new Date(),
      data: customMessage.value,
    }
    messages.value.unshift(msg)
    send(connectionId, topic.value, parsed)
    customMessage.value = ''
  } catch (error) {
    alert('Invalid JSON format')
  }
}

function formatTime(timestamp) {
  return dayjs(timestamp).format('HH:mm:ss')
}

definePageMeta({
  middleware: 'auth',
})
</script>
