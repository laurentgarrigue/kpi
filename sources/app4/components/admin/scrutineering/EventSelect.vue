<script setup lang="ts">
/** Event of the scrutineering, shared with the page event filter of the other pages (`event:{id}`). */
const { t } = useI18n()
const workContext = useWorkContextStore()
const api = useApi()

defineProps<{ eventId: number | null }>()

function onChange(value: string) {
  workContext.setPageEventGroupSelection(value ? `event:${value}` : '')
  if (value) {
    workContext.loadPageEventCompetitions(Number(value), api)
  }
}
</script>

<template>
  <div class="min-w-48 max-w-96">
    <label class="block text-xs font-medium text-header-900 dark:text-header-200 mb-1" for="scrutineering-event">{{ t('scrutineering.event') }}</label>
    <select
      id="scrutineering-event"
      :value="eventId ?? ''"
      class="w-full px-3 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50"
      @change="onChange(($event.target as HTMLSelectElement).value)"
    >
      <option value="">—</option>
      <option v-for="evt in workContext.events" :key="evt.id" :value="evt.id">
        {{ evt.id }} - {{ evt.libelle }}
      </option>
    </select>
  </div>
</template>
