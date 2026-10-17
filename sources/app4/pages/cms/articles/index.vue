<script setup lang="ts">
import type { CmsArticleSummary, CmsCategory } from '~/types/cms'

// Articles of the public site (PAGE_CMS.md § 2): list filtered by status and category, search in titles.
definePageMeta({ layout: 'admin', middleware: ['auth', 'editorial'] })

const { t, locale } = useI18n()
const cms = useCmsApi()
const router = useRouter()

const articles = ref<CmsArticleSummary[]>([])
const categories = ref<CmsCategory[]>([])
const ALL = 'all'
const status = ref(ALL)
const category = ref(ALL)
const search = ref('')
const page = ref(1)
const pages = ref(1)
const total = ref(0)
const loading = ref(false)
const STATUSES = ['draft', 'scheduled', 'published', 'archived'] as const

const statusItems = computed(() => [{ label: t('cms.all_statuses'), value: ALL }, ...STATUSES.map(value => ({ label: t(`cms.status.${value}`), value }))])
const categoryItems = computed(() => [{ label: t('cms.all_categories'), value: ALL }, ...categories.value.map(item => ({ label: locale.value === 'en' ? item.label_en : item.label_fr, value: String(item.id) }))])
const categoryLabel = (id: number) => {
  const found = categories.value.find(item => item.id === id)
  return found ? (locale.value === 'en' ? found.label_en : found.label_fr) : ''
}

async function load() {
  loading.value = true
  try {
    const result = await cms.articles({ page: page.value, status: status.value === ALL ? '' : status.value, category: category.value === ALL ? '' : category.value, search: search.value })
    articles.value = result.items
    pages.value = result.pages
    total.value = result.total
  } finally {
    loading.value = false
  }
}

let debounce: ReturnType<typeof setTimeout> | undefined
watch(search, () => {
  clearTimeout(debounce)
  debounce = setTimeout(() => { page.value = 1; load() }, 300)
})
watch([status, category], () => { page.value = 1; load() })
watch(page, load)

onMounted(async () => {
  categories.value = await cms.categories()
  await load()
})
</script>

<template>
  <div>
    <h1 class="mb-6 text-2xl font-bold text-header-900 dark:text-header-50">{{ t('cms.articles.title') }}</h1>
    <AdminToolbar v-model:search="search" :search-placeholder="t('cms.articles.search')" :add-label="t('cms.articles.add')" @add="router.push('/cms/articles/new')">
      <template #left>
        <USelect v-model="status" :items="statusItems" class="w-40" :aria-label="t('cms.status_label')" />
        <USelect v-model="category" :items="categoryItems" class="w-64" :aria-label="t('cms.categories')" />
      </template>
    </AdminToolbar>

    <div class="sticky-thead rounded-lg bg-white dark:bg-header-900 shadow">
      <table class="min-w-full divide-y divide-header-200 dark:divide-header-700 text-sm">
        <thead class="bg-header-50 dark:bg-header-900">
          <tr>
            <th class="px-4 py-2 text-left">{{ t('cms.articles.columns.title') }}</th>
            <th class="hidden px-4 py-2 text-left md:table-cell">{{ t('cms.categories') }}</th>
            <th class="px-4 py-2 text-left">{{ t('cms.status_label') }}</th>
            <th class="hidden px-4 py-2 text-left sm:table-cell">{{ t('cms.articles.columns.published_at') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-header-200 dark:divide-header-700">
          <tr v-if="loading && articles.length === 0"><td colspan="4" class="px-4 py-6 text-center">{{ t('common.loading') }}</td></tr>
          <tr v-else-if="articles.length === 0"><td colspan="4" class="px-4 py-6 text-center">{{ t('cms.articles.empty') }}</td></tr>
          <tr v-for="article in articles" :key="article.id" class="cursor-pointer hover:bg-header-50 dark:hover:bg-header-800" @click="router.push(`/cms/articles/${article.id}`)">
            <td class="px-4 py-2">
              <NuxtLink :to="`/cms/articles/${article.id}`" class="font-medium text-primary-700 dark:text-primary-300 hover:underline" @click.stop>{{ article.title }}</NuxtLink>
              <UBadge v-if="article.imported" size="sm" variant="outline" class="ml-2">{{ t('cms.imported') }}</UBadge>
            </td>
            <td class="hidden px-4 py-2 md:table-cell">
              <span v-if="article.categories.length === 0" class="text-warning-600">{{ t('cms.articles.no_category') }}</span>
              <span v-else>{{ article.categories.map(categoryLabel).join(', ') }}</span>
            </td>
            <td class="px-4 py-2"><CmsStatusBadge :status="article.status" /></td>
            <td class="hidden px-4 py-2 sm:table-cell tabular-nums">{{ article.published_at?.slice(0, 16) ?? '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="mt-3 text-sm text-header-600 dark:text-header-300">{{ t('cms.articles.total', { count: total }) }}</p>
    <CmsPager v-if="pages > 1" v-model:page="page" :pages="pages" class="mt-3" />
    <AdminScrollToTop />
  </div>
</template>
