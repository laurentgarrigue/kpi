<script setup lang="ts">
import { joinURL } from 'ufo'
import { ADMIN_PATH } from '~/utils/links'

// Site header: FFCK logo on a light background (charter), site name, search, administration, language
// (SITE_LAYOUT.md § 2.2). Below `md` the search field folds into an icon leading to the search page (FEATURE_SEARCH.md § 2).
const localePath = useLocalePath()
const adminUrl = joinURL(useRuntimeConfig().public.legacyBaseUrl, ADMIN_PATH)
</script>

<template>
  <header class="bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 lg:px-8">
      <NuxtLink :to="localePath('/')" class="flex items-center gap-3" :aria-label="$t('site.homeLinkLabel')" data-testid="home-link">
        <img src="/img/brand/ffck.png" :alt="$t('site.ffckLogoAlt')" width="67" height="48" class="h-12 w-auto">
        <span class="flex flex-col">
          <span class="font-display text-3xl leading-none text-kpi-blue-500">{{ $t('site.name') }}</span>
          <span class="hidden text-sm text-ink sm:block">{{ $t('site.tagline') }}</span>
        </span>
      </NuxtLink>
      <div class="flex items-center gap-3">
        <div class="hidden w-72 md:block" data-testid="header-search">
          <SiteSearch />
        </div>
        <NuxtLink :to="localePath('/search')" class="rounded p-1 text-kpi-blue-600 hover:bg-kpi-blue-50 md:hidden" :aria-label="$t('header.search')" data-testid="header-search-icon">
          <UIcon name="i-heroicons-magnifying-glass" class="size-5" aria-hidden="true" />
        </NuxtLink>
        <a :href="adminUrl" class="flex items-center gap-1 rounded px-2 py-1 text-sm text-kpi-blue-600 hover:bg-kpi-blue-50" data-testid="admin-link">
          <UIcon name="i-heroicons-cog-6-tooth" class="size-5" aria-hidden="true" />
          <span class="sr-only sm:not-sr-only">{{ $t('header.admin') }}</span>
        </a>
        <SiteLanguageSwitcher />
      </div>
    </div>
  </header>
</template>
