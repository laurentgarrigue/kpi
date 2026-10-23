<script setup lang="ts">
import { withQuery } from 'ufo'
import { TOC_MIN_HEADINGS, isCanonicalPagePath, pageSlug, prepareBody, type PageView } from '~/utils/content'
import { cmsLanguageQuery } from '~/utils/news'

// Editorial page, resolved after every application route (PAGE_CONTENT.md § 2, CNT-01 to CNT-03): the api2 slug is
// the last segment, the path must be the page's own path (parents included), otherwise 301 to it.
const route = useRoute()
const { t, locale } = useI18n()
const localePath = useLocalePath()
const { legacyBaseUrl } = useRuntimeConfig().public

const segments = computed(() => route.params.slug ?? [])
const slug = computed(() => pageSlug(segments.value))
if (slug.value === null) {
  throw createError({ statusCode: 404, fatal: true })
}
const preview = computed(() => (typeof route.query.preview === 'string' ? route.query.preview : null))
const { data: page } = await useApiResource<PageView>(
  () => (slug.value === null ? null : withQuery(`/pages/${slug.value}`, { ...cmsLanguageQuery(locale.value), preview: preview.value ?? undefined })),
  { server: preview.value === null },
)

if (page.value && !isCanonicalPagePath(segments.value, page.value)) {
  await navigateTo(localePath(withQuery(page.value.path, route.query)), { redirectCode: 301 })
}

const body = computed(() => (page.value
  ? prepareBody(page.value.body, { legacyBaseUrl, videoLabel: provider => t('news.video', { provider }) })
  : null))
const toc = computed(() => ((body.value?.toc.length ?? 0) >= TOC_MIN_HEADINGS ? body.value!.toc : []))
// The summary follows the reading (CNT-08).
const reading = useReadingPosition(() => toc.value.map(entry => entry.id))
const breadcrumb = computed(() => [
  { label: t('nav.home'), to: localePath('/') },
  ...(page.value?.parents ?? []).map(parent => ({ label: parent.title, to: localePath(parent.path) })),
  { label: page.value?.title ?? '' },
])

useSeoMeta({
  title: () => page.value?.seo.title || page.value?.title,
  description: () => page.value?.seo.description || undefined,
  robots: () => (preview.value ? 'noindex, nofollow' : undefined),
})
useHead(() => ({
  script: page.value && !page.value.preview
    ? [{
        type: 'application/ld+json',
        innerHTML: JSON.stringify({
          '@context': 'https://schema.org',
          '@graph': [
            { '@type': 'WebPage', 'name': page.value.title },
            {
              '@type': 'BreadcrumbList',
              'itemListElement': breadcrumb.value.map((item, index) => ({ '@type': 'ListItem', 'position': index + 1, 'name': item.label, ...(item.to ? { item: item.to } : {}) })),
            },
          ],
        }),
      }]
    : [],
}))
</script>

<template>
  <div v-if="page && body" class="grid justify-center gap-x-14 gap-y-4 pb-10 lg:grid-cols-[minmax(0,45rem)_15rem]">
    <article class="min-w-0">
      <SiteBreadcrumb :items="breadcrumb" />
      <p v-if="page.preview" class="mt-4 rounded bg-kpi-gold-50 p-3 text-kpi-gold-800" role="status" data-testid="page-preview">{{ $t('content.preview') }}</p>
      <h1 class="mt-1.5 mb-5.5 text-[2.5rem] leading-none text-navy md:text-[3.375rem] dark:text-ink">{{ page.title }}</h1>
      <nav
        v-if="toc.length > 0"
        :aria-label="$t('content.toc')"
        class="mb-6 rounded-lg border border-line px-3.5 py-3 text-sm lg:hidden"
      >
        <p class="mb-2 text-[0.8125rem] font-bold text-muted">{{ $t('content.toc') }}</p>
        <ol class="space-y-1">
          <li v-for="entry in toc" :key="entry.id"><a :href="`#${entry.id}`" class="hover:underline">{{ entry.label }}</a></li>
        </ol>
      </nav>
      <ContentBody :html="body.html" class="text-[1.0625rem]" />
      <section v-if="page.children.length > 0" aria-labelledby="page-children" class="mt-8" data-testid="page-children">
        <h2 id="page-children" class="mb-2 text-[1.875rem] leading-none text-kpi-blue-700">{{ $t('content.children') }}</h2>
        <ul class="space-y-1">
          <li v-for="child in page.children" :key="child.path">
            <NuxtLink :to="localePath(child.path)" class="font-semibold hover:underline">{{ child.title }}</NuxtLink>
          </li>
        </ul>
      </section>
    </article>
    <nav
      v-if="toc.length > 0"
      :aria-label="$t('content.toc')"
      class="sticky top-4 mt-17.5 hidden self-start text-sm lg:block"
      data-testid="page-toc"
    >
      <p class="mb-2 text-[0.8125rem] font-bold text-muted">{{ $t('content.toc') }}</p>
      <ol class="border-l-2 border-line">
        <li v-for="entry in toc" :key="entry.id">
          <a
            :href="`#${entry.id}`"
            :aria-current="reading === entry.id ? 'location' : undefined"
            class="-ml-0.5 block border-l-2 py-1.5 pl-3.5"
            :class="reading === entry.id ? 'border-navy font-semibold text-ink dark:border-kpi-blue-500' : 'border-transparent text-muted hover:text-ink'"
          >{{ entry.label }}</a>
        </li>
      </ol>
    </nav>
  </div>
</template>
