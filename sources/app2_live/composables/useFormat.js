/**
 * Formatting utilities composable
 */
export const useFormat = () => {
  const runtimeConfig = useRuntimeConfig()
  const backendBaseUrl = runtimeConfig.public.backendBaseUrl

  /**
   * Format period code to display string
   * @param {string} period - Period code (M1, M2, OVT, PEN, etc.)
   * @returns {string} Formatted period string
   */
  const formatPeriod = (period) => {
    const periodMap = {
      'M1': 'M1',
      'M2': 'M2',
      'P1': 'P1',
      'P2': 'P2',
      'OVT': 'OVT',
      'PEN': 'PEN',
      'TB': 'TB'
    }
    return periodMap[period] || period
  }

  /**
   * Convert milliseconds to MM:SS format
   * @param {number} ms - Milliseconds
   * @returns {string} Formatted time MM:SS
   */
  const msToMMSS = (ms) => {
    const totalSeconds = Math.floor(ms / 1000)
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`
  }

  /**
   * Convert milliseconds to seconds
   * @param {number} ms - Milliseconds
   * @returns {number} Seconds
   */
  const msToSS = (ms) => {
    return Math.floor(ms / 1000)
  }

  /**
   * Convert milliseconds to MM:SS.D format (with decimal)
   * @param {number} ms - Milliseconds
   * @returns {string} Formatted time MM:SS.D
   */
  const msToMMSSD = (ms) => {
    const totalSeconds = ms / 1000
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = Math.floor(totalSeconds % 60)
    const deciseconds = Math.floor((ms % 1000) / 100)
    return `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}.${deciseconds}`
  }

  /**
   * Truncate string to specified length
   * @param {string} str - String to truncate
   * @param {number} num - Maximum length
   * @returns {string} Truncated string
   */
  const truncateStr = (str, num) => {
    if (!str) return ''
    if (str.length <= num) return str
    return str.slice(0, num) + '...'
  }

  /**
   * Format team name (uppercase, truncate if needed)
   * @param {string} name - Team name
   * @param {string} zone - Display zone ('inter' or 'club')
   * @returns {string} Formatted team name
   */
  const teamName = (name, zone = 'inter') => {
    if (!name) return ''

    if (zone === 'inter') {
      // International mode: show 3-letter country code
      return name.substring(0, 3).toUpperCase()
    }

    // Club mode: show full name
    return name
  }

  /**
   * Get team logo HTML (48px version)
   * @param {string} logoPath - Logo path from backend (e.g., "str_logo/48/FRA.png")
   * @returns {string} HTML img tag
   */
  const logo48 = (logoPath) => {
    if (!logoPath) return ''

    // The logo path is already complete from the backend
    return `<img class="centre" src="${backendBaseUrl}/img/${logoPath}" height="48" alt="" />`
  }

  /**
   * Get player photo URL
   * @param {string} photo - Photo filename
   * @returns {string} Full photo URL
   */
  const playerPhoto = (photo) => {
    if (!photo) return ''
    return `${backendBaseUrl}/img_ress/joueurs/${photo}`
  }

  /**
   * Get event icon URL (goal, card, etc.)
   * @param {string} type - Event type (B, J, R, V, D)
   * @returns {string} Icon URL
   */
  const eventIcon = (type) => {
    const iconMap = {
      'B': 'ball.png',
      'J': 'carton_jaune.png',
      'R': 'carton_rouge.png',
      'V': 'carton_vert.png',
      'D': 'carton_rouge.png'
    }
    const icon = iconMap[type] || 'ball.png'
    return `${backendBaseUrl}/img/${icon}`
  }

  /**
   * Verify and clean nation code
   * @param {string} nation - Nation code
   * @returns {string} Validated nation code (defaults to 'FRA' if invalid)
   */
  const verifNation = (nation) => {
    if (!nation) return 'FRA'

    // Truncate to 3 characters max
    let code = nation.length > 3 ? nation.substring(0, 3) : nation

    // Check if contains digits (invalid nation code)
    for (let i = 0; i < code.length; i++) {
      const char = code.charAt(i)
      if (char >= '0' && char <= '9') {
        return 'FRA' // Default to France if code contains numbers
      }
    }

    return code
  }

  /**
   * Get flag icon URL
   * @param {string} nation - Nation code (3 letters)
   * @returns {string} Flag icon URL
   */
  const flagIcon = (nation) => {
    const nationCode = verifNation(nation)
    return `${backendBaseUrl}/img_ress/flags/${nationCode.toLowerCase()}.png`
  }

  return {
    formatPeriod,
    msToMMSS,
    msToSS,
    msToMMSSD,
    truncateStr,
    teamName,
    logo48,
    playerPhoto,
    eventIcon,
    verifNation,
    flagIcon
  }
}
