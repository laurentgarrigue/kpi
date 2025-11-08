<template>
  <div
    v-if="needRefresh"
    class="fixed bottom-4 left-4 bg-blue-600 text-white rounded-lg shadow-xl p-4 max-w-sm z-50"
  >
    <p class="mb-3 font-medium">{{ t('Update.Message') }}</p>
    <button
      @click="updateApp"
      class="w-full px-4 py-2 bg-white text-blue-600 rounded hover:bg-gray-100 transition font-medium"
    >
      {{ t('Update.Button') }}
    </button>
  </div>
</template>

<script setup>
const { t } = useI18n()
const { needRefresh, updateApp } = usePwa()
</script>
