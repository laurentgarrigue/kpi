<template>
  <div class="min-h-screen">
    <NuxtPage />

    <ClientOnly>
      <UpdatePrompt />
      <StatusMessage />
      <OnlineIndicator />
    </ClientOnly>
  </div>
</template>

<script setup>
// Minimal layout for live display - no header/footer for fullscreen display
</script>
