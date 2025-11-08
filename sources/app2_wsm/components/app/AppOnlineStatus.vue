<template>
  <div v-if="!isOnline" class="bg-red-600 text-white px-4 py-2 text-center">
    <span class="font-semibold">{{ t('status.Offline') }}</span>
  </div>
</template>

<script setup>
import { useStatusStore } from '~/stores/statusStore'

const { t } = useI18n()
const statusStore = useStatusStore()
const isOnline = computed(() => statusStore.isOnline)
</script>
