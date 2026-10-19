<script setup lang="ts">
import type { LastUpdateInfo } from '~/types/presence'

/** "Dernière modification dd/mm/yyyy hh:mm:ss par Prénom NOM" of a team (presence sheet and scrutineering). */
defineProps<{ lastUpdate: LastUpdateInfo }>()

const { t } = useI18n()

/** "yyyy-mm-dd hh:mm:ss" → "dd/mm/yyyy hh:mm:ss" */
const formatDate = (date: string): string => {
  const m = date.match(/^(\d{4})-(\d{2})-(\d{2})(.*)$/)
  return m ? `${m[3]}/${m[2]}/${m[1]}${m[4]}` : date
}
</script>

<template>
  <div class="px-4 py-2 bg-header-50 dark:bg-header-900 border border-header-200 dark:border-header-700 rounded-lg text-xs text-header-600 dark:text-header-300 text-center" data-testid="team-last-update">
    {{ t('presence.last_update') }} {{ formatDate(lastUpdate.date) }} {{ t('presence.last_update_by') }} {{ lastUpdate.user }}
  </div>
</template>
