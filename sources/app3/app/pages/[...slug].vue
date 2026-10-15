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
  <article v-if="page && body" class="mx-auto max-w-4xl space-y-6">
    <SiteBreadcrumb :items="breadcrumb" />
    <p v-if="page.preview" class="rounded bg-kpi-gold-50 p-3 text-kpi-gold-800" role="status" data-testid="page-preview">{{ $t('content.preview') }}</p>
    <h1 class="text-5xl leading-tight text-kpi-blue-600">{{ page.title }}</h1>
    <nav v-if="toc.length > 0" :aria-label="$t('content.toc')" class="rounded border border-line p-4" data-testid="page-toc">
      <p class="mb-2 font-semibold">{{ $t('content.toc') }}</p>
      <ol class="list-decimal space-y-1 pl-6">
        <li v-for="entry in toc" :key="entry.id"><a :href="`#${entry.id}`" class="hover:underline">{{ entry.label }}</a></li>
      </ol>
    </nav>
    <ContentBody :html="body.html" />
    <section v-if="page.children.length > 0" aria-labelledby="page-children" data-testid="page-children">
      <h2 id="page-children" class="mb-2 text-3xl text-kpi-blue-600">{{ $t('content.children') }}</h2>
      <ul class="space-y-1">
        <li v-for="child in page.children" :key="child.path">
          <NuxtLink :to="localePath(child.path)" class="font-semibold hover:underline">{{ child.title }}</NuxtLink>
        </li>
      </ul>
    </section>
  </article>
</template>
