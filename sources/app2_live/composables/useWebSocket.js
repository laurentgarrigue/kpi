import { ref } from 'vue'
import { Client } from '@stomp/stompjs'
import { useGameStore } from '~/stores/gameStore'

/**
 * WebSocket/STOMP composable
 * Handles real-time communication via WebSocket and STOMP protocol
 */
export const useWebSocket = () => {
  const gameStore = useGameStore()
  const { formatPeriod } = useFormat()

  const client = ref(null)
  const connected = ref(false)
  const connecting = ref(false)
  const error = ref(null)

  /**
   * Process incoming WebSocket message
   * @param {Object} message - Message object
   */
  const processMessage = (message) => {
    try {
      const data = typeof message === 'string' ? JSON.parse(message) : message

      // Update score based on message type
      if (data.type === 'chrono' || data.TPS-JEU !== undefined) {
        // Timer update
        gameStore.updateScore({
          timer: data['TPS-JEU'] || data.timer || gameStore.score.timer
        })
      }

      if (data.type === 'posses' || data.POSSES !== undefined) {
        // Possession timer
        gameStore.updateScore({
          possession: parseInt(data.POSSES || data.possession) || 0
        })
      }

      if (data.type === 'period' || data.period !== undefined) {
        // Period change
        gameStore.updateScore({
          period: formatPeriod(data.period)
        })
      }

      if (data.type === 'scoreA' || data.HOME !== undefined) {
        // Home team score
        gameStore.updateScore({
          scoreA: parseInt(data.HOME || data.scoreA) || 0
        })
      }

      if (data.type === 'scoreB' || data.GUEST !== undefined) {
        // Guest team score
        gameStore.updateScore({
          scoreB: parseInt(data.GUEST || data.scoreB) || 0
        })
      }

      if (data.type === 'penA' || data.penaltiesA !== undefined) {
        // Penalty shootout - team A
        gameStore.updateScore({
          penaltiesA: data.penaltiesA || []
        })
      }

      if (data.type === 'penB' || data.penaltiesB !== undefined) {
        // Penalty shootout - team B
        gameStore.updateScore({
          penaltiesB: data.penaltiesB || []
        })
      }

      if (data.type === 'evt' || data.event !== undefined) {
        // Game event (goal, card, etc.)
        const { displayGameEvent } = useGame()
        displayGameEvent(data.event || data)
      }

    } catch (err) {
      console.error('Error processing WebSocket message:', err)
    }
  }

  /**
   * Connect to WebSocket using STOMP protocol
   * @param {Object} config - Connection configuration
   * @param {string} config.url - WebSocket URL
   * @param {string} config.login - Login (optional)
   * @param {string} config.password - Password (optional)
   * @param {Array<string>} config.topics - STOMP topics to subscribe
   */
  const connectStomp = (config) => {
    if (connected.value || connecting.value) {
      console.warn('Already connected or connecting')
      return
    }

    connecting.value = true
    error.value = null

    try {
      // Create STOMP client
      client.value = new Client({
        brokerURL: config.url,
        connectHeaders: {
          login: config.login || '',
          passcode: config.password || ''
        },
        debug: (str) => {
          console.log('STOMP:', str)
        },
        reconnectDelay: 5000,
        heartbeatIncoming: 4000,
        heartbeatOutgoing: 4000,

        onConnect: (frame) => {
          console.log('STOMP connected:', frame)
          connected.value = true
          connecting.value = false

          // Subscribe to topics
          const topics = config.topics || [
            '/game/chrono',
            '/game/period',
            '/game/data-game',
            '/game/player-info'
          ]

          topics.forEach(topic => {
            client.value.subscribe(topic, (message) => {
              console.log(`Message from ${topic}:`, message.body)
              processMessage(message.body)
            })
          })

          // Send sync request
          if (client.value.connected) {
            client.value.publish({
              destination: '/game/sync',
              body: JSON.stringify({ action: 'sync' })
            })
          }
        },

        onStompError: (frame) => {
          console.error('STOMP error:', frame)
          error.value = frame.headers?.message || 'STOMP error'
          connected.value = false
          connecting.value = false
        },

        onWebSocketError: (evt) => {
          console.error('WebSocket error:', evt)
          error.value = 'WebSocket connection error'
          connected.value = false
          connecting.value = false
        },

        onDisconnect: () => {
          console.log('STOMP disconnected')
          connected.value = false
          connecting.value = false
        }
      })

      // Activate the client
      client.value.activate()

    } catch (err) {
      console.error('Error connecting STOMP:', err)
      error.value = err.message
      connecting.value = false
    }
  }

  /**
   * Disconnect from WebSocket/STOMP
   */
  const disconnect = async () => {
    if (client.value) {
      try {
        await client.value.deactivate()
        client.value = null
        connected.value = false
        connecting.value = false
      } catch (err) {
        console.error('Error disconnecting:', err)
      }
    }
  }

  /**
   * Send message via STOMP
   * @param {string} destination - STOMP destination/topic
   * @param {Object} body - Message body
   */
  const sendMessage = (destination, body) => {
    if (!connected.value || !client.value) {
      console.warn('Not connected to STOMP')
      return
    }

    try {
      client.value.publish({
        destination,
        body: JSON.stringify(body)
      })
    } catch (err) {
      console.error('Error sending message:', err)
    }
  }

  /**
   * Request sync from server
   */
  const requestSync = () => {
    sendMessage('/game/sync', { action: 'sync' })
  }

  /**
   * Send team rosters to external system
   * @param {Object} teams - Teams data
   */
  const setTeams = (teams) => {
    sendMessage('/game/set-teams', teams)
  }

  return {
    client,
    connected,
    connecting,
    error,
    connectStomp,
    disconnect,
    sendMessage,
    requestSync,
    setTeams,
    processMessage
  }
}
