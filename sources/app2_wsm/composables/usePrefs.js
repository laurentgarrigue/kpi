import { usePreferencesStore } from '~/stores/preferencesStore'

export function usePrefs() {
  const preferencesStore = usePreferencesStore()

  /**
   * Set selected event
   */
  async function setSelectedEvent(eventId) {
    await preferencesStore.savePref('selectedEvent', eventId)
  }

  /**
   * Set locale
   */
  async function setLocale(locale) {
    await preferencesStore.savePref('locale', locale)
  }

  /**
   * Set pitch count
   */
  async function setPitchCount(count) {
    await preferencesStore.savePref('pitchCount', count)
  }

  /**
   * Set sync to database preference
   */
  async function setSyncToDatabase(sync) {
    await preferencesStore.savePref('syncToDatabase', sync)
  }

  return {
    // State
    selectedEvent: computed(() => preferencesStore.selectedEvent),
    locale: computed(() => preferencesStore.locale),
    pitchCount: computed(() => preferencesStore.pitchCount),
    syncToDatabase: computed(() => preferencesStore.syncToDatabase),
    // Actions
    setSelectedEvent,
    setLocale,
    setPitchCount,
    setSyncToDatabase,
  }
}
