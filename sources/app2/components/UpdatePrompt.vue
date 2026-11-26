<template>
  <div
    v-if="visible"
    class="fixed bottom-20 left-4 right-4 md:left-auto md:right-4 md:w-96 bg-blue-600 text-white rounded-lg shadow-lg p-4 z-50 animate-slide-up"
    role="status"
    aria-live="polite"
  >
    <div class="flex items-start justify-between">
      <div class="flex-1">
        <h3 class="font-semibold text-lg mb-1">{{ t('Update.Message') }}</h3>
        <p class="text-sm text-blue-100 mb-3">{{ t('Update.Description') }}</p>
        <div class="flex space-x-2">
          <button
            data-test="update-now"
            :disabled="updating"
            class="px-4 py-2 bg-white text-blue-600 rounded-md font-semibold hover:bg-blue-50 transition-colors cursor-pointer disabled:opacity-60"
            @click="update"
          >
            {{ t('Update.Button') }}
          </button>
          <button
            data-test="update-later"
            class="px-4 py-2 bg-blue-700 text-white rounded-md hover:bg-blue-800 transition-colors cursor-pointer"
            @click="dismissed = true"
          >
            {{ t('Update.Later') }}
          </button>
        </div>
      </div>
      <button
        class="ml-2 text-blue-200 hover:text-white cursor-pointer"
        :aria-label="t('Update.Later')"
        @click="dismissed = true"
      >
        <UIcon name="i-heroicons-x-mark" class="h-5 w-5" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * « New version » banner — DOC/specs/PWA_AUTO_UPDATE.md §4 (twin of app4's PwaUpdatePrompt).
 *
 * Prompt-to-update: the new service worker waits; nothing switches before « update now ».
 * « Later » only hides the banner for this page: the waiting worker activates on its own once
 * every tab of the app is closed.
 */
const { t } = useI18n()
const { needRefresh, updateApp } = usePwa()

const dismissed = ref(false)
const updating = ref(false)
const visible = computed(() => needRefresh.value && !dismissed.value)

const update = async () => {
  updating.value = true
  await updateApp()
}
</script>

<style scoped>
@keyframes slide-up {
  from {
    transform: translateY(100%);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.animate-slide-up {
  animation: slide-up 0.3s ease-out;
}
</style>
