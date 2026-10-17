<script setup lang="ts">
import type { CmsPageSummary } from '~/types/cms'

// Editorial pages (PAGE_CMS.md § 3): tree ordered by path, indentation by depth.
definePageMeta({ layout: 'admin', middleware: ['auth', 'editorial'] })

const { t } = useI18n()
const cms = useCmsApi()
const router = useRouter()
const pages = ref<CmsPageSummary[]>([])
const loading = ref(true)
const search = ref('')
const DEPTH_INDENT_REM = 1.25

const visible = computed(() => {
  const term = search.value.trim().toLowerCase()
  return term ? pages.value.filter(page => page.title.toLowerCase().includes(term) || page.path.includes(term)) : pages.value
})

onMounted(async () => {
  try {
    pages.value = await cms.pages()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <h1 class="mb-6 text-2xl font-bold text-header-900 dark:text-header-50">{{ t('cms.pages.title') }}</h1>
    <AdminToolbar v-model:search="search" :search-placeholder="t('common.search')" :add-label="t('cms.pages.add')" @add="router.push('/cms/pages/new')" />
    <div class="sticky-thead rounded-lg bg-white dark:bg-header-900 shadow">
      <table class="min-w-full divide-y divide-header-200 dark:divide-header-700 text-sm">
        <thead class="bg-header-50 dark:bg-header-900">
          <tr>
            <th class="px-4 py-2 text-left">{{ t('cms.fields.title') }}</th>
            <th class="hidden px-4 py-2 text-left md:table-cell">{{ t('cms.pages.path') }}</th>
            <th class="px-4 py-2 text-left">{{ t('cms.status_label') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-header-200 dark:divide-header-700">
          <tr v-if="loading"><td colspan="3" class="px-4 py-6 text-center">{{ t('common.loading') }}</td></tr>
          <tr v-else-if="visible.length === 0"><td colspan="3" class="px-4 py-6 text-center">{{ t('cms.pages.empty') }}</td></tr>
          <tr v-for="page in visible" :key="page.id" class="cursor-pointer hover:bg-header-50 dark:hover:bg-header-800" @click="router.push(`/cms/pages/${page.id}`)">
            <td class="px-4 py-2" :style="{ paddingLeft: `${1 + page.depth * DEPTH_INDENT_REM}rem` }">
              <NuxtLink :to="`/cms/pages/${page.id}`" class="font-medium text-primary-700 dark:text-primary-300 hover:underline" @click.stop>{{ page.title }}</NuxtLink>
              <UBadge v-if="page.imported" size="sm" variant="outline" class="ml-2">{{ t('cms.imported') }}</UBadge>
            </td>
            <td class="hidden px-4 py-2 font-mono text-xs md:table-cell">{{ page.path }}</td>
            <td class="px-4 py-2">
              <CmsStatusBadge :status="page.status" />
              <span v-if="page.status === 'published' && !page.public" class="ml-1 text-xs text-warning-600">{{ t('cms.pages.parent_unpublished') }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
