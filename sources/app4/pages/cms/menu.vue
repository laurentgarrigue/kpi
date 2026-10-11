<script setup lang="ts">
import type { CmsMenuItem, CmsMenuTarget, CmsPageSummary } from '~/types/cms'
import { moveItem, validationErrors } from '~/utils/cms'

// Menu « Le kayak-polo » of the public site (PAGE_CMS.md § 5, CNT-04): two levels, ordered with up / down buttons.
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

function move(list: CmsMenuItem[], index: number, delta: -1 | 1): CmsMenuItem[] {
  return moveItem(list, index, delta)
}

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
    <ol class="space-y-3">
      <li v-for="(item, index) in items" :key="index" class="rounded-lg bg-white dark:bg-header-900 p-3 shadow">
        <CmsMenuItemRow
v-model="items[index]!" :page-items="pageItems" :target-items="targetItems" :errors="errors" :prefix="`items.${index}`"
          :first="index === 0" :last="index === items.length - 1"
          @up="items = move(items, index, -1)" @down="items = move(items, index, 1)" @remove="items.splice(index, 1)" />
        <ol class="ml-8 mt-2 space-y-2 border-l border-header-200 dark:border-header-700 pl-3">
          <li v-for="(child, childIndex) in item.children" :key="childIndex">
            <CmsMenuItemRow
v-model="item.children![childIndex]!" :page-items="pageItems" :target-items="targetItems" :errors="errors" :prefix="`items.${index}.children.${childIndex}`"
              :first="childIndex === 0" :last="childIndex === item.children!.length - 1"
              @up="item.children = move(item.children!, childIndex, -1)" @down="item.children = move(item.children!, childIndex, 1)" @remove="item.children!.splice(childIndex, 1)" />
          </li>
        </ol>
        <UButton size="xs" variant="ghost" icon="heroicons:plus" class="ml-8 mt-1" @click="(item.children ??= []).push(emptyItem())">{{ t('cms.menu.add_child') }}</UButton>
      </li>
    </ol>
    <div class="flex gap-2">
      <UButton variant="outline" icon="heroicons:plus" @click="items.push(emptyItem())">{{ t('cms.menu.add') }}</UButton>
      <UButton :loading="saving" @click="save">{{ t('common.save') }}</UButton>
    </div>
  </div>
</template>
