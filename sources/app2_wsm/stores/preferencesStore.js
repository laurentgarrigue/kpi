import { defineStore } from 'pinia'
import { ref } from 'vue'
import db from '~/utils/db'

export const usePreferencesStore = defineStore('preferences', () => {
  // State
  const selectedEvent = ref(null)
  const locale = ref('fr')
  const pitchCount = ref(1)
  const syncToDatabase = ref(false)

  // Actions
  async function loadFromDB() {
    try {
      const prefs = await db.preferences.toArray()
      prefs.forEach(pref => {
        switch (pref.id) {
          case 'selectedEvent':
            selectedEvent.value = pref.value
            break
          case 'locale':
            locale.value = pref.value
            break
          case 'pitchCount':
            pitchCount.value = pref.value
            break
          case 'syncToDatabase':
            syncToDatabase.value = pref.value
            break
        }
      })
    } catch (err) {
      console.error('Failed to load preferences from IndexedDB:', err)
    }
  }

  async function savePref(id, value) {
    // Update state
    switch (id) {
      case 'selectedEvent':
        selectedEvent.value = value
        break
      case 'locale':
        locale.value = value
        break
      case 'pitchCount':
        pitchCount.value = value
        break
      case 'syncToDatabase':
        syncToDatabase.value = value
        break
    }

    // Save to IndexedDB
    try {
      await db.preferences.put({ id, value })
    } catch (err) {
      console.error('Failed to save preference to IndexedDB:', err)
    }
  }

  async function clearPreferences() {
    selectedEvent.value = null
    locale.value = 'fr'
    pitchCount.value = 1
    syncToDatabase.value = false

    try {
      await db.preferences.clear()
    } catch (err) {
      console.error('Failed to clear preferences from IndexedDB:', err)
    }
  }

  return {
    // State
    selectedEvent,
    locale,
    pitchCount,
    syncToDatabase,
    // Actions
    loadFromDB,
    savePref,
    clearPreferences,
  }
})
