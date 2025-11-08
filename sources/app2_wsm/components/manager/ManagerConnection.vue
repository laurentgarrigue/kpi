<template>
  <div>
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-xl font-semibold">{{ t('Pitch') }} {{ pitch }}</h3>
      <div class="flex items-center gap-2">
        <div
          :class="{
            'ws-connected': status === 'connected',
            'ws-connecting': status === 'connecting',
            'ws-disconnected': status === 'disconnected',
          }"
          class="flex items-center"
        >
          <div
            class="h-3 w-3 rounded-full mr-2"
            :class="{
              'bg-green-500 animate-pulse': status === 'connected',
              'bg-yellow-500 animate-pulse': status === 'connecting',
              'bg-red-500': status === 'disconnected',
            }"
          ></div>
          <span class="font-medium">{{ statusText }}</span>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('Manager.ConnectionUrl') }}</label>
        <input
          v-model="connectionUrl"
          type="text"
          :disabled="status === 'connected'"
          placeholder="ws://localhost:8080"
          class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-green-500 disabled:bg-gray-100"
        />
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">{{ t('Manager.ConnectionType') }}</label>
        <select
          v-model="connectionType"
          :disabled="status === 'connected'"
          class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-green-500 disabled:bg-gray-100"
        >
          <option value="websocket">{{ t('Manager.RawWebSocket') }}</option>
          <option value="stomp">{{ t('Manager.STOMP') }}</option>
        </select>
      </div>
    </div>

    <div class="flex gap-2 mb-4">
      <button
        v-if="status !== 'connected'"
        @click="handleConnect"
        :disabled="!connectionUrl"
        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
      >
        {{ t('Manager.Connect') }}
      </button>
      <button
        v-else
        @click="handleDisconnect"
        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition"
      >
        {{ t('Manager.Disconnect') }}
      </button>

      <button
        @click="handleClearMessages"
        class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700 transition"
      >
        {{ t('Manager.ClearLog') }}
      </button>
    </div>

    <div v-if="messages.length > 0" class="bg-gray-900 text-green-400 rounded p-4 max-h-96 overflow-y-auto message-log">
      <div class="text-xs mb-2 text-gray-500">
        {{ t('Manager.MessageLog') }} ({{ messages.length }})
      </div>
      <div v-for="(msg, index) in messages.slice(0, 100)" :key="index" class="text-xs mb-1">
        <span class="text-gray-600">[{{ formatTimestamp(msg.timestamp) }}]</span>
        <span v-if="msg.topic" class="text-blue-400">[{{ msg.topic }}]</span>
        {{ msg.raw || JSON.stringify(msg.data) }}
      </div>
    </div>
    <div v-else class="text-center py-8 text-gray-500 text-sm">
      {{ t('Manager.NoMessages') }}
    </div>
  </div>
</template>

<script setup>
import dayjs from 'dayjs'
import db from '~/utils/db'

const props = defineProps({
  pitch: {
    type: Number,
    required: true,
  },
  eventId: {
    type: [Number, String],
    default: null,
  },
  syncToDatabase: {
    type: Boolean,
    default: false,
  },
})

const { t } = useI18n()
const { createConnection, connect, disconnect, clearMessages: clearWsMessages, getConnection } = useWebSocket()

const connectionId = computed(() => `pitch-${props.pitch}`)
const connectionUrl = ref('')
const connectionType = ref('stomp')
const status = ref('disconnected')
const messages = ref([])

const statusText = computed(() => {
  switch (status.value) {
    case 'connected':
      return t('Manager.Connected')
    case 'connecting':
      return t('Manager.Connecting')
    default:
      return t('Manager.Disconnected')
  }
})

// STOMP topics to subscribe to
const stompTopics = [
  '/game/ready-to-start-game',
  '/game/set-teams',
  '/game/game-state',
  '/game/period',
  '/game/chrono',
  '/game/data-game',
  '/game/player-info',
  '/game/team-game',
  '/game/game-phase',
]

onMounted(async () => {
  // Load saved connection from IndexedDB
  try {
    const saved = await db.connections
      .where('pitch')
      .equals(props.pitch)
      .and(conn => !props.eventId || conn.eventId === props.eventId)
      .first()

    if (saved) {
      connectionUrl.value = saved.url || ''
      connectionType.value = saved.type || 'stomp'
    }
  } catch (error) {
    console.error('Failed to load connection:', error)
  }
})

function handleConnect() {
  if (!connectionUrl.value) return

  status.value = 'connecting'

  createConnection({
    id: connectionId.value,
    url: connectionUrl.value,
    type: connectionType.value,
    topics: connectionType.value === 'stomp' ? stompTopics : [],
    onMessage: msg => {
      messages.value.unshift(msg)

      // Sync to database if enabled
      if (props.syncToDatabase) {
        syncMessageToDatabase(msg)
      }
    },
    onConnect: () => {
      status.value = 'connected'
      saveConnection()
    },
    onDisconnect: () => {
      status.value = 'disconnected'
    },
    onError: error => {
      console.error('Connection error:', error)
      status.value = 'disconnected'
    },
  })

  connect(connectionId.value)
}

function handleDisconnect() {
  disconnect(connectionId.value)
  status.value = 'disconnected'
}

function handleClearMessages() {
  messages.value = []
  clearWsMessages(connectionId.value)
}

function formatTimestamp(timestamp) {
  return dayjs(timestamp).format('HH:mm:ss.SSS')
}

async function saveConnection() {
  try {
    await db.connections.put({
      pitch: props.pitch,
      eventId: props.eventId,
      url: connectionUrl.value,
      type: connectionType.value,
      timestamp: new Date(),
    })
  } catch (error) {
    console.error('Failed to save connection:', error)
  }
}

async function syncMessageToDatabase(msg) {
  try {
    // Save message to IndexedDB
    await db.messages.add({
      connectionId: connectionId.value,
      pitch: props.pitch,
      eventId: props.eventId,
      timestamp: msg.timestamp,
      topic: msg.topic,
      data: msg.data,
      raw: msg.raw,
    })

    // TODO: Send to backend API if needed
    // const { postApi } = useApi()
    // await postApi('/api/wsm/message', msg)
  } catch (error) {
    console.error('Failed to sync message:', error)
  }
}
</script>
