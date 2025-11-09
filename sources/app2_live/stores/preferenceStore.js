import { defineStore } from 'pinia'
import { v4 as uuidv4 } from 'uuid'
import db from '~/utils/db'

export const usePreferenceStore = defineStore('preferenceStore', {
  state: () => ({
    preferences: {
      uid: null,
      lang: 'fr',
      lastEvent: null,
      eventName: null,
      eventPlace: null,
      eventLogo: null,
    }
  }),
  actions: {
    async fetchItems() {
      const all = await db.preferences.toArray()
      this.preferences = {
        uid: null,
        lang: 'fr',
        lastEvent: null,
        eventName: null,
        eventPlace: null,
        eventLogo: null,
      }
      all.forEach(item => {
        this.preferences[item.id] = item.value
      })
    },
    async initUid() {
      if (!this.preferences.uid) {
        const newUid = uuidv4()
        await this.putItem('uid', newUid)
      }
    },
    async addItem(id, value) {
      await db.preferences.add({ id, value })
      await this.fetchItems()
    },
    async putItem(id, value) {
      await db.preferences.put({ id, value })
      await this.fetchItems()
    },
    async getItem(id) {
      return await db.preferences.get(id)
    },
    async removeItem(id) {
      await db.preferences.delete(id)
      await this.fetchItems()
    },
    async removeAllItems() {
      await db.preferences.clear()
      await this.fetchItems()
    }
  }
})
