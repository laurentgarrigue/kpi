<template>
  <div class="min-h-screen flex flex-col bg-gray-50">
    <AppNavbar />

    <AppOnlineStatus />

    <main class="flex-1 pb-16">
      <slot />
    </main>

    <AppFooter />

    <ClientOnly>
      <AppInstallPrompt />
      <AppUpdatePrompt />
    </ClientOnly>
  </div>
</template>

<script setup></script>
