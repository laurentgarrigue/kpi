<template>
  <div
    v-if="showPrompt"
    class="fixed bottom-4 right-4 bg-white rounded-lg shadow-xl p-4 max-w-sm z-50 border border-gray-200"
  >
    <div class="flex items-start justify-between mb-2">
      <p class="text-sm font-medium text-gray-900">
        {{ t('AddToHomeScreen.message') }}
      </p>
      <button @click="dismiss" class="text-gray-400 hover:text-gray-600 transition">
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
    </div>

    <div class="flex gap-2">
      <button
        @click="dismiss"
        class="flex-1 px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300 transition text-sm font-medium"
      >
        {{ t('AddToHomeScreen.Dismiss') }}
      </button>
      <button
        @click="install"
        class="flex-1 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition text-sm font-medium"
      >
        {{ t('AddToHomeScreen.Install') }}
      </button>
    </div>
  </div>
</template>

<script setup>
const { t } = useI18n()
const showPrompt = ref(false)
let deferredPrompt = null

onMounted(() => {
  window.addEventListener('beforeinstallprompt', e => {
    // Prevent the mini-infobar from appearing
    e.preventDefault()
    // Store the event for later use
    deferredPrompt = e
    // Show the install prompt
    showPrompt.value = true
  })
})

function dismiss() {
  showPrompt.value = false
  deferredPrompt = null
}

async function install() {
  if (!deferredPrompt) return

  // Show the install prompt
  deferredPrompt.prompt()

  // Wait for the user's response
  const { outcome } = await deferredPrompt.userChoice

  // Clear the prompt
  deferredPrompt = null
  showPrompt.value = false
}
</script>
