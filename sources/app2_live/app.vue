<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup>
import { onMounted } from 'vue'
import { usePreferenceStore } from '~/stores/preferenceStore'
import { useI18n } from 'vue-i18n'

const preferenceStore = usePreferenceStore()
const { locale } = useI18n()
const runtimeConfig = useRuntimeConfig()
const baseUrl = runtimeConfig.public.baseUrl || ''
const backendBaseUrl = runtimeConfig.public.backendBaseUrl

// SEO Meta Tags
useSeoMeta({
  title: 'KPI Live - Live Display',
  description: 'Real-time live scores display for kayak polo competitions. Display scores, timers, and events in real-time.',
  ogTitle: 'KPI Live - Live Display',
  ogDescription: 'Real-time live scores display for kayak polo competitions',
  ogImage: `${baseUrl}/img/logo_kp.png`,
  ogUrl: backendBaseUrl,
  ogType: 'website',
  twitterCard: 'summary',
  twitterTitle: 'KPI Live - Live Display',
  twitterDescription: 'Real-time live scores display for kayak polo competitions',
  twitterImage: `${baseUrl}/img/logo_kp.png`,
  themeColor: '#1f2937',
  colorScheme: 'light',
  robots: 'noindex, nofollow'
})

// Head configuration
useHead({
  htmlAttrs: {
    lang: locale
  },
  link: [
    { rel: 'icon', type: 'image/x-icon', href: `${baseUrl}/favicon.ico` },
    { rel: 'apple-touch-icon', href: `${baseUrl}/apple-touch-icon.png` }
  ],
  meta: [
    { name: 'viewport', content: 'width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes' },
    { name: 'format-detection', content: 'telephone=no' },
    { name: 'mobile-web-app-capable', content: 'yes' },
    { name: 'apple-mobile-web-app-capable', content: 'yes' },
    { name: 'apple-mobile-web-app-status-bar-style', content: 'black-translucent' },
    { name: 'apple-mobile-web-app-title', content: 'KPI Live' }
  ]
})

onMounted(async () => {
  await preferenceStore.fetchItems()
  if (preferenceStore.preferences.lang) {
    locale.value = preferenceStore.preferences.lang
  }
})
</script>

<style>
@import url("~/assets/css/app.css");
@import "tailwindcss";
@import "animate.css";
</style>
