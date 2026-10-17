<script setup lang="ts">
import type { CmsSeoFields } from '~/types/cms'

// SEO title and description of a content, per language, with a search-result preview (FEATURE_CMS.md § 2).
const seo = defineModel<CmsSeoFields>({ required: true })
const props = defineProps<{ language: 'fr' | 'en', fallbackTitle: string, errors: Record<string, string> }>()
const { t } = useI18n()
const titleKey = computed(() => `seo_title_${props.language}` as const)
const descriptionKey = computed(() => `seo_description_${props.language}` as const)
</script>

<template>
  <div class="space-y-3">
    <UFormField :label="t('cms.seo.title')" :error="errors[titleKey] ? t(`cms.errors.${errors[titleKey]}`) : undefined">
      <UInput v-model="seo[titleKey]" class="w-full" :placeholder="fallbackTitle" maxlength="255" />
    </UFormField>
    <UFormField :label="t('cms.seo.description')" :help="t('cms.seo.description_help')" :error="errors[descriptionKey] ? t(`cms.errors.${errors[descriptionKey]}`) : undefined">
      <UTextarea v-model="seo[descriptionKey]" class="w-full" :rows="2" maxlength="300" />
    </UFormField>
    <div class="rounded bg-header-50 dark:bg-header-800 p-2 text-sm" :aria-label="t('cms.seo.preview')">
      <p class="truncate text-primary-700 dark:text-primary-300">{{ seo[titleKey] || fallbackTitle }} — kayak-polo.info</p>
      <p class="line-clamp-2 text-header-600 dark:text-header-300">{{ seo[descriptionKey] }}</p>
    </div>
  </div>
</template>
