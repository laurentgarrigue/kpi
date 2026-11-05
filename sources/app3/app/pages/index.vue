<script setup lang="ts">
import { withQuery } from 'ufo'
import { homeAgenda, type HomeFixtures } from '~/utils/fixtures'
import { homeLayout, type HomeBlock } from '~/utils/home'
import { cmsLanguageQuery } from '~/utils/news'

// Home page (PAGE_HOME.md § 2 bis): the next fixture first, news on the left, agenda on the right; editorial blocks
// of phase 4 (PAGE_CONTENT.md § 4).
const { t, locale } = useI18n()
const localePath = useLocalePath()

useSeoMeta({ title: () => t('home.title') })

const api2 = useApi2()
// CNT-05: an api2 error only removes the editorial blocks; the fixtures stay.
const { data: blocks } = await useAsyncData(
  () => `home-blocks:${locale.value}`,
  () => api2<HomeBlock[]>(withQuery('/home', cmsLanguageQuery(locale.value))).catch(() => []),
)
// api2 splits fixtures on its own « today » and sends it with the lists: the browser never recomputes it (HOME-06).
const { data: fixtures, error } = await useAsyncData('home-fixtures', () => api2<HomeFixtures>('/fixtures/site'))
const layout = computed(() => homeLayout(blocks.value ?? []))
const agenda = computed(() => homeAgenda(fixtures.value?.upcoming ?? []))
</script>

<template>
  <div class="space-y-2">
    <HomeAlert v-if="layout.alert" :level="layout.alert.level" :text="layout.alert.text" />
    <h1 class="max-w-[60ch] pt-4 font-sans text-base leading-snug font-semibold text-muted">{{ $t('home.title') }}</h1>
    <p v-if="error || !fixtures" class="text-kpi-red-600" data-testid="fixtures-unavailable">{{ $t('home.fixturesUnavailable') }}</p>
    <HomeNext v-else-if="agenda.next" :fixture="agenda.next" :programme="agenda.programme" :today="fixtures.today" />
    <div class="grid gap-9 pt-8.5 pb-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)] lg:gap-12">
      <section v-if="layout.news.length" aria-labelledby="home-latest-news" data-testid="home-latest-news">
        <div class="mb-3.5 flex items-baseline justify-between gap-3">
          <h2 id="home-latest-news" class="text-[1.875rem] leading-none text-kpi-blue-700">{{ $t('home.news') }}</h2>
          <NuxtLink :to="localePath('/news')" class="text-sm underline" data-testid="home-all-news">{{ $t('home.allNews') }}</NuxtLink>
        </div>
        <NewsLead :article="layout.news[0]!" heading-level="h3" stacked />
        <ul v-if="layout.news.length > 1" class="mt-5.5 grid gap-5 sm:grid-cols-2">
          <li v-for="article in layout.news.slice(1)" :key="article.slug"><NewsRow :article="article" compact /></li>
        </ul>
      </section>
      <HomeAgenda v-if="fixtures && !error" :after="agenda.after" :recent="fixtures.recent" :has-next="agenda.next !== null" :class="{ 'lg:col-start-2': !layout.news.length }" />
    </div>
    <HomePartners v-if="layout.partners" :block="layout.partners" />
  </div>
</template>
