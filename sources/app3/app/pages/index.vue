<script setup lang="ts">
import { MAIN_MENU, findMenuLink, resolveMenuLink } from '~/utils/navigation'

// Home page, phase 1 (PAGE_HOME.md).
const { t, locale } = useI18n()
const localePath = useLocalePath()
const { legacyBaseUrl, app2BaseUrl } = useRuntimeConfig().public

useSeoMeta({ title: () => t('home.title') })

// HOME-02: same target as the "Competitions and results" menu entry (single source: MAIN_MENU).
const competitionsEntry = findMenuLink(MAIN_MENU, 'competitions-list')
const competitionsLink = computed(() => competitionsEntry
  && resolveMenuLink(competitionsEntry, { locale: locale.value, legacyBaseUrl, app2BaseUrl, localePath: path => localePath(path) }))
</script>

<template>
  <div class="space-y-12">
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
        <UButton :to="app2BaseUrl" external size="lg" color="error" variant="outline" data-testid="live-cta">
          {{ $t('home.liveCta') }}
        </UButton>
      </div>
    </section>
    <HomeRecentEvents />
  </div>
</template>
