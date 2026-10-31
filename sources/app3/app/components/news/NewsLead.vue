<script setup lang="ts">
import type { NewsSummary } from '~/utils/news'

// The news put forward (PAGE_NEWS.md § 2.1.2, HOME-13): large picture, title in Agency FB, lead, date and categories.
// `stacked`: picture above the text (home page), else beside it (news list, stacked on phones).
withDefaults(defineProps<{ article: NewsSummary, headingLevel?: 'h2' | 'h3', stacked?: boolean }>(), { headingLevel: 'h2', stacked: false })
const localePath = useLocalePath()
</script>

<template>
  <article
    class="group relative grid items-center gap-3.5"
    :class="stacked ? '' : 'border-b border-line pb-7 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)] md:gap-7'"
    data-testid="news-card"
  >
    <ContentImage
      v-if="article.cover"
      :media="article.cover"
      :width="1280"
      :sizes="stacked ? '(min-width: 1024px) 60vw, 100vw' : '(min-width: 768px) 58vw, 100vw'"
      :lazy="false"
      class="w-full rounded-lg bg-wash object-cover"
      :class="stacked ? 'aspect-[2/1]' : 'aspect-[3/2]'"
    />
    <div>
      <component :is="headingLevel" class="mb-2 leading-[1.03] text-navy dark:text-ink" :class="stacked ? 'text-[2rem]' : 'text-[2rem] md:text-[2.5rem]'">
        <NuxtLink :to="localePath(`/news/${article.slug}`)" class="after:absolute after:inset-0 group-hover:underline group-hover:decoration-1 group-hover:underline-offset-4">{{ article.title }}</NuxtLink>
      </component>
      <p v-if="article.lead" class="mb-2 max-w-[65ch]" :class="{ 'md:text-[1.0625rem]': !stacked }">{{ article.lead }}</p>
      <NewsMeta :article="article" with-categories />
    </div>
  </article>
</template>
