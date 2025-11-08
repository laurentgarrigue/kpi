import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useStatusStore = defineStore('status', () => {
  // State
  const isOnline = ref(navigator.onLine)
  const message = ref('')
  const messageClass = ref('info') // 'info', 'success', 'warning', 'error'

  // Getters
  const hasMessage = computed(() => !!message.value)

  // Actions
  function setOnlineStatus(online) {
    isOnline.value = online
  }

  function setMessage(msg, msgClass = 'info') {
    message.value = msg
    messageClass.value = msgClass
  }

  function clearMessage() {
    message.value = ''
    messageClass.value = 'info'
  }

  // Initialize online/offline listeners
  if (typeof window !== 'undefined') {
    window.addEventListener('online', () => setOnlineStatus(true))
    window.addEventListener('offline', () => setOnlineStatus(false))
  }

  return {
    // State
    isOnline,
    message,
    messageClass,
    // Getters
    hasMessage,
    // Actions
    setOnlineStatus,
    setMessage,
    clearMessage,
  }
})
