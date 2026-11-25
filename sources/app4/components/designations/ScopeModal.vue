<script setup lang="ts">
import type { ScopeModalTarget } from '~/utils/designations'

/** Fenêtre du bloc « Désignations » d'un périmètre, ouverte depuis les pages compétitions, événements et équipes. */

defineProps<{ target: ScopeModalTarget | null }>()
const emit = defineEmits<{ close: [], changed: [] }>()
const { t } = useI18n()
</script>

<template>
  <AdminModal :open="target !== null" :title="target ? t('designations.block.title', { line: target.label }) : ''" max-width="xl" @close="emit('close')">
    <DesignationsScopePanel
      v-if="target"
      :season="target.season"
      :scope-type="target.scopeType"
      :scope-id="target.scopeId"
      :label="target.label"
      @changed="emit('changed')"
    />
  </AdminModal>
</template>
