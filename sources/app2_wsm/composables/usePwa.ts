import { ref, onMounted } from 'vue'

export function usePwa() {
  const isOnline = ref(true)
  const needRefresh = ref(false)
  const offlineReady = ref(false)

  let updateSW: (() => Promise<void>) | undefined

  onMounted(() => {
    // Check online status
    isOnline.value = navigator.onLine

    // Listen for online/offline events
    window.addEventListener('online', () => {
      isOnline.value = true
    })

    window.addEventListener('offline', () => {
      isOnline.value = false
    })

    // Check for service worker updates
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.ready.then(registration => {
        // Check for updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing
          if (newWorker) {
            newWorker.addEventListener('statechange', () => {
              if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                needRefresh.value = true
              }
            })
          }
        })

        // Check immediately for updates
        registration.update()

        // Check for updates every hour
        setInterval(
          () => {
            registration.update()
          },
          60 * 60 * 1000,
        )
      })
    }
  })

  async function updateApp() {
    if (updateSW) {
      await updateSW()
    } else {
      // Fallback: reload the page
      window.location.reload()
    }
  }

  return {
    isOnline,
    needRefresh,
    offlineReady,
    updateApp,
  }
}
