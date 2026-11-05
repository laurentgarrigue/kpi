<script setup lang="ts">
// Standalone schema, opened in a new tab outside the work context (e.g. the competition copy page,
// schemas of other seasons). Inside the Phases page the schema is a view: /gamedays?view=schema.
definePageMeta({
  layout: 'admin',
  middleware: 'auth',
})

const { t } = useI18n()
const route = useRoute()

const competitionCode = computed(() => (route.query.competition as string) || '')
const season = computed(() => (route.query.season as string) || '')

function closeWindow() {
  window.close()
}
</script>

<template>
  <SchemaView :competition="competitionCode" :season="season">
    <template #toolbar-end>
      <button
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg hover:bg-header-50 dark:hover:bg-header-800 transition-colors"
        @click="closeWindow"
      >
        <UIcon name="heroicons:x-mark" class="w-4 h-4" />
        {{ t('schema.close') }}
      </button>
    </template>
  </SchemaView>
</template>
