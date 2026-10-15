<script setup lang="ts">
import { withQuery } from 'ufo'
import { homeLayout, type HomeBlock } from '~/utils/home'
import { cmsLanguageQuery } from '~/utils/news'
import { NEW_TAB_ATTRS } from '~/utils/links'
import { MAIN_MENU, findMenuLink, resolveMenuLink } from '~/utils/navigation'

// Home page (PAGE_HOME.md), with the editorial blocks of phase 4 (PAGE_CONTENT.md § 4).
const { t, locale } = useI18n()
const localePath = useLocalePath()
const { legacyBaseUrl, app2BaseUrl } = useRuntimeConfig().public

useSeoMeta({ title: () => t('home.title') })

// CNT-05: an api2 error only removes the editorial blocks; the events blocks stay.
const api2 = useApi2()
const { data: blocks } = await useAsyncData(
  () => `home-blocks:${locale.value}`,
  () => api2<HomeBlock[]>(withQuery('/home', cmsLanguageQuery(locale.value))).catch(() => []),
)
const layout = computed(() => homeLayout(blocks.value ?? []))

// HOME-02: same target as the "Competitions and results" menu entry (single source: MAIN_MENU).
const competitionsEntry = findMenuLink(MAIN_MENU, 'competitions-list')
const competitionsLink = computed(() => competitionsEntry
  && resolveMenuLink(competitionsEntry, { locale: locale.value, legacyBaseUrl, app2BaseUrl, localePath: path => localePath(path) }))
</script>

<template>
  <div class="space-y-12">
    <HomeAlert v-if="layout.alert" :level="layout.alert.level" :text="layout.alert.text" />
    <section class="space-y-4">
      <h1 class="text-5xl text-kpi-blue-600">{{ $t('home.title') }}</h1>
      <p class="max-w-3xl text-lg">{{ $t('home.intro') }}</p>
      <div class="flex flex-wrap gap-3">
        <UButton
          v-if="competitionsLink"
          :to="competitionsLink.href"
          :external="competitionsLink.kind !== 'internal'"
          size="lg"
          data-testid="competitions-cta"
        >
          {{ $t('home.competitionsCta') }}
        </UButton>
        <UButton
          :to="app2BaseUrl"
          external
          v-bind="NEW_TAB_ATTRS"
          size="lg"
          color="error"
          variant="outline"
          data-testid="live-cta"
        >
          {{ $t('home.liveCta') }}
          <span class="sr-only">{{ $t('a11y.newTab') }}</span>
        </UButton>
      </div>
    </section>
    <HomeEditorialBlock v-for="block in layout.beforeEvents" :key="block.kind" :block="block" />
    <HomeEvents />
    <HomeEditorialBlock v-for="block in layout.afterEvents" :key="block.kind" :block="block" />
  </div>
</template>
