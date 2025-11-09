<template>
  <div v-if="statusStore.messageVisible" class="status-message">
    <div
      class="alert rounded-lg shadow-lg p-4 cursor-pointer"
      :class="alertClass"
      role="alert"
      @click="handleClose"
    >
      <div class="flex items-start justify-between">
        <div class="flex-1">
          <strong>{{ statusStore.messageText }}</strong>
        </div>
        <button
          type="button"
          class="ml-2 text-current opacity-70 hover:opacity-100"
          @click.stop="handleClose"
        >
          <UIcon name="i-heroicons-x-mark" class="h-5 w-5" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useStatusStore } from '~/stores/statusStore'

const statusStore = useStatusStore()

const alertClass = computed(() => {
  const classes = {
    success: 'bg-green-600 text-white',
    danger: 'bg-red-600 text-white',
    warning: 'bg-yellow-600 text-white',
    info: 'bg-blue-600 text-white'
  }
  return classes[statusStore.messageClass] || classes.info
})

const handleClose = () => {
  statusStore.clearMessage()
}
</script>

<style scoped>
/* Styles are in assets/css/app.css */
</style>
