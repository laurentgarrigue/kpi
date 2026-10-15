<script setup lang="ts">
// Previous / next pager of the editorial lists (server-side pages of fixed size).
const page = defineModel<number>('page', { required: true })
defineProps<{ pages: number }>()
const { t } = useI18n()
</script>

<template>
  <div class="flex items-center justify-center gap-3 text-sm">
    <UButton size="sm" variant="outline" icon="heroicons:chevron-left" :disabled="page <= 1" :aria-label="t('cms.previous_page')" @click="page--" />
    <span>{{ t('cms.page_of', { page, pages }) }}</span>
    <UButton size="sm" variant="outline" icon="heroicons:chevron-right" :disabled="page >= pages" :aria-label="t('cms.next_page')" @click="page++" />
  </div>
</template>
