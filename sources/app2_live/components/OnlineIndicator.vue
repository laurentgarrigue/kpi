<template>
  <div v-if="showIndicator" class="online-indicator" :class="{ online: isOnline, offline: !isOnline }">
    <UIcon v-if="isOnline" name="i-heroicons-wifi" class="h-5 w-5" />
    <UIcon v-else name="i-heroicons-wifi" class="h-5 w-5 line-through" />
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue'
import { usePwa } from '~/composables/usePwa'
import { useStatusStore } from '~/stores/statusStore'

const { isOnline } = usePwa()
const statusStore = useStatusStore()

const showIndicator = ref(true)

// Update status store when online status changes
watch(isOnline, (newValue) => {
  statusStore.setOnline(newValue)
})

onMounted(() => {
  // Initialize status
  statusStore.setOnline(navigator.onLine)
})
</script>

<style scoped>
/* Styles are in assets/css/app.css */
</style>
