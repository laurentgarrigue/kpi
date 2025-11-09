import { defineStore } from 'pinia'

export const useStatusStore = defineStore('statusStore', {
  state: () => ({
    online: true,
    messageText: '',
    messageClass: 'info', // 'success', 'danger', 'warning', 'info'
    messageVisible: false
  }),
  actions: {
    setOnline(status) {
      this.online = status
    },
    setMessage(text, className = 'info', duration = 5000) {
      this.messageText = text
      this.messageClass = className
      this.messageVisible = true

      if (duration > 0) {
        setTimeout(() => {
          this.clearMessage()
        }, duration)
      }
    },
    clearMessage() {
      this.messageVisible = false
      setTimeout(() => {
        this.messageText = ''
        this.messageClass = 'info'
      }, 300)
    }
  }
})
