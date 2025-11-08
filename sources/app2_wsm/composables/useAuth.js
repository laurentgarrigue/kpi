import { useUserStore } from '~/stores/userStore'
import { usePreferencesStore } from '~/stores/preferencesStore'

export function useAuth() {
  const userStore = useUserStore()
  const preferencesStore = usePreferencesStore()
  const { getApi, setCookie, deleteCookie } = useApi()
  const router = useRouter()

  /**
   * Login with username and password
   */
  async function login(username, password) {
    userStore.setLoading(true)
    userStore.setError(null)

    try {
      // Encode credentials in Base64
      const credentials = btoa(`${username}:${password}`)

      // Call authentication API
      const response = await getApi('/auth/login', {
        headers: {
          Authorization: `Basic ${credentials}`,
        },
      })

      if (response.success && response.user && response.token) {
        // Save user and token
        await userStore.saveUser(response.user, response.token)

        // Set cookie for token
        setCookie('token', response.token)

        return { success: true, user: response.user }
      } else {
        throw new Error('Invalid response from server')
      }
    } catch (error) {
      console.error('Login error:', error)
      userStore.setError(error.message)
      return { success: false, error: error.message }
    } finally {
      userStore.setLoading(false)
    }
  }

  /**
   * Logout and clear user data
   */
  async function logout() {
    // Clear user store
    await userStore.clearUser()

    // Clear preferences
    await preferencesStore.clearPreferences()

    // Delete cookie
    deleteCookie('token')

    // Redirect to login
    router.push('/login')
  }

  /**
   * Check if user has access to a specific event
   */
  function hasEventAccess(eventId) {
    if (!userStore.isAuthenticated) return false

    const userEvents = userStore.userEvents
    // If user has no events, they have access to all
    if (userEvents.length === 0) return true

    // Check if event is in user's events list
    return userEvents.includes(String(eventId))
  }

  return {
    login,
    logout,
    hasEventAccess,
    user: computed(() => userStore.user),
    isAuthenticated: computed(() => userStore.isAuthenticated),
    loading: computed(() => userStore.loading),
    error: computed(() => userStore.error),
  }
}
