<template>
  <button
    v-if="hasSelection"
    type="button"
    class="flex h-full w-full cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border border-gray-200 bg-surface p-4 shadow-sm transition-colors hover:border-blue-500"
    @click="copyLinkToClipboard"
  >
    <canvas ref="qrcodeCanvas" class="rounded" />
    <span class="flex items-center gap-1 text-sm text-gray-600">
      <UIcon name="i-heroicons-clipboard-document" class="h-4 w-4" />
      {{ t('Event.ClickToCopy') }}
    </span>
  </button>
</template>

<script setup>
import { ref, watch, onMounted, nextTick, computed } from 'vue'
import QRCode from 'qrcode'

const { t } = useI18n()
const preferenceStore = usePreferenceStore()
const { hasEventSelected } = useEventGuard()
const runtimeConfig = useRuntimeConfig()
const toast = useToast()

// Kept small so that the « Coming up » section below stays in view (PAGE_HOME.md § 7).
const QR_SIZE = 180
const QR_LOGO_SIZE = 40

const qrcodeCanvas = ref(null)

// Check if we have either an event or a group selected
const hasSelection = computed(() => {
  return hasEventSelected.value ||
         (preferenceStore.preferences?.lastGroup !== undefined && preferenceStore.preferences?.lastGroup !== null)
})

/**
 * Get the share URL based on current selection (event or group)
 */
const getShareUrl = () => {
  const baseUrl = runtimeConfig.public.baseUrl || ''
  const origin = window.location.origin

  // Check if we have an event selected
  const eventId = preferenceStore.preferences.lastEvent?.id
  if (eventId) {
    return `${origin}${baseUrl}/event/${eventId}`
  }

  // Check if we have a group selected
  const groupCode = preferenceStore.preferences.lastGroup?.code
  const season = preferenceStore.preferences.lastSeason
  if (groupCode && season) {
    return `${origin}${baseUrl}/group/${season}/${groupCode}`
  }

  return null
}

/**
 * Generate QR code with centered logo
 */
const generateQRCode = async () => {
  if (!hasSelection.value || !qrcodeCanvas.value) {
    return
  }

  const shareUrl = getShareUrl()
  if (!shareUrl) return

  try {
    const canvas = qrcodeCanvas.value
    const ctx = canvas.getContext('2d')

    // Generate QR code on canvas with high error correction (to allow logo overlay)
    await QRCode.toCanvas(canvas, shareUrl, {
      errorCorrectionLevel: 'H',
      margin: 2,
      width: QR_SIZE,
      color: {
        dark: '#000000',
        light: '#FFFFFF'
      }
    })

    // Load and draw logo in the center
    const logo = new Image()
    logo.crossOrigin = 'anonymous'
    logo.src = '/img/logo_kp.png'

    logo.onload = () => {
      const logoSize = QR_LOGO_SIZE
      const x = (canvas.width - logoSize) / 2
      const y = (canvas.height - logoSize) / 2

      // Draw white background circle for logo
      ctx.fillStyle = '#FFFFFF'
      ctx.beginPath()
      ctx.arc(canvas.width / 2, canvas.height / 2, logoSize / 2 + 5, 0, 2 * Math.PI)
      ctx.fill()

      // Draw logo
      ctx.drawImage(logo, x, y, logoSize, logoSize)
    }

    logo.onerror = (error) => {
      console.warn('Failed to load logo for QR code:', error)
      // QR code still works without logo
    }
  } catch (error) {
    console.error('Failed to generate QR code:', error)
  }
}

/**
 * Copy link to clipboard
 */
const copyLinkToClipboard = async () => {
  if (!hasSelection.value) {
    return
  }

  const shareUrl = getShareUrl()
  if (!shareUrl) return

  try {
    await navigator.clipboard.writeText(shareUrl)

    // Show success toast notification
    toast.add({
      title: t('Event.LinkCopied'),
      description: shareUrl,
      icon: 'i-heroicons-check-circle',
      color: 'success',
      duration: 3000
    })
  } catch (error) {
    console.error('Failed to copy link to clipboard:', error)

    // Show error toast notification
    toast.add({
      title: t('Event.CopyFailed'),
      description: error.message,
      icon: 'i-heroicons-x-circle',
      color: 'error',
      duration: 3000
    })
  }
}

// Watch for event changes and regenerate QR code
watch(
  () => preferenceStore.preferences.lastEvent,
  async () => {
    await nextTick()
    generateQRCode()
  },
  { immediate: true }
)

// Watch for group changes and regenerate QR code
watch(
  () => preferenceStore.preferences.lastGroup,
  async () => {
    await nextTick()
    generateQRCode()
  },
  { immediate: true }
)

onMounted(() => {
  generateQRCode()
})
</script>
