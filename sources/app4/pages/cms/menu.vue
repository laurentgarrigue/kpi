<script setup lang="ts">
import type { CmsMenuItem, CmsMenuTarget, CmsPageSummary } from '~/types/cms'
import { validationErrors } from '~/utils/cms'

// Menu « Le kayak-polo » of the public site (PAGE_CMS.md § 5, CNT-04): three levels, ordered with up / down buttons.
definePageMeta({ layout: 'admin', middleware: ['auth', 'editorial'], editorialAdmin: true })

const { t } = useI18n()
const toast = useToast()
const cms = useCmsApi()
const items = ref<CmsMenuItem[]>([])
const pages = ref<CmsPageSummary[]>([])
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const TARGETS: CmsMenuTarget[] = ['page', 'article', 'url']
const targetItems = computed(() => TARGETS.map(value => ({ label: t(`cms.menu.target.${value}`), value })))
const pageItems = computed(() => pages.value.map(page => ({ label: `${page.title} (${page.path})`, value: String(page.id) })))
const emptyItem = (): CmsMenuItem => ({ label_fr: '', label_en: '', target_kind: 'page', target_ref: '', children: [] })

async function save() {
  errors.value = {}
  saving.value = true
  try {
    items.value = await cms.saveMenu(items.value)
    toast.add({ title: t('common.success'), description: t('cms.saved'), color: 'success', duration: 3000 })
  } catch (failure) {
    errors.value = validationErrors(failure)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  [items.value, pages.value] = await Promise.all([cms.menu(), cms.pages()])
})
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">{{ t('cms.menu.title') }}</h1>
    <p class="text-sm text-header-600 dark:text-header-300">{{ t('cms.menu.help') }}</p>
    <CmsMenuTree v-model="items" :depth="1" prefix="items" :page-items="pageItems" :target-items="targetItems" :errors="errors" />
    <div class="flex gap-2">
      <UButton variant="outline" icon="heroicons:plus" @click="items.push(emptyItem())">{{ t('cms.menu.add') }}</UButton>
      <UButton :loading="saving" @click="save">{{ t('common.save') }}</UButton>
    </div>
  </div>
</template>
