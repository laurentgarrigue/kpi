<script setup lang="ts">
import { SEARCH_MIN_LENGTH, searchApiPath, searchKind, searchKinds, searchQuery, type SearchResults } from '~/utils/search'

// Global search results (FEATURE_SEARCH.md, SRC-03 to SRC-07). Never indexed, even after the switch-over.
const route = useRoute()
const { t, locale } = useI18n()
const language = useLanguageTag()
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
  () => `search:${locale.value}:${query.value ?? ''}`,
  () => (query.value === null ? Promise.resolve(null) : api2<SearchResults>(searchApiPath(query.value, locale.value), { headers: forwarded })),
)
const kind = computed(() => (data.value ? searchKind(route.query.kind, data.value) : null))
const view = computed(() => (data.value ? searchKinds(data.value, kind.value, language.value) : null))
const kindLink = (target: string | null) => ({ path: localePath('/search'), query: { q: query.value ?? undefined, kind: target ?? undefined } })
const tooMany = computed(() => error.value?.statusCode === TOO_MANY_REQUESTS)

useSeoMeta({
  title: () => (query.value ? t('search.resultsFor', { query: query.value }) : t('search.title')),
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div class="space-y-4 pb-10">
    <h1 class="pt-2 text-[2.25rem] leading-none text-navy md:text-[2.75rem] dark:text-ink">{{ query ? $t('search.resultsFor', { query }) : $t('search.title') }}</h1>
    <form method="get" :action="localePath('/search')" role="search" class="flex max-w-[45rem] gap-2" data-testid="search-form">
      <label class="sr-only" for="search-page-q">{{ $t('search.label') }}</label>
      <input id="search-page-q" v-model="input" name="q" type="search" maxlength="50" class="min-w-0 flex-1 rounded-lg border-2 border-navy bg-surface px-3.5 py-3 text-[1.0625rem] md:text-xl dark:border-kpi-blue-500">
      <button type="submit" class="rounded-lg bg-navy px-5 font-semibold text-white hover:bg-kpi-blue-700">{{ $t('header.search') }}</button>
    </form>

    <p v-if="!query" data-testid="search-min-length">{{ $t('search.minLength', { min: SEARCH_MIN_LENGTH }) }}</p>
    <p v-else-if="tooMany" class="text-kpi-red-600" data-testid="search-too-many">{{ $t('search.tooMany') }}</p>
    <p v-else-if="error" class="text-kpi-red-600" data-testid="search-unavailable">{{ $t('common.unavailable') }}</p>
    <p v-else-if="!view || view.total === 0" data-testid="search-no-result">{{ $t('search.noResult', { query }) }}</p>
    <template v-else>
      <nav :aria-label="$t('search.kinds')" data-testid="search-kinds">
        <ul class="flex flex-wrap gap-2">
          <li v-for="item in [[null, view.total] as const, ...view.counts]" :key="item[0] ?? 'all'">
            <NuxtLink
              :to="kindLink(item[0])"
              :aria-current="item[0] === kind ? 'page' : undefined"
              class="inline-block rounded-full border px-3 py-1 text-sm"
              :class="item[0] === kind ? 'border-navy bg-navy text-white' : 'border-line hover:bg-wash'"
            >
              {{ item[0] ? $t(`search.category.${item[0]}`) : $t('search.all') }}
              <span class="ml-1" :class="item[0] === kind ? 'text-white/75' : 'text-muted'">{{ item[1] }}</span>
            </NuxtLink>
          </li>
        </ul>
      </nav>
      <section
        v-if="view.best"
        :aria-label="$t('search.best')"
        class="grid max-w-[56.25rem] grid-cols-[3.5rem_minmax(0,1fr)] items-center gap-4.5 rounded-xl bg-wash p-4.5 sm:grid-cols-[5rem_minmax(0,1fr)_auto]"
        data-testid="search-best"
      >
        <ResultsTeamLogo :path="view.best.logo" size="best" />
        <div>
          <small class="text-[0.8125rem] text-muted">{{ $t('search.clubCode', { code: view.best.code }) }}</small>
          <b class="my-0.5 block font-display text-[2rem] leading-none font-normal text-navy dark:text-ink">{{ view.best.label }}</b>
          <small class="text-[0.8125rem] text-muted">{{ view.best.department.label }}</small>
        </div>
        <NuxtLink :to="{ path: localePath('/clubs'), query: { club: view.best.code } }" class="col-span-2 rounded-md bg-navy px-3.5 py-2.5 text-center font-semibold whitespace-nowrap text-white sm:col-span-1">{{ $t('search.showOnMap') }}</NuxtLink>
      </section>
      <section
        v-for="section in view.sections"
        :key="section.category"
        class="max-w-[56.25rem]"
        :aria-labelledby="`search-${section.category}`"
        :data-category="section.category"
      >
        <h2 :id="`search-${section.category}`" class="mb-3 text-[1.875rem] leading-none text-kpi-blue-700">{{ $t(`search.category.${section.category}`) }}</h2>
        <ul>
          <li v-for="hit in section.hits" :key="hit.key">
            <NuxtLink :to="localePath(hit.to)" class="group grid grid-cols-[minmax(0,1fr)_auto] gap-3 border-t border-line py-2.5">
              <span>
                <b class="font-semibold group-hover:underline">{{ hit.label }}</b>
                <small v-if="hit.detail" class="block text-[0.8125rem] text-muted">{{ hit.detail }}</small>
              </span>
              <span v-if="hit.aside" class="self-center text-[0.8125rem] whitespace-nowrap text-muted">{{ hit.aside }}</span>
            </NuxtLink>
          </li>
        </ul>
      </section>
    </template>
  </div>
</template>
