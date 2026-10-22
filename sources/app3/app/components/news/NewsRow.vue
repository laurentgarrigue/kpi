<script setup lang="ts">
import type { NewsSummary } from '~/utils/news'

// One news of an editorial list (PAGE_NEWS.md § 2.1.2): thumbnail, title, lead (hidden on phones), date and
// categories. `compact`: small square thumbnail, title and date only (home page, HOME-13).
withDefaults(defineProps<{ article: NewsSummary, headingLevel?: 'h2' | 'h3', compact?: boolean }>(), { headingLevel: 'h3', compact: false })
const localePath = useLocalePath()
const sameText = (a: string, b: string) => a.replace(/\s+/g, ' ').trim() === b.replace(/\s+/g, ' ').trim()
</script>

<template>
  <article
    class="group relative grid items-start"
    :class="compact ? 'grid-cols-[5.5rem_minmax(0,1fr)] gap-3' : 'grid-cols-[6rem_minmax(0,1fr)] gap-3.5 border-b border-line py-5.5 md:grid-cols-[12.5rem_minmax(0,1fr)] md:gap-5.5'"
    data-testid="news-card"
  >
    <ContentImage
      v-if="article.cover"
      :media="{ ...article.cover, alt: '' }"
      :width="320"
      :sizes="compact ? '88px' : '(min-width: 768px) 200px, 96px'"
      class="w-full rounded-md bg-wash object-cover"
      :class="compact ? 'aspect-square' : 'aspect-square md:aspect-[3/2]'"
    />
    <span
      v-else
      class="grid w-full place-items-center rounded-md bg-wash font-display text-[1.625rem] text-kpi-blue-500"
      :class="compact ? 'aspect-square' : 'aspect-square md:aspect-[3/2]'"
      aria-hidden="true"
    >KPI</span>
    <div>
      <component
        :is="headingLevel"
        class="mb-1.5"
        :class="compact ? 'font-sans text-[0.9375rem] font-bold leading-snug' : 'text-[1.3125rem] leading-[1.05] text-navy md:text-[1.625rem] dark:text-ink'"
      >
        <NuxtLink :to="localePath(`/news/${article.slug}`)" class="after:absolute after:inset-0 group-hover:underline group-hover:decoration-1">{{ article.title.replace(/\s+/g, ' ') }}</NuxtLink>
      </component>
      <p v-if="!compact && article.lead && !sameText(article.lead, article.title)" class="mb-1.5 hidden max-w-[70ch] md:block">{{ article.lead }}</p>
      <NewsMeta :article="article" :with-categories="!compact" />
    </div>
  </article>
</template>
