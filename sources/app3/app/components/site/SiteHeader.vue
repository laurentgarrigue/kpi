<script setup lang="ts">
import { onClickOutside } from '@vueuse/core'
import { joinURL } from 'ufo'
import { ADMIN_PATH } from '~/utils/links'

// Site header: FFCK logo on a light background (charter), site name, search, administration, theme, language
// (SITE_LAYOUT.md § 2.2). The magnifier toggles the search block shown under the navigation (FEATURE_SEARCH.md § 2,
// SRC-06). Below `md`, administration, theme and language fold into a « Réglages » drop-down panel (LAY-14).
const localePath = useLocalePath()
const adminUrl = joinURL(useRuntimeConfig().public.legacyBaseUrl, ADMIN_PATH)
const route = useRoute()
// The magnifier is a link to the search page without JavaScript, the toggle of the search block with it (SRC-06).
const { open: searchOpen, toggle: toggleSearch } = useSearchBlock()

const toolsOpen = ref(false)
const tools = useTemplateRef<HTMLElement>('tools')
const toolsToggle = useTemplateRef<HTMLButtonElement>('toolsToggle')

function closeTools(returnFocus = false) {
  toolsOpen.value = false
  if (returnFocus) {
    toolsToggle.value?.focus()
  }
}

onClickOutside(tools, () => closeTools())
watch(() => route.fullPath, () => closeTools())
</script>

<template>
  <header class="bg-surface">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-2 px-4 py-3 sm:gap-4 lg:px-8">
      <NuxtLink :to="localePath('/')" class="flex min-w-0 items-center gap-2 sm:gap-3" :aria-label="$t('site.homeLinkLabel')" data-testid="home-link">
        <img src="/img/brand/ffck.png" :alt="$t('site.ffckLogoAlt')" width="67" height="48" class="h-10 w-auto shrink-0 rounded bg-white sm:h-12 dark:p-1">
        <span class="flex min-w-0 flex-col">
          <span class="whitespace-nowrap font-display text-2xl leading-none text-kpi-blue-500 sm:text-3xl">{{ $t('site.name') }}</span>
          <span class="hidden text-sm text-ink sm:block">{{ $t('site.tagline') }}</span>
        </span>
      </NuxtLink>
      <div ref="tools" class="relative flex shrink-0 items-center gap-1 md:gap-3" @keydown.esc="closeTools(true)">
        <a
          :id="SEARCH_BLOCK_TOGGLE_ID"
          :href="localePath('/search')"
          role="button"
          class="rounded p-1 text-kpi-blue-600 hover:bg-kpi-blue-50"
          :class="{ 'bg-kpi-blue-50': searchOpen }"
          :aria-label="$t('header.search')"
          :aria-expanded="searchOpen"
          :aria-controls="SEARCH_BLOCK_ID"
          data-testid="header-search-icon"
          @click.prevent="toggleSearch"
        >
          <UIcon name="i-heroicons-magnifying-glass" class="block size-6" aria-hidden="true" />
        </a>
        <button
          ref="toolsToggle"
          type="button"
          class="rounded p-1 text-kpi-blue-600 hover:bg-kpi-blue-50 md:hidden"
          :aria-label="$t('header.settings')"
          :aria-expanded="toolsOpen"
          aria-controls="header-tools"
          data-testid="header-tools-toggle"
          @click="toolsOpen = !toolsOpen"
        >
          <UIcon :name="toolsOpen ? 'i-heroicons-x-mark' : 'i-heroicons-adjustments-horizontal'" class="block size-6" aria-hidden="true" />
        </button>
        <!-- Drop-down panel below md, inline actions from md -->
        <div
          id="header-tools"
          class="absolute right-0 top-full z-30 mt-2 min-w-56 flex-col items-start gap-3 rounded border border-line bg-surface p-3 shadow-lg md:static md:mt-0 md:flex md:min-w-0 md:flex-row md:items-center md:border-0 md:p-0 md:shadow-none"
          :class="toolsOpen ? 'flex' : 'hidden'"
        >
          <a :href="adminUrl" class="flex items-center gap-1 rounded px-2 py-1 text-sm text-kpi-blue-600 hover:bg-kpi-blue-50" data-testid="admin-link">
            <UIcon name="i-heroicons-cog-6-tooth" class="size-5" aria-hidden="true" />
            {{ $t('header.admin') }}
          </a>
          <SiteColorMode />
          <SiteLanguageSwitcher />
        </div>
      </div>
    </div>
  </header>
</template>
