<script setup lang="ts">
import { newsDate, type NewsSummary } from '~/utils/news'

// Date and categories of a news item, one muted line (PAGE_NEWS.md § 2.1.2).
defineProps<{ article: Pick<NewsSummary, 'published_at' | 'categories'>, withCategories?: boolean }>()
const language = useLanguageTag()
</script>

<template>
  <p class="text-[0.8125rem] text-muted">
    <time :datetime="article.published_at">{{ newsDate(article.published_at, language) }}</time><template v-if="withCategories && article.categories.length">, <b class="font-semibold text-kpi-blue-700" data-testid="news-categories">{{ article.categories.map(category => category.label).join(', ') }}</b></template>
  </p>
</template>
