import { defineStore } from 'pinia'
import db from '~/utils/db'

export const useEventStore = defineStore('eventStore', {
  state: () => ({
    events: [],
    selectedEvent: null
  }),
  getters: {
    getEventById: (state) => (id) => {
      return state.events.find(event => event.id === id)
    }
  },
  actions: {
    async fetchEvents() {
      // Events will be loaded from API via composable
      // This store just holds the state
      const cached = await db.events.toArray()
      if (cached.length > 0) {
        this.events = cached
      }
    },
    async setEvents(events) {
      this.events = events
      // Cache in IndexedDB
      await db.events.clear()
      await db.events.bulkAdd(events)
    },
    setSelectedEvent(event) {
      this.selectedEvent = event
    }
  }
})
