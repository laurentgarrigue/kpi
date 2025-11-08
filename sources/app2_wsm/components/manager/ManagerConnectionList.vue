<template>
  <div class="space-y-4">
    <div v-for="pitch in pitchCount" :key="pitch" class="bg-white rounded-lg shadow p-6">
      <ManagerConnection :pitch="pitch" :event-id="eventId" :sync-to-database="syncToDatabase" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  pitchCount: {
    type: Number,
    required: true,
  },
  eventId: {
    type: [Number, String],
    default: null,
  },
  syncToDatabase: {
    type: Boolean,
    default: false,
  },
})
</script>
