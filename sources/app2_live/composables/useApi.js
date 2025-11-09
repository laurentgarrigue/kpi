export const useApi = () => {
  const runtimeConfig = useRuntimeConfig()
  const apiBaseUrl = runtimeConfig.public.apiBaseUrl
  const backendBaseUrl = runtimeConfig.public.backendBaseUrl

  /**
   * Fetch wrapper with error handling
   */
  const fetchApi = async (url, options = {}) => {
    try {
      const response = await fetch(url, {
        headers: {
          'Accept': 'application/json',
          'Cache-Control': 'no-cache',
          ...options.headers
        },
        ...options
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      return await response.json()
    } catch (error) {
      console.error('API fetch error:', error)
      throw error
    }
  }

  /**
   * Get events list
   * @param {string} mode - 'std' or 'local'
   */
  const getEvents = (mode = 'std') => {
    return fetchApi(`${apiBaseUrl}/events/${mode}`)
  }

  /**
   * Get games for an event
   * @param {number} eventId - Event ID
   */
  const getGames = (eventId) => {
    return fetchApi(`${apiBaseUrl}/games/${eventId}`)
  }

  /**
   * Get network configuration for WebSocket/STOMP
   * @param {number} eventId - Event ID
   */
  const getNetworkConfig = (eventId) => {
    return fetchApi(`${backendBaseUrl}/live/cache/event${eventId}_network.json`)
  }

  /**
   * Get game ID for a specific pitch
   * @param {number} eventId - Event ID
   * @param {number} pitch - Pitch number
   */
  const getGameIdForPitch = (eventId, pitch) => {
    return fetchApi(`${backendBaseUrl}/live/cache/event${eventId}_pitch${pitch}.json`)
  }

  /**
   * Get full game data
   * @param {string} gameId - Game ID
   */
  const getGameData = (gameId) => {
    return fetchApi(`${backendBaseUrl}/live/cache/${gameId}_match_global.json`)
  }

  /**
   * Get live score data
   * @param {string} gameId - Game ID
   */
  const getScoreData = (gameId) => {
    return fetchApi(`${backendBaseUrl}/live/cache/${gameId}_match_score.json`)
  }

  /**
   * Get timer/chrono data
   * @param {string} gameId - Game ID
   */
  const getTimerData = (gameId) => {
    return fetchApi(`${backendBaseUrl}/live/cache/${gameId}_match_chrono.json`)
  }

  return {
    fetchApi,
    getEvents,
    getGames,
    getNetworkConfig,
    getGameIdForPitch,
    getGameData,
    getScoreData,
    getTimerData
  }
}
