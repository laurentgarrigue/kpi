<script setup lang="ts">
// Site header: FFCK logo on a light background (charter), site name, language (SITE_LAYOUT.md § 2.2).
const localePath = useLocalePath()
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
      <SiteLanguageSwitcher />
    </div>
  </header>
</template>
