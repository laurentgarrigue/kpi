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
  <div v-if="article && body" class="pb-10">
    <article class="mx-auto max-w-[45rem]">
      <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: $t('news.title'), to: localePath('/news') }, { label: article.title }]" />
      <p v-if="article.preview" class="mt-4 rounded bg-kpi-gold-50 p-3 text-kpi-gold-800" role="status" data-testid="news-preview">{{ $t('news.preview') }}</p>

      <header>
        <h1 class="mt-1.5 mb-3.5 text-[2.5rem] leading-none text-navy md:text-[3.375rem] dark:text-ink">{{ article.title }}</h1>
        <div class="mb-5.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted">
          <p class="flex flex-wrap gap-x-2" data-testid="news-meta">
            <i18n-t v-if="article.published_at" keypath="news.publishedOn" tag="span" scope="global">
              <template #date><time :datetime="article.published_at">{{ newsDate(article.published_at, language) }}</time></template>
            </i18n-t>
            <i18n-t v-if="wasUpdated(article.published_at, article.updated_at)" keypath="news.updatedOn" tag="span" scope="global" data-testid="news-updated">
              <template #date><time :datetime="article.updated_at">{{ newsDate(article.updated_at, language) }}</time></template>
            </i18n-t>
            <span v-if="article.signature" data-testid="news-signature">· {{ $t('news.by', { signature: article.signature }) }}</span>
          </p>
          <ul v-if="article.categories.length > 0" class="flex flex-wrap gap-x-4" :aria-label="$t('news.categories')" data-testid="news-categories">
            <li v-for="category in article.categories" :key="category.slug">
              <NuxtLink :to="categoryPath(category.slug)" class="font-semibold text-kpi-blue-700 hover:underline">{{ category.label }}</NuxtLink>
            </li>
          </ul>
        </div>
      </header>

      <ContentImage
        v-if="article.cover"
        :media="article.cover"
        :width="1280"
        sizes="(min-width: 960px) 840px, 100vw"
        :lazy="false"
        class="mb-6.5 block max-h-[32.5rem] w-full rounded-lg object-cover lg:-mx-15 lg:w-[calc(100%+7.5rem)] lg:max-w-none"
        data-testid="news-cover"
      />
      <p v-if="article.lead" class="mb-5.5 text-xl leading-normal font-semibold" data-testid="news-lead">{{ article.lead }}</p>
      <ContentBody :html="body.html" class="text-[1.0625rem]" />

      <section v-if="article.links.length > 0" aria-labelledby="news-related" class="mt-8 rounded-lg border border-line p-4" data-testid="news-links">
        <h2 id="news-related" class="mb-2 text-2xl text-kpi-blue-700">{{ $t('news.related') }}</h2>
        <ul class="space-y-1">
          <li v-for="link in article.links" :key="link.path">
            <span class="text-sm text-muted">{{ $t(`news.relatedKind.${link.kind}`) }} :</span>
            <NuxtLink :to="localePath(link.path)" class="font-semibold hover:underline">{{ link.label }}</NuxtLink>
          </li>
        </ul>
      </section>

      <div v-if="!article.preview" class="mt-8.5 border-t border-line pt-4.5">
        <NewsShare :url="url" :title="article.title" />
      </div>
    </article>

    <nav v-if="article.previous || article.next" :aria-label="$t('news.siblings')" class="mx-auto mt-8 grid max-w-[45rem] gap-4 sm:grid-cols-2" data-testid="news-siblings">
      <NuxtLink v-if="article.previous" :to="localePath(`/news/${article.previous.slug}`)" rel="prev" class="group block rounded-lg border border-line px-4 py-3.5" data-testid="news-previous">
        <small class="block text-[0.8125rem] text-muted">← {{ $t('news.previous') }}</small><b class="group-hover:underline">{{ article.previous.title }}</b>
      </NuxtLink>
      <NuxtLink v-if="article.next" :to="localePath(`/news/${article.next.slug}`)" rel="next" class="group block rounded-lg border border-line px-4 py-3.5 text-right sm:col-start-2" data-testid="news-next">
        <small class="block text-[0.8125rem] text-muted">{{ $t('news.next') }} →</small><b class="group-hover:underline">{{ article.next.title }}</b>
      </NuxtLink>
    </nav>
  </div>
</template>
