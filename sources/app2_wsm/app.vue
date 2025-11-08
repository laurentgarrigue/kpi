<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup>
import { useUserStore } from '~/stores/userStore'
import { usePreferencesStore } from '~/stores/preferencesStore'
import { useEventStore } from '~/stores/eventStore'

const { locale } = useI18n()
const userStore = useUserStore()
const preferencesStore = usePreferencesStore()
const eventStore = useEventStore()

// Load data from IndexedDB on mount
onMounted(async () => {
  await preferencesStore.loadFromDB()
  await userStore.loadFromDB()
  await eventStore.loadFromDB()

  // Set locale from preferences
  if (preferencesStore.locale) {
    locale.value = preferencesStore.locale
  }
})

// Watch locale changes and save to preferences
watch(locale, async newLocale => {
  if (newLocale !== preferencesStore.locale) {
    await preferencesStore.savePref('locale', newLocale)
  }
})

// SEO Meta tags
useHead({
  title: 'KPI WSM - WebSocket Manager',
  meta: [
    { name: 'description', content: 'Kayak Polo Information - WebSocket Manager for live game data' },
    { name: 'theme-color', content: '#1f2937' },
  ],
  link: [{ rel: 'icon', type: 'image/x-icon', href: '/favicon.ico' }],
})
</script>
