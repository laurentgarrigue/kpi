import { ref, computed, onUnmounted } from 'vue'
import { Client } from '@stomp/stompjs'
import dayjs from 'dayjs'

/**
 * WebSocket/STOMP Connection Composable
 * Handles both raw WebSocket and STOMP protocol connections
 */
export function useWebSocket() {
  const connections = ref([])

  /**
   * Create a new WebSocket connection
   */
  function createConnection(config) {
    const {
      id,
      url,
      type = 'websocket', // 'websocket' or 'stomp'
      topics = [],
      onMessage,
      onConnect,
      onDisconnect,
      onError,
    } = config

    const connection = {
      id,
      url,
      type,
      topics,
      status: 'disconnected', // 'connecting', 'connected', 'disconnected'
      client: null,
      socket: null,
      messages: [],
      lastMessage: null,
      connectedAt: null,
      disconnectedAt: null,
    }

    if (type === 'stomp') {
      // STOMP connection
      connection.client = new Client({
        brokerURL: url,
        reconnectDelay: 5000,
        heartbeatIncoming: 4000,
        heartbeatOutgoing: 4000,

        onConnect: () => {
          connection.status = 'connected'
          connection.connectedAt = new Date()
          console.log(`STOMP connected: ${id}`)

          // Subscribe to topics
          topics.forEach(topic => {
            connection.client.subscribe(topic, message => {
              const parsed = JSON.parse(message.body)
              const msg = {
                topic,
                timestamp: new Date(),
                data: parsed,
                raw: message.body,
              }
              connection.messages.push(msg)
              connection.lastMessage = msg

              if (onMessage) {
                onMessage(msg)
              }
            })
          })

          if (onConnect) {
            onConnect(connection)
          }
        },

        onDisconnect: () => {
          connection.status = 'disconnected'
          connection.disconnectedAt = new Date()
          console.log(`STOMP disconnected: ${id}`)

          if (onDisconnect) {
            onDisconnect(connection)
          }
        },

        onStompError: frame => {
          console.error('STOMP error:', frame)
          connection.status = 'disconnected'

          if (onError) {
            onError(frame)
          }
        },

        onWebSocketError: event => {
          console.error('WebSocket error:', event)

          if (onError) {
            onError(event)
          }
        },
      })
    } else {
      // Raw WebSocket connection
      try {
        connection.socket = new WebSocket(url)

        connection.socket.onopen = () => {
          connection.status = 'connected'
          connection.connectedAt = new Date()
          console.log(`WebSocket connected: ${id}`)

          if (onConnect) {
            onConnect(connection)
          }
        }

        connection.socket.onmessage = event => {
          let parsed
          try {
            parsed = JSON.parse(event.data)
          } catch {
            parsed = event.data
          }

          const msg = {
            timestamp: new Date(),
            data: parsed,
            raw: event.data,
          }
          connection.messages.push(msg)
          connection.lastMessage = msg

          if (onMessage) {
            onMessage(msg)
          }
        }

        connection.socket.onclose = () => {
          connection.status = 'disconnected'
          connection.disconnectedAt = new Date()
          console.log(`WebSocket disconnected: ${id}`)

          if (onDisconnect) {
            onDisconnect(connection)
          }
        }

        connection.socket.onerror = error => {
          console.error('WebSocket error:', error)

          if (onError) {
            onError(error)
          }
        }
      } catch (error) {
        console.error('Failed to create WebSocket:', error)
        connection.status = 'disconnected'

        if (onError) {
          onError(error)
        }
      }
    }

    connections.value.push(connection)
    return connection
  }

  /**
   * Connect to WebSocket/STOMP
   */
  function connect(connectionId) {
    const connection = connections.value.find(c => c.id === connectionId)
    if (!connection) {
      console.error('Connection not found:', connectionId)
      return
    }

    if (connection.status === 'connected') {
      console.warn('Already connected:', connectionId)
      return
    }

    connection.status = 'connecting'

    if (connection.type === 'stomp') {
      connection.client.activate()
    }
    // Raw WebSocket connects automatically on creation
  }

  /**
   * Disconnect from WebSocket/STOMP
   */
  function disconnect(connectionId) {
    const connection = connections.value.find(c => c.id === connectionId)
    if (!connection) {
      console.error('Connection not found:', connectionId)
      return
    }

    if (connection.type === 'stomp' && connection.client) {
      connection.client.deactivate()
    } else if (connection.socket) {
      connection.socket.close()
    }

    connection.status = 'disconnected'
    connection.disconnectedAt = new Date()
  }

  /**
   * Send message through WebSocket/STOMP
   */
  function send(connectionId, destination, message) {
    const connection = connections.value.find(c => c.id === connectionId)
    if (!connection) {
      console.error('Connection not found:', connectionId)
      return
    }

    if (connection.status !== 'connected') {
      console.error('Connection not connected:', connectionId)
      return
    }

    if (connection.type === 'stomp' && connection.client) {
      connection.client.publish({
        destination,
        body: JSON.stringify(message),
      })
    } else if (connection.socket) {
      connection.socket.send(JSON.stringify(message))
    }
  }

  /**
   * Clear messages for a connection
   */
  function clearMessages(connectionId) {
    const connection = connections.value.find(c => c.id === connectionId)
    if (connection) {
      connection.messages = []
      connection.lastMessage = null
    }
  }

  /**
   * Remove a connection
   */
  function removeConnection(connectionId) {
    const index = connections.value.findIndex(c => c.id === connectionId)
    if (index !== -1) {
      disconnect(connectionId)
      connections.value.splice(index, 1)
    }
  }

  /**
   * Get connection by ID
   */
  function getConnection(connectionId) {
    return connections.value.find(c => c.id === connectionId)
  }

  /**
   * Format timer (milliseconds to MM:SS.D)
   */
  function formatTimer(milliseconds) {
    if (!milliseconds) return '00:00.0'

    const totalSeconds = Math.floor(milliseconds / 1000)
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60
    const deciseconds = Math.floor((milliseconds % 1000) / 100)

    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}.${deciseconds}`
  }

  /**
   * Cleanup all connections on unmount
   */
  onUnmounted(() => {
    connections.value.forEach(connection => {
      disconnect(connection.id)
    })
  })

  return {
    connections: computed(() => connections.value),
    createConnection,
    connect,
    disconnect,
    send,
    clearMessages,
    removeConnection,
    getConnection,
    formatTimer,
  }
}
