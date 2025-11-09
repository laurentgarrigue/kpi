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
  const forcedGameId = ref(null)

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
      const data = await getGameData(gameId)
      await gameStore.loadGame(data)
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

      // Update score in store
      gameStore.updateScore({
        scoreA: parseInt(data.scoreA) || 0,
        scoreB: parseInt(data.scoreB) || 0,
        period: data.period || gameStore.score.period,
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

      // Update timer in store
      gameStore.updateScore({
        timer: data.timer || '00:00',
        possession: parseInt(data.possession) || 0,
        period: data.period || gameStore.score.period
      })
    } catch (err) {
      console.error('Error fetching timer:', err)
    }
  }

  /**
   * Set up periodic game updates
   * @param {string} gameId - Game ID
   * @param {number} interval - Update interval in ms (default: 5000)
   */
  const startGameRotation = (gameId, interval = 5000) => {
    if (intervalGame.value) {
      clearInterval(intervalGame.value)
    }

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
    gameStore.resetGame()
    error.value = null
  }

  return {
    loading,
    error,
    forcedGameId,
    fetchGameIdForPitch,
    fetchGame,
    fetchScore,
    fetchTimer,
    startGameRotation,
    stopGameRotation,
    displayGameEvent,
    testGameId,
    cleanup
  }
}
