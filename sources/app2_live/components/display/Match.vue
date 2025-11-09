<template>
  <div class="h-screen w-screen flex items-center justify-center bg-gray-900 text-white">
    <div class="text-center">
      <h1 class="text-6xl font-bold mb-4">KPI Live</h1>
      <p class="text-2xl text-gray-400">Match Display Mode</p>
      <p class="text-lg text-gray-500 mt-4">Coming soon...</p>
    </div>
  </div>
</template>

<script setup>
defineProps({
  gameData: {
    type: Object,
    default: null
  }
})
</script>
