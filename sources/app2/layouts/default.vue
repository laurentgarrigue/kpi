<template>
  <div>
    <AppHeader />

    <div class="pb-16">
      <NuxtPage />
    </div>

    <AppFooter />
    <AppInstallPrompt />
    <ClientOnly>
      <UpdatePrompt />
    </ClientOnly>
  </div>
</template>

<script setup>
// Network monitoring: useOnlineStatus (AppHeader). Service worker updates: usePwa (UpdatePrompt).
</script>
