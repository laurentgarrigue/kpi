<script setup lang="ts">
import { holderLabel, isInherited, type CoverageCell, type CoverageHolder, type RowKind } from '~/utils/designations'

/** Titulaires d'une case du suivi : hérités en italique (SUI-03), alertes (SUI-04), retrait des titulaires propres. */
const props = defineProps<{ cell: CoverageCell, kind: RowKind, canAdd: boolean }>()
const emit = defineEmits<{ add: [], remove: [holder: CoverageHolder] }>()
const { t } = useI18n()
</script>

<template>
  <div class="flex flex-col gap-0.5 min-w-[8rem]">
    <span v-if="props.cell.status === 'not_applicable'" class="text-xs">{{ t('designations.not_applicable') }}</span>
    <span v-for="holder in props.cell.holders" :key="holder.designationId" class="inline-flex items-center gap-1" :class="{ italic: isInherited(holder, props.kind) }">
      <Icon v-if="holder.warnings.length" name="heroicons:exclamation-triangle" class="w-3.5 h-3.5 shrink-0" :aria-label="holder.warnings.map(w => t(`designations.warning.${w}`)).join(', ')" />
      <span>{{ holderLabel(holder) }}</span>
      <span v-if="isInherited(holder, props.kind)" class="text-xs">({{ t(`designations.origin.${holder.origin}`) }})</span>
      <button
        v-else
        type="button"
        class="ml-auto text-danger-600 dark:text-danger-300 hover:text-danger-800 dark:hover:text-danger-200"
        :aria-label="t('designations.remove.label', { name: holderLabel(holder) })"
        :title="t('designations.remove.confirm')"
        @click="emit('remove', holder)"
      >
        <UIcon name="heroicons:trash" class="w-5 h-5" />
      </button>
    </span>
    <button
      v-if="props.canAdd"
      type="button"
      class="self-start inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium rounded border border-current hover:bg-white/30 dark:hover:bg-black/20"
      :aria-label="props.cell.status === 'red' ? t('designations.missing_add') : t('designations.add.button')"
      @click="emit('add')"
    >
      <UIcon name="heroicons:plus" class="w-3.5 h-3.5" />
      {{ t('designations.add.button') }}
    </button>
  </div>
</template>
