<script setup lang="ts">
import { SEARCH_MIN_LENGTH, searchApiPath, searchQuery, searchSections, type SearchResults } from '~/utils/search'

// Global search results (FEATURE_SEARCH.md, SRC-03 to SRC-05). Never indexed, even after the switch-over.
const route = useRoute()
const { t } = useI18n()
const localePath = useLocalePath()
const api2 = useApi2()
// api2 limits the search per visitor: the server rendering relays the visitor address (API_PUBLIC_TRANSVERSE.md § 3.6).
const forwarded = useRequestHeaders(['x-forwarded-for'])
const TOO_MANY_REQUESTS = 429

const raw = computed(() => (typeof route.query.q === 'string' ? route.query.q : ''))
const query = computed(() => searchQuery(raw.value))
const input = ref(raw.value)
watch(raw, (value) => {
  input.value = value
})

const { data, error } = await useAsyncData(
  () => `search:${query.value ?? ''}`,
  () => (query.value === null ? Promise.resolve(null) : api2<SearchResults>(searchApiPath(query.value), { headers: forwarded })),
)
const sections = computed(() => (data.value ? searchSections(data.value) : []))
const tooMany = computed(() => error.value?.statusCode === TOO_MANY_REQUESTS)

useSeoMeta({
  title: () => (query.value ? t('search.resultsFor', { query: query.value }) : t('search.title')),
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="space-y-8">
    <h1 class="text-5xl text-kpi-blue-600">{{ query ? $t('search.resultsFor', { query }) : $t('search.title') }}</h1>
    <form method="get" :action="localePath('/search')" role="search" class="flex max-w-xl items-end gap-2" data-testid="search-form">
      <label class="flex flex-1 flex-col text-sm">
        {{ $t('search.label') }}
        <input v-model="input" name="q" type="search" maxlength="50" class="rounded border border-line px-2 py-1">
      </label>
      <UButton type="submit" variant="outline">{{ $t('header.search') }}</UButton>
    </form>

    <p v-if="!query" data-testid="search-min-length">{{ $t('search.minLength', { min: SEARCH_MIN_LENGTH }) }}</p>
    <p v-else-if="tooMany" class="text-kpi-red-600" data-testid="search-too-many">{{ $t('search.tooMany') }}</p>
    <p v-else-if="error" class="text-kpi-red-600" data-testid="search-unavailable">{{ $t('common.unavailable') }}</p>
    <p v-else-if="sections.length === 0" data-testid="search-no-result">{{ $t('search.noResult', { query }) }}</p>
    <div v-else class="grid gap-8 md:grid-cols-2">
      <section v-for="section in sections" :key="section.category" :aria-labelledby="`search-${section.category}`" :data-category="section.category">
        <h2 :id="`search-${section.category}`" class="mb-2 text-3xl text-kpi-blue-600">{{ $t(`search.category.${section.category}`) }}</h2>
        <ul class="space-y-2">
          <li v-for="hit in section.hits" :key="hit.key">
            <NuxtLink :to="localePath(hit.to)" class="font-semibold hover:underline">{{ hit.label }}</NuxtLink>
            <span v-if="hit.detail" class="block text-sm text-ink/70">{{ hit.detail }}</span>
          </li>
        </ul>
      </section>
    </div>
  </div>
</template>
