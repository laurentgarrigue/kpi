<script setup lang="ts">
import { statusColor, type DisplayStatus } from '~/utils/cms'

// Status of an article, page or form (PAGE_CMS.md § 2).
defineProps<{ status: DisplayStatus }>()
const { t } = useI18n()
</script>

<template>
  <UBadge :color="statusColor(status)" variant="subtle" size="sm">{{ t(`cms.status.${status}`) }}</UBadge>
</template>
