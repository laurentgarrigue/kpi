<script setup lang="ts">
import { NEW_TAB_ATTRS } from '~/utils/links'
import type { HomeBlock } from '~/utils/home'

// One editorial block of the home page: featured articles, latest news or partners (PAGE_CONTENT.md § 4).
defineProps<{ block: Exclude<HomeBlock, { kind: 'alert' }> }>()
const localePath = useLocalePath()
</script>

<template>
  <section v-if="block.kind === 'featured'" aria-labelledby="home-featured" class="space-y-4" data-testid="home-featured">
    <h2 id="home-featured" class="text-4xl text-kpi-blue-600">{{ $t('home.featured') }}</h2>
    <NewsCard v-for="article in block.articles" :key="article.slug" :article="article" heading-level="h3" large />
  </section>

  <section v-else-if="block.kind === 'latest_news'" aria-labelledby="home-latest-news" class="space-y-4" data-testid="home-latest-news">
    <div class="flex flex-wrap items-end justify-between gap-2">
      <h2 id="home-latest-news" class="text-4xl text-kpi-blue-600">{{ $t('home.latestNews') }}</h2>
      <NuxtLink :to="localePath('/news')" class="hover:underline" data-testid="home-all-news">{{ $t('home.allNews') }} →</NuxtLink>
    </div>
    <div class="grid gap-6 md:grid-cols-3">
      <NewsCard v-for="article in block.articles" :key="article.slug" :article="article" heading-level="h3" />
    </div>
  </section>

  <section v-else-if="block.kind === 'partners'" aria-labelledby="home-partners" class="space-y-4" data-testid="home-partners">
    <h2 id="home-partners" class="text-4xl text-kpi-blue-600">{{ $t('home.partners') }}</h2>
    <ul class="flex flex-wrap items-center gap-8">
      <li v-for="partner in block.partners" :key="partner.url || partner.name">
        <a :href="partner.url" v-bind="NEW_TAB_ATTRS" class="block">
          <ContentImage v-if="partner.logo" :media="{ ...partner.logo, alt: partner.name }" :width="320" class="h-16 w-auto object-contain" />
          <span v-else class="font-semibold">{{ partner.name }}</span>
          <span class="sr-only">{{ $t('a11y.newTab') }}</span>
        </a>
      </li>
    </ul>
  </section>
</template>
