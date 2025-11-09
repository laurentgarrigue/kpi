export const useApi = () => {
  const runtimeConfig = useRuntimeConfig()
  const apiBaseUrl = runtimeConfig.public.apiBaseUrl
  const backendBaseUrl = runtimeConfig.public.backendBaseUrl

  /**
   * Fetch wrapper with error handling
   */
  const fetchApi = async (url, options = {}) => {
    try {
      // Add timestamp to URL to prevent caching
      const separator = url.includes('?') ? '&' : '?'
      const urlWithTimestamp = `${url}${separator}_t=${Date.now()}`

      const response = await fetch(urlWithTimestamp, {
        headers: {
          'Accept': 'application/json',
          'Cache-Control': 'no-cache, no-store, must-revalidate',
          'Pragma': 'no-cache',
          'Expires': '0',
          ...options.headers
        },
        cache: 'no-store',
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
  const getGameIdForPitch = async (eventId, pitch) => {
    try {
      // Try to fetch from cache first
      return await fetchApi(`${backendBaseUrl}/live/cache/event${eventId}_pitch${pitch}.json`)
    } catch (error) {
      // If cache doesn't exist (404), try to generate it
      if (error.message.includes('404')) {
        console.log(`Pitch cache not found for event ${eventId} pitch ${pitch}, trying to generate it...`)
        try {
          // Call ajax_cache_pitch.php to generate the cache
          const genResponse = await fetch(`${backendBaseUrl}/live/ajax_cache_pitch.php?event=${eventId}&pitch=${pitch}`)
          const genResult = await genResponse.text()
          console.log('Pitch cache generation result:', genResult)

          // Wait a bit for the file to be generated
          await new Promise(resolve => setTimeout(resolve, 1000))

          // Retry fetching from cache
          return await fetchApi(`${backendBaseUrl}/live/cache/event${eventId}_pitch${pitch}.json`)
        } catch (genError) {
          console.error('Failed to generate pitch cache:', genError)
          throw error // Throw original error
        }
      }
      throw error
    }
  }

  /**
   * Get full game data
   * @param {string} gameId - Game ID
   */
  const getGameData = async (gameId) => {
    try {
      // Try to fetch from cache first
      return await fetchApi(`${backendBaseUrl}/live/cache/${gameId}_match_global.json`)
    } catch (error) {
      // If cache doesn't exist (404), try to generate it
      if (error.message.includes('404')) {
        console.log(`Cache not found for game ${gameId}, trying to generate it...`)
        try {
          // Call force_cache_match.php to generate the cache
          const genResponse = await fetch(`${backendBaseUrl}/live/force_cache_match.php?match=${gameId}`)
          const genResult = await genResponse.text()
          console.log('Cache generation result:', genResult)

          // Wait a bit for the file to be generated
          await new Promise(resolve => setTimeout(resolve, 1000))

          // Retry fetching from cache
          return await fetchApi(`${backendBaseUrl}/live/cache/${gameId}_match_global.json`)
        } catch (genError) {
          console.error('Failed to generate cache:', genError)
          throw error // Throw original error
        }
      }
      throw error
    }
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
