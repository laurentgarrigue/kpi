<script setup lang="ts">
import { withQuery } from 'ufo'
import { prepareBody } from '~/utils/content'
import { SHARE_IMAGE_WIDTH, cmsLanguageQuery, mediaUrl, mediaVariant, newsArticleJsonLd, newsDate, wasUpdated, type NewsArticle } from '~/utils/news'

// One article (PAGE_NEWS.md § 2.2, NEWS-03 to NEWS-07). A preview (`?preview=` token from app4) is fetched by the
// browser only: the cached server rendering never holds an unpublished article (CMS-12).
const route = useRoute()
const { t, locale } = useI18n()
const localePath = useLocalePath()
const { legacyBaseUrl, i18n } = useRuntimeConfig().public
const language = useLanguageTag()

const slug = computed(() => String(route.params.slug))
const preview = computed(() => (typeof route.query.preview === 'string' ? route.query.preview : null))
const { data: article } = await useApiResource<NewsArticle>(
  () => withQuery(`/news/${slug.value}`, { ...cmsLanguageQuery(locale.value), preview: preview.value ?? undefined }),
  { server: preview.value === null },
)

const body = computed(() => (article.value
  ? prepareBody(article.value.body, { legacyBaseUrl, videoLabel: provider => t('news.video', { provider }) })
  : null))
const url = computed(() => `${i18n.baseUrl.replace(/\/+$/, '')}${localePath(`/news/${slug.value}`)}`)
const image = computed(() => (article.value?.cover ? mediaUrl(mediaVariant(article.value.cover, SHARE_IMAGE_WIDTH), legacyBaseUrl) : null))
const description = computed(() => article.value?.seo.description || article.value?.lead || undefined)
const categoryPath = (category: string) => localePath(withQuery('/news', { category }))

useSeoMeta({
  title: () => article.value?.seo.title || article.value?.title || t('news.title'),
  description: () => description.value,
  ogTitle: () => article.value?.seo.title || article.value?.title,
  ogDescription: () => description.value,
  ogType: 'article',
  ogUrl: () => url.value,
  ogImage: () => image.value ?? undefined,
  ogImageAlt: () => article.value?.cover?.alt,
  twitterCard: () => (image.value ? 'summary_large_image' : 'summary'),
  articlePublishedTime: () => article.value?.published_at ?? undefined,
  articleModifiedTime: () => article.value?.updated_at,
  robots: () => (preview.value ? 'noindex, nofollow' : undefined),
})
useHead(() => ({
  script: article.value && !article.value.preview
    ? [{ type: 'application/ld+json', innerHTML: JSON.stringify(newsArticleJsonLd(article.value, url.value, image.value, t('site.name'))) }]
    : [],
}))
</script>

<template>
  <article v-if="article && body" class="mx-auto max-w-4xl space-y-6">
    <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: $t('news.title'), to: localePath('/news') }, { label: article.title }]" />
    <p v-if="article.preview" class="rounded bg-kpi-gold-50 p-3 text-kpi-gold-800" role="status" data-testid="news-preview">{{ $t('news.preview') }}</p>

    <header class="space-y-3">
      <h1 class="text-5xl leading-tight text-kpi-blue-600">{{ article.title }}</h1>
      <p class="flex flex-wrap gap-x-2 text-sm text-ink/70" data-testid="news-meta">
        <i18n-t v-if="article.published_at" keypath="news.publishedOn" tag="span" scope="global">
          <template #date><time :datetime="article.published_at">{{ newsDate(article.published_at, language) }}</time></template>
        </i18n-t>
        <i18n-t v-if="wasUpdated(article.published_at, article.updated_at)" keypath="news.updatedOn" tag="span" scope="global" data-testid="news-updated">
          <template #date><time :datetime="article.updated_at">{{ newsDate(article.updated_at, language) }}</time></template>
        </i18n-t>
        <span v-if="article.signature" data-testid="news-signature">· {{ $t('news.by', { signature: article.signature }) }}</span>
      </p>
      <ul v-if="article.categories.length > 0" class="flex flex-wrap gap-2" :aria-label="$t('news.categories')" data-testid="news-categories">
        <li v-for="category in article.categories" :key="category.slug">
          <NuxtLink :to="categoryPath(category.slug)" class="rounded bg-kpi-blue-50 px-2 py-0.5 text-sm text-kpi-blue-600 hover:underline">{{ category.label }}</NuxtLink>
        </li>
      </ul>
    </header>

    <ContentImage v-if="article.cover" :media="article.cover" :width="1280" sizes="(min-width: 896px) 896px, 100vw" :lazy="false" class="w-full rounded" data-testid="news-cover" />
    <p v-if="article.lead" class="text-xl font-semibold" data-testid="news-lead">{{ article.lead }}</p>
    <ContentBody :html="body.html" />

    <section v-if="article.links.length > 0" aria-labelledby="news-related" class="rounded border border-line p-4" data-testid="news-links">
      <h2 id="news-related" class="mb-2 text-2xl text-kpi-blue-600">{{ $t('news.related') }}</h2>
      <ul class="space-y-1">
        <li v-for="link in article.links" :key="link.path">
          <span class="text-sm text-ink/70">{{ $t(`news.relatedKind.${link.kind}`) }} :</span>
          <NuxtLink :to="localePath(link.path)" class="font-semibold hover:underline">{{ link.label }}</NuxtLink>
        </li>
      </ul>
    </section>

    <NewsShare v-if="!article.preview" :url="url" :title="article.title" />

    <nav v-if="article.previous || article.next" :aria-label="$t('news.siblings')" class="flex justify-between gap-4 border-t border-line pt-4" data-testid="news-siblings">
      <NuxtLink v-if="article.previous" :to="localePath(`/news/${article.previous.slug}`)" rel="prev" class="hover:underline" data-testid="news-previous">
        <span class="block text-sm text-ink/70">← {{ $t('news.previous') }}</span>{{ article.previous.title }}
      </NuxtLink>
      <span v-else />
      <NuxtLink v-if="article.next" :to="localePath(`/news/${article.next.slug}`)" rel="next" class="text-right hover:underline" data-testid="news-next">
        <span class="block text-sm text-ink/70">{{ $t('news.next') }} →</span>{{ article.next.title }}
      </NuxtLink>
    </nav>
  </article>
</template>
