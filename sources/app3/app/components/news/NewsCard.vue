<script setup lang="ts">
import { CARD_IMAGE_WIDTH, newsDate, type NewsSummary } from '~/utils/news'

// News card of the list, the home page and the related news (PAGE_NEWS.md § 2.1.2).
withDefaults(defineProps<{ article: NewsSummary, headingLevel?: 'h2' | 'h3', large?: boolean }>(), { headingLevel: 'h2', large: false })
const localePath = useLocalePath()
const language = useLanguageTag()
</script>

<template>
  <article class="flex flex-col overflow-hidden rounded border border-line bg-white" :class="{ 'md:flex-row': large }" data-testid="news-card">
    <ContentImage
      v-if="article.cover"
      :media="article.cover"
      :width="CARD_IMAGE_WIDTH"
      :sizes="large ? '(min-width: 768px) 50vw, 100vw' : '(min-width: 1024px) 33vw, (min-width: 768px) 50vw, 100vw'"
      class="aspect-video w-full object-cover"
      :class="{ 'md:w-1/2': large }"
    />
    <div class="flex flex-1 flex-col gap-2 p-4">
      <component :is="headingLevel" class="text-2xl leading-tight text-kpi-blue-600" :class="{ 'md:text-4xl': large }">
        <NuxtLink :to="localePath(`/news/${article.slug}`)" class="hover:underline">{{ article.title }}</NuxtLink>
      </component>
      <time :datetime="article.published_at" class="text-sm text-ink/70">{{ newsDate(article.published_at, language) }}</time>
      <p v-if="article.lead" class="line-clamp-3">{{ article.lead }}</p>
      <ul v-if="article.categories.length > 0" class="mt-auto flex flex-wrap gap-1 pt-2" :aria-label="$t('news.categories')">
        <li v-for="category in article.categories" :key="category.slug" class="rounded bg-kpi-blue-50 px-2 py-0.5 text-xs text-kpi-blue-600">
          {{ category.label }}
        </li>
      </ul>
    </div>
  </article>
</template>
