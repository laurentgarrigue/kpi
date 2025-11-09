import { ref } from 'vue'
import { useGameStore } from '~/stores/gameStore'
import { useEventStore } from '~/stores/eventStore'

/**
 * Game management composable
 * Handles game data fetching, rotation, and updates
 */
export const useGame = () => {
  const gameStore = useGameStore()
  const eventStore = useEventStore()
  const { getGameIdForPitch, getGameData, getScoreData, getTimerData } = useApi()
  const { formatPeriod } = useFormat()

  const loading = ref(false)
  const error = ref(null)
  const intervalGame = ref(null)
  const intervalPitch = ref(null)
  const forcedGameId = ref(null)
  const currentGameId = ref(null)
  const currentEventId = ref(null)
  const currentPitch = ref(null)
  const useWebSocketForUpdates = ref(false) // If true, don't use HTTP polling

  /**
   * Fetch game ID for a pitch
   * @param {number} eventId - Event ID
   * @param {number} pitch - Pitch number
   * @returns {Promise<string|null>} Game ID
   */
  const fetchGameIdForPitch = async (eventId, pitch) => {
    try {
      const data = await getGameIdForPitch(eventId, pitch)
      // Support multiple field names: id_match (backend), gameId, g_id
      return data?.id_match || data?.gameId || data?.g_id || null
    } catch (err) {
      console.error('Error fetching game ID:', err)
      return null
    }
  }

  /**
   * Fetch full game data
   * @param {string} gameId - Game ID
   */
  const fetchGame = async (gameId) => {
    if (!gameId) return

    loading.value = true
    error.value = null

    try {
      // Load global game data
      const data = await getGameData(gameId)
      // Pass gameId explicitly to handle cases where it's not in the data
      await gameStore.loadGame(data, gameId)

      // Load initial score and chrono data (important if match already started)
      // If WebSocket is active, these will be updated via WebSocket
      // But we need initial state in case no changes occur
      await fetchScore(gameId)
      await fetchTimer(gameId)

    } catch (err) {
      console.error('Error fetching game:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch live score data
   * @param {string} gameId - Game ID
   */
  const fetchScore = async (gameId) => {
    if (!gameId) return

    try {
      const data = await getScoreData(gameId)

      // Update score in store (backend uses score1/score2, periode)
      gameStore.updateScore({
        scoreA: parseInt(data.score1) || 0,
        scoreB: parseInt(data.score2) || 0,
        period: data.periode || gameStore.score.period,
        penaltiesA: data.penaltiesA || [],
        penaltiesB: data.penaltiesB || []
      })
    } catch (err) {
      console.error('Error fetching score:', err)
    }
  }

  /**
   * Fetch timer/chrono data
   * @param {string} gameId - Game ID
   */
  const fetchTimer = async (gameId) => {
    if (!gameId) return

    try {
      const data = await getTimerData(gameId)

      // Convert run_time (milliseconds) to MM:SS format
      let timer = '00:00'
      if (data.run_time) {
        const totalSeconds = Math.floor(parseInt(data.run_time) / 1000)
        const minutes = Math.floor(totalSeconds / 60)
        const seconds = totalSeconds % 60
        timer = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
      }

      // Update timer in store
      gameStore.updateScore({
        timer: timer,
        possession: parseFloat(data.possession) || 0,
        period: data.period || gameStore.score.period
      })
    } catch (err) {
      console.error('Error fetching timer:', err)
    }
  }

  /**
   * Check pitch file periodically for match changes
   * @param {number} eventId - Event ID
   * @param {number} pitch - Pitch number
   */
  const checkPitchForChanges = async (eventId, pitch) => {
    try {
      const gameId = await fetchGameIdForPitch(eventId, pitch)

      if (gameId && gameId !== currentGameId.value) {
        console.log(`Match changed from ${currentGameId.value} to ${gameId}`)
        currentGameId.value = gameId

        // Reload all match data
        await fetchGame(gameId)

        // If not using WebSocket, restart score/timer polling
        if (!useWebSocketForUpdates.value) {
          startGameRotation(gameId)
        }
      }
    } catch (err) {
      console.error('Error checking pitch for changes:', err)
    }
  }

  /**
   * Start periodic pitch check (every 30 seconds)
   * @param {number} eventId - Event ID
   * @param {number} pitch - Pitch number
   */
  const startPitchCheck = (eventId, pitch) => {
    if (intervalPitch.value) {
      clearInterval(intervalPitch.value)
    }

    currentEventId.value = eventId
    currentPitch.value = pitch

    // Check every 30 seconds
    intervalPitch.value = setInterval(() => {
      checkPitchForChanges(eventId, pitch)
    }, 30000)
  }

  /**
   * Stop periodic pitch check
   */
  const stopPitchCheck = () => {
    if (intervalPitch.value) {
      clearInterval(intervalPitch.value)
      intervalPitch.value = null
    }
  }

  /**
   * Set up periodic game updates (only if not using WebSocket)
   * @param {string} gameId - Game ID
   * @param {number} interval - Update interval in ms (default: 5000)
   */
  const startGameRotation = (gameId, interval = 5000) => {
    // Don't start HTTP polling if WebSocket is active
    if (useWebSocketForUpdates.value) {
      console.log('WebSocket active, skipping HTTP polling')
      return
    }

    if (intervalGame.value) {
      clearInterval(intervalGame.value)
    }

    currentGameId.value = gameId

    // Initial fetch
    fetchScore(gameId)
    fetchTimer(gameId)

    // Periodic updates
    intervalGame.value = setInterval(() => {
      fetchScore(gameId)
      fetchTimer(gameId)
    }, interval)
  }

  /**
   * Stop game rotation
   */
  const stopGameRotation = () => {
    if (intervalGame.value) {
      clearInterval(intervalGame.value)
      intervalGame.value = null
    }
  }

  /**
   * Enable WebSocket mode (disables HTTP polling)
   */
  const enableWebSocketMode = () => {
    useWebSocketForUpdates.value = true
    stopGameRotation() // Stop HTTP polling
    console.log('WebSocket mode enabled, HTTP polling disabled')
  }

  /**
   * Disable WebSocket mode (enables HTTP polling fallback)
   */
  const disableWebSocketMode = () => {
    useWebSocketForUpdates.value = false
    // Restart HTTP polling if we have a current game
    if (currentGameId.value) {
      startGameRotation(currentGameId.value)
    }
    console.log('WebSocket mode disabled, falling back to HTTP polling')
  }

  /**
   * Display game event (goal, card, etc.)
   * @param {Object} event - Event object
   */
  const displayGameEvent = (event) => {
    gameStore.setCurrentEvent(event)

    // Auto-hide event after duration
    const duration = parseInt(import.meta.env.VUE_APP_INTERVAL_GAMEEVENTSHOW) || 8000
    setTimeout(() => {
      gameStore.clearCurrentEvent()
    }, duration)
  }

  /**
   * Validate game ID format
   * @param {string} id - Game ID to validate
   * @returns {boolean} True if valid
   */
  const testGameId = (id) => {
    if (!id) return false
    // Game ID format: eventId_categoryCode_gameNumber (e.g., "123_SM_001")
    const regex = /^\d+_[A-Z]{1,3}_\d{3}$/
    return regex.test(id)
  }

  /**
   * Clean up and reset
   */
  const cleanup = () => {
    stopGameRotation()
    stopPitchCheck()
    gameStore.resetGame()
    error.value = null
  }

  return {
    loading,
    error,
    forcedGameId,
    currentGameId,
    currentEventId,
    currentPitch,
    useWebSocketForUpdates,
    fetchGameIdForPitch,
    fetchGame,
    fetchScore,
    fetchTimer,
    checkPitchForChanges,
    startPitchCheck,
    stopPitchCheck,
    startGameRotation,
    stopGameRotation,
    enableWebSocketMode,
    disableWebSocketMode,
    displayGameEvent,
    testGameId,
    cleanup
  }
}
