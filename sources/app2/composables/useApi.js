// Error types for classification
const ErrorType = {
  NETWORK: 'network',
  HTTP_4XX: 'http_4xx',
  HTTP_5XX: 'http_5xx',
  HTTP_404: 'http_404',
  TIMEOUT: 'timeout',
  OFFLINE: 'offline'
}

// Throttle cache toast to prevent duplicates
let lastCacheToastTime = 0
const THROTTLE_CACHE_TOAST_MS = 3000

export const useApi = () => {
  const runtimeConfig = useRuntimeConfig()
  const apiBaseUrl = runtimeConfig.public.apiBaseUrl
  const toast = useToast()
  const { t } = useI18n()

  /**
   * Show cache toast with deduplication
   */
  const showCacheToast = () => {
    const now = Date.now()
    if (now - lastCacheToastTime < THROTTLE_CACHE_TOAST_MS) {
      return // Skip if toast was shown recently
    }
    lastCacheToastTime = now

    toast.add({
      id: 'cache-toast',
      title: t('errors.cache.usingOfflineData.title'),
      description: t('errors.cache.usingOfflineData.description'),
      icon: 'i-heroicons-archive-box',
      color: 'info',
      duration: 3000
    })
  }

  /**
   * Detect error type based on error and response
   */
  const detectErrorType = (error, response) => {
    // If no network connection
    if (!navigator.onLine) {
      return ErrorType.OFFLINE
    }

    // If fetch threw (network error, CORS, etc.)
    if (error && !response) {
      if (error.name === 'AbortError' || error.message.includes('timeout')) {
        return ErrorType.TIMEOUT
      }
      return ErrorType.NETWORK
    }

    // HTTP status code errors
    if (response && !response.ok) {
      if (response.status === 404) return ErrorType.HTTP_404
      if (response.status >= 400 && response.status < 500) return ErrorType.HTTP_4XX
      if (response.status >= 500) return ErrorType.HTTP_5XX
    }

    return null
  }

  /**
   * Show HTTP error toast
   */
  const showHttpErrorToast = (errorType, status) => {
    let titleKey, descKey, icon, color

    switch (errorType) {
      case ErrorType.HTTP_404:
        titleKey = 'errors.http.404.title'
        descKey = 'errors.http.404.description'
        icon = 'i-heroicons-question-mark-circle'
        color = 'warning'
        break
      case ErrorType.HTTP_4XX:
        titleKey = 'errors.http.4xx.title'
        descKey = 'errors.http.4xx.description'
        icon = 'i-heroicons-exclamation-triangle'
        color = 'warning'
        break
      case ErrorType.HTTP_5XX:
        titleKey = 'errors.http.5xx.title'
        descKey = 'errors.http.5xx.description'
        icon = 'i-heroicons-server'
        color = 'error'
        break
      default:
        titleKey = 'errors.generic.title'
        descKey = 'errors.generic.description'
        icon = 'i-heroicons-x-circle'
        color = 'error'
    }

    toast.add({
      id: `http-error-${errorType}`,
      title: t(titleKey),
      description: t(descKey, { status }),
      icon,
      color,
      duration: 3000
    })
  }

  /**
   * Show network error toast
   * Note: OFFLINE errors are handled by useOnlineStatus, so we skip them here
   */
  const showNetworkErrorToast = (errorType) => {
    // Skip OFFLINE errors - they are handled by useOnlineStatus
    if (errorType === ErrorType.OFFLINE) {
      return
    }

    let titleKey, descKey, icon

    switch (errorType) {
      case ErrorType.TIMEOUT:
        titleKey = 'errors.network.timeout.title'
        descKey = 'errors.network.timeout.description'
        icon = 'i-heroicons-clock'
        break
      case ErrorType.NETWORK:
      default:
        titleKey = 'errors.network.failed.title'
        descKey = 'errors.network.failed.description'
        icon = 'i-heroicons-signal-slash'
    }

    toast.add({
      id: `network-error-${errorType}`,
      title: t(titleKey),
      description: t(descKey),
      icon,
      color: 'error',
      duration: 3000
    })
  }

  /**
   * Handle API response with automatic error detection and toast notifications
   * @param {Promise<Response>} responsePromise - The fetch promise
   * @param {Object} options - Configuration options
   * @param {boolean} options.silentErrors - If true, don't show toast notifications
   * @returns {Promise<Response>}
   */
  const handleApiResponse = async (responsePromise, options = {}) => {
    const {
      silentErrors = false
    } = options

    let response = null
    let error = null

    try {
      // Add timeout wrapper (10 seconds)
      response = await Promise.race([
        responsePromise,
        new Promise((_, reject) =>
          setTimeout(() => reject(new Error('Request timeout')), 10000)
        )
      ])

      // Check if response is OK
      if (!response.ok) {
        const errorType = detectErrorType(null, response)

        // Handle other HTTP errors
        if (!silentErrors) {
          showHttpErrorToast(errorType, response.status)
        }

        throw new Error(`HTTP ${response.status}`)
      }

      return response
    } catch (err) {
      error = err
      const errorType = detectErrorType(error, response)

      // Show appropriate error toast (if not HTTP error already handled)
      if (!silentErrors) {
        if (errorType === ErrorType.NETWORK || errorType === ErrorType.OFFLINE || errorType === ErrorType.TIMEOUT) {
          showNetworkErrorToast(errorType)
        }
      }

      throw error
    }
  }

  /**
   * GET request with automatic error handling
   */
  const getApi = (url, options = {}) => {
    const responsePromise = fetch(`${apiBaseUrl}${url}`, {
      headers: {
        'Cache-Control': 'no-cache',
        Pragma: 'no-cache',
        Expires: '0'
      }
    })

    return handleApiResponse(responsePromise, options)
  }

  return { getApi, showCacheToast }
}
