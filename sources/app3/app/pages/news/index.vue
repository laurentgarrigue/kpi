<script setup lang="ts">
import {
  isNewsPageOutOfRange,
  newsCategoriesApiPath,
  newsCategory,
  newsListApiPath,
  newsListPath,
  newsPageNumber,
  rssPath,
  type NewsCategory,
  type NewsList,
} from '~/utils/news'

// News list: category filter, 12 per page, pagination by links usable without JavaScript (PAGE_NEWS.md § 2.1).
const route = useRoute()
const { t, locale } = useI18n()
const localePath = useLocalePath()
const api2 = useApi2()

const page = computed(() => newsPageNumber(route.query.page))

const { data: categories } = await useAsyncData(
  () => `news-categories:${locale.value}`,
  () => api2<NewsCategory[]>(newsCategoriesApiPath(locale.value)).catch(() => []),
)
// NEWS-02: an unknown category is ignored (full list).
const category = computed(() => newsCategory(route.query.category, categories.value ?? []))

const { data: list, error } = await useAsyncData(
  () => `news:${locale.value}:${page.value}:${category.value ?? ''}`,
  () => (page.value === null ? Promise.resolve(null) : api2<NewsList>(newsListApiPath(page.value, category.value, locale.value))),
)

const chips = computed(() => [
  { slug: null, label: t('news.allCategories') },
  ...(categories.value ?? []).map(item => ({ slug: item.slug, label: item.label })),
].map(chip => ({ ...chip, to: localePath(newsListPath(1, chip.slug)), current: chip.slug === category.value })))

const pager = computed(() => {
  const current = list.value
  if (!current) {
    return null
  }
  return {
    newer: current.page > 1 ? localePath(newsListPath(current.page - 1, category.value)) : null,
    older: current.page < current.pages ? localePath(newsListPath(current.page + 1, category.value)) : null,
  }
})

const rssUrl = computed(() => rssPath(locale.value))
// The newest news of the first page is put forward, the others are editorial rows (NEWS-10).
const featured = computed(() => (list.value?.page === 1 ? list.value.items[0] ?? null : null))
const rows = computed(() => (featured.value ? list.value!.items.slice(1) : list.value?.items ?? []))

// NEWS-01: an invalid page number or a page after the last one does not exist (raised once the page state is set,
// so that the error page never renders a half-initialised list).
if (page.value === null || (list.value && isNewsPageOutOfRange(list.value))) {
  throw createError({ statusCode: 404, fatal: true })
}

useSeoMeta({
  title: () => ((page.value ?? 1) > 1 ? t('news.pageTitle', { page: page.value }) : t('news.title')),
  description: () => t('news.description'),
})
useHead(() => ({
  link: [{ rel: 'alternate', type: 'application/rss+xml', title: t('news.rssTitle'), href: rssUrl.value }],
}))
</script>

<template>
  <div v-if="page" class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-2.5">
      <SitePageHeading :title="$t('news.title')" />
      <a :href="rssUrl" class="mb-2 flex items-center gap-1 text-sm underline" data-testid="news-rss">
        <UIcon name="i-heroicons-rss" class="size-5" aria-hidden="true" />
        {{ $t('news.rss') }}
      </a>
    </div>

    <nav v-if="chips.length > 1" :aria-label="$t('news.categories')">
      <ul class="flex flex-wrap gap-2" data-testid="news-categories">
        <li v-for="chip in chips" :key="chip.slug ?? 'all'">
          <NuxtLink
            :to="chip.to"
            :aria-current="chip.current ? 'page' : undefined"
            class="block rounded-full border px-3 py-1 text-sm"
            :class="chip.current ? 'border-navy bg-navy text-white' : 'border-line hover:bg-wash'"
          >
            {{ chip.label }}
          </NuxtLink>
        </li>
      </ul>
    </nav>

    <p v-if="error || !list" class="text-kpi-red-600" data-testid="news-unavailable">{{ $t('news.unavailable') }}</p>
    <p v-else-if="list.items.length === 0" data-testid="news-empty">{{ $t('news.empty') }}</p>
    <template v-else>
      <div v-if="featured" data-testid="news-featured">
        <NewsLead :article="featured" />
      </div>
      <ul data-testid="news-list">
        <li v-for="article in rows" :key="article.slug"><NewsRow :article="article" heading-level="h2" /></li>
      </ul>
      <nav v-if="pager && (pager.newer || pager.older)" :aria-label="$t('news.pagination')" class="flex justify-between pb-8 font-semibold" data-testid="news-pager">
        <NuxtLink v-if="pager.newer" :to="pager.newer" rel="prev" class="flex items-center gap-1 hover:underline" data-testid="news-newer">
          <UIcon name="i-heroicons-arrow-left" class="size-5" aria-hidden="true" />{{ $t('news.newer') }}
        </NuxtLink>
        <span v-else />
        <NuxtLink v-if="pager.older" :to="pager.older" rel="next" class="flex items-center gap-1 hover:underline" data-testid="news-older">
          {{ $t('news.older') }}<UIcon name="i-heroicons-arrow-right" class="size-5" aria-hidden="true" />
        </NuxtLink>
      </nav>
    </template>
  </div>
</template>
