<script setup lang="ts">
import type { CmsRedirect } from '~/types/cms'
import { validationErrors } from '~/utils/cms'

// 301 redirections of old addresses (PAGE_CMS.md § 5): WordPress permalinks (import) and manual entries.
definePageMeta({ layout: 'admin', middleware: ['auth', 'editorial'], editorialAdmin: true })

const { t } = useI18n()
const cms = useCmsApi()
const redirects = ref<CmsRedirect[]>([])
const search = ref('')
const from = ref('')
const to = ref('')
const errors = ref<Record<string, string>>({})

const visible = computed(() => {
  const term = search.value.trim()
  return term ? redirects.value.filter(item => item.from_path.includes(term) || item.to_path.includes(term)) : redirects.value
})

async function load() {
  redirects.value = await cms.redirects()
}

async function add() {
  errors.value = {}
  try {
    await cms.saveRedirect({ from_path: from.value, to_path: to.value })
    from.value = ''
    to.value = ''
    await load()
  } catch (failure) {
    errors.value = validationErrors(failure)
  }
}

async function remove(id: number) {
  await cms.deleteRedirect(id)
  await load()
}

onMounted(load)
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">{{ t('cms.redirects.title') }}</h1>
    <p class="text-sm text-header-600 dark:text-header-300">{{ t('cms.redirects.help') }}</p>
    <form class="flex flex-wrap items-end gap-2 rounded-lg bg-white dark:bg-header-900 p-3 shadow" @submit.prevent="add">
      <UFormField :label="t('cms.redirects.from')" :error="errors.from_path ? t('cms.errors.invalid') : undefined"><UInput v-model="from" class="w-72" placeholder="/2024/06/article/" /></UFormField>
      <UFormField :label="t('cms.redirects.to')" :error="errors.to_path ? t('cms.errors.invalid') : undefined"><UInput v-model="to" class="w-72" placeholder="/news/article" /></UFormField>
      <UButton type="submit" icon="heroicons:plus">{{ t('cms.redirects.add') }}</UButton>
    </form>
    <UInput v-model="search" icon="heroicons:magnifying-glass" :placeholder="t('common.search')" class="w-72" />
    <div class="sticky-thead rounded-lg bg-white dark:bg-header-900 shadow">
      <table class="min-w-full divide-y divide-header-200 dark:divide-header-700 text-sm">
        <thead class="bg-header-50 dark:bg-header-900">
          <tr>
            <th class="px-4 py-2 text-left">{{ t('cms.redirects.from') }}</th>
            <th class="px-4 py-2 text-left">{{ t('cms.redirects.to') }}</th>
            <th class="px-4 py-2 text-right">{{ t('cms.redirects.hits') }}</th>
            <th class="px-4 py-2"><span class="sr-only">{{ t('common.delete') }}</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-header-200 dark:divide-header-700">
          <tr v-for="redirect in visible" :key="redirect.id">
            <td class="px-4 py-2 font-mono text-xs">{{ redirect.from_path }}</td>
            <td class="px-4 py-2 font-mono text-xs">{{ redirect.to_path }}</td>
            <td class="px-4 py-2 text-right tabular-nums">{{ redirect.hits }}</td>
            <td class="px-4 py-2 text-right"><UButton size="xs" variant="ghost" color="error" icon="heroicons:trash" :aria-label="t('common.delete')" @click="remove(redirect.id)" /></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
