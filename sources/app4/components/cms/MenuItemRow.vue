<script setup lang="ts">
import type { CmsMenuItem } from '~/types/cms'

// One entry of the editorial menu: labels, target (page, article id or URL), move up / down, remove.
const item = defineModel<CmsMenuItem>({ required: true })
defineProps<{
  pageItems: { label: string, value: string }[]
  targetItems: { label: string, value: string }[]
  errors: Record<string, string>
  prefix: string
  first: boolean
  last: boolean
}>()
const emit = defineEmits<{ up: [], down: [], remove: [] }>()
const { t } = useI18n()
</script>

<template>
  <div class="flex flex-wrap items-end gap-2">
    <div class="flex flex-col">
      <UButton size="xs" variant="ghost" icon="heroicons:chevron-up" :disabled="first" :aria-label="t('cms.move_up')" @click="emit('up')" />
      <UButton size="xs" variant="ghost" icon="heroicons:chevron-down" :disabled="last" :aria-label="t('cms.move_down')" @click="emit('down')" />
    </div>
    <UFormField :label="t('cms.menu.label_fr')" :error="errors[`${prefix}.label_fr`] ? t(`cms.errors.${errors[`${prefix}.label_fr`]}`) : undefined">
      <UInput v-model="item.label_fr" class="w-48" />
    </UFormField>
    <UFormField :label="t('cms.menu.label_en')">
      <UInput v-model="item.label_en" class="w-40" />
    </UFormField>
    <UFormField :label="t('cms.menu.target_kind')">
      <USelect v-model="item.target_kind" :items="targetItems" class="w-32" @update:model-value="item.target_ref = ''" />
    </UFormField>
    <UFormField :label="t('cms.menu.target')" :error="errors[`${prefix}.target_ref`] ? t(`cms.errors.${errors[`${prefix}.target_ref`]}`) : undefined" class="min-w-56 flex-1">
      <USelect v-if="item.target_kind === 'page'" v-model="item.target_ref" :items="pageItems" class="w-full" />
      <UInput v-else v-model="item.target_ref" class="w-full" :placeholder="item.target_kind === 'url' ? 'https://… ou /calendar' : t('cms.menu.article_id')" />
    </UFormField>
    <UButton variant="ghost" color="error" icon="heroicons:trash" :aria-label="t('common.delete')" @click="emit('remove')" />
  </div>
</template>
