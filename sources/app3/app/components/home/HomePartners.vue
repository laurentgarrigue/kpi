<script setup lang="ts">
import { NEW_TAB_ATTRS } from '~/utils/links'
import type { PartnersBlock } from '~/utils/home'

// Partners, closing the home page (PAGE_CONTENT.md § 4, CNT-05).
defineProps<{ block: PartnersBlock }>()
</script>

<template>
  <section aria-labelledby="home-partners" class="space-y-4 border-t border-line pt-8" data-testid="home-partners">
    <h2 id="home-partners" class="text-[1.875rem] leading-none text-kpi-blue-700">{{ $t('home.partners') }}</h2>
    <ul class="flex flex-wrap items-center gap-8">
      <li v-for="partner in block.partners" :key="partner.url || partner.name">
        <a :href="partner.url" v-bind="NEW_TAB_ATTRS" class="block">
          <ContentImage v-if="partner.logo" :media="{ ...partner.logo, alt: partner.name }" :width="320" class="kpi-logo h-16 w-auto object-contain" />
          <span v-else class="font-semibold">{{ partner.name }}</span>
          <span class="sr-only">{{ $t('a11y.newTab') }}</span>
        </a>
      </li>
    </ul>
  </section>
</template>
