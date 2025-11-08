import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import db from '~/utils/db'

export const useEventStore = defineStore('event', () => {
  // State
  const events = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Getters
  const eventCount = computed(() => events.value.length)

  function getEventById(id) {
    return events.value.find(event => event.id === id)
  }

  // Actions
  async function loadFromDB() {
    try {
      const storedEvents = await db.events.toArray()
      if (storedEvents.length > 0) {
        events.value = storedEvents
      }
    } catch (err) {
      console.error('Failed to load events from IndexedDB:', err)
    }
  }

  async function setEvents(eventList) {
    events.value = eventList

    try {
      // Clear existing events
      await db.events.clear()
      // Add new events
      await db.events.bulkAdd(eventList)
    } catch (err) {
      console.error('Failed to save events to IndexedDB:', err)
    }
  }

  function addEvent(event) {
    const exists = events.value.find(e => e.id === event.id)
    if (!exists) {
      events.value.push(event)
      db.events.add(event).catch(err =>
        console.error('Failed to add event to IndexedDB:', err),
      )
    }
  }

  function removeEvent(eventId) {
    events.value = events.value.filter(e => e.id !== eventId)
    db.events.delete(eventId).catch(err =>
      console.error('Failed to delete event from IndexedDB:', err),
    )
  }

  async function clearEvents() {
    events.value = []
    try {
      await db.events.clear()
    } catch (err) {
      console.error('Failed to clear events from IndexedDB:', err)
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
    events,
    loading,
    error,
    // Getters
    eventCount,
    getEventById,
    // Actions
    loadFromDB,
    setEvents,
    addEvent,
    removeEvent,
    clearEvents,
    setLoading,
    setError,
  }
})
