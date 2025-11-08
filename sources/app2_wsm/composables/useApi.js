export function useApi() {
  const config = useRuntimeConfig()

  /**
   * Get cookie value by name
   */
  function getCookie(name) {
    if (typeof document === 'undefined') return null

    const value = `; ${document.cookie}`
    const parts = value.split(`; ${name}=`)
    if (parts.length === 2) {
      return parts.pop().split(';').shift()
    }
    return null
  }

  /**
   * Set cookie value
   */
  function setCookie(name, value, days = 365) {
    if (typeof document === 'undefined') return

    const expires = new Date()
    expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000)
    document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`
  }

  /**
   * Delete cookie
   */
  function deleteCookie(name) {
    if (typeof document === 'undefined') return

    document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 UTC;path=/`
  }

  /**
   * Get auth headers with token
   */
  function getAuthHeaders() {
    const token = getCookie('token')
    const headers = {
      'Content-Type': 'application/json',
    }

    if (token) {
      headers['X-Auth-Token'] = token
    }

    return headers
  }

  /**
   * GET request
   */
  async function getApi(url, options = {}) {
    const fullUrl = url.startsWith('http') ? url : `${config.public.apiBaseUrl}${url}`

    try {
      const response = await fetch(fullUrl, {
        method: 'GET',
        headers: {
          ...getAuthHeaders(),
          ...options.headers,
        },
        ...options,
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      return await response.json()
    } catch (error) {
      console.error('API GET error:', error)
      throw error
    }
  }

  /**
   * POST request
   */
  async function postApi(url, data, options = {}) {
    const fullUrl = url.startsWith('http') ? url : `${config.public.apiBaseUrl}${url}`

    try {
      const response = await fetch(fullUrl, {
        method: 'POST',
        headers: {
          ...getAuthHeaders(),
          ...options.headers,
        },
        body: JSON.stringify(data),
        ...options,
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      return await response.json()
    } catch (error) {
      console.error('API POST error:', error)
      throw error
    }
  }

  /**
   * PUT request
   */
  async function putApi(url, data, options = {}) {
    const fullUrl = url.startsWith('http') ? url : `${config.public.apiBaseUrl}${url}`

    try {
      const response = await fetch(fullUrl, {
        method: 'PUT',
        headers: {
          ...getAuthHeaders(),
          ...options.headers,
        },
        body: JSON.stringify(data),
        ...options,
      })

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`)
      }

      return await response.json()
    } catch (error) {
      console.error('API PUT error:', error)
      throw error
    }
  }

  return {
    getCookie,
    setCookie,
    deleteCookie,
    getAuthHeaders,
    getApi,
    postApi,
    putApi,
  }
}
