import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import db from '~/utils/db'

export const useUserStore = defineStore('user', () => {
  // State
  const user = ref(null)
  const token = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // Getters
  const isAuthenticated = computed(() => !!user.value && !!token.value)
  const userEvents = computed(() => {
    if (!user.value || !user.value.events) return []
    // Events are stored as pipe-separated string in the legacy format
    return user.value.events.split('|').filter(e => e)
  })

  // Actions
  async function loadFromDB() {
    try {
      const stored = await db.user.get('current')
      if (stored) {
        user.value = stored.data
        token.value = stored.token
      }
    } catch (err) {
      console.error('Failed to load user from IndexedDB:', err)
    }
  }

  async function saveUser(userData, authToken) {
    user.value = userData
    token.value = authToken

    try {
      await db.user.put({
        id: 'current',
        data: userData,
        token: authToken,
      })
    } catch (err) {
      console.error('Failed to save user to IndexedDB:', err)
    }
  }

  async function clearUser() {
    user.value = null
    token.value = null

    try {
      await db.user.delete('current')
    } catch (err) {
      console.error('Failed to clear user from IndexedDB:', err)
    }
  }

  function setLoading(value) {
    loading.value = value
  }

  function setError(err) {
    error.value = err
  }

  return {
    // State
    user,
    token,
    loading,
    error,
    // Getters
    isAuthenticated,
    userEvents,
    // Actions
    loadFromDB,
    saveUser,
    clearUser,
    setLoading,
    setError,
  }
})
