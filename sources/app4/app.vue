<script setup lang="ts">
// KPI Manager - Root component
const { locale } = useI18n()

// Initialize online status monitoring at app level
useOnlineStatus()

// Head configuration
useHead({
  htmlAttrs: {
    lang: locale,
  },
  meta: [
    { name: 'viewport', content: 'width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes' }
  ]
})
</script>

<template>
  <UApp :toaster="{ position: 'bottom-right' }">
    <!-- Bandeau « préprod expérimentale » (Phase 7 CI/CD) : ne s'affiche que si
         une branche feature a été déployée en préprod à la place de develop.
         Hors préprod, le composant ne fait aucune requête et ne rend rien. -->
    <ExperimentalBanner />
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>

<style>
html {
  -webkit-text-size-adjust: 100%;
  text-size-adjust: 100%;
}

html, body {
  margin: 0;
  padding: 0;
  min-height: 100vh;
}
</style>
