<script setup lang="ts">
import { NEW_TAB_ATTRS } from '~/utils/links'

// « Follow live » call to action, opened in the live application: red (the colour of the live only) with a pulsing
// dot, easy to spot among the plain links of the headers (CMP-02, EVT-02).
defineProps<{ href: string }>()
</script>

<template>
  <a :href="href" v-bind="NEW_TAB_ATTRS" class="inline-flex items-center gap-2 rounded-md bg-live px-3 py-1.5 text-sm font-semibold text-white shadow-sm hover:brightness-110 md:px-3.5 md:py-2" data-live-button>
    <span class="size-2 animate-pulse rounded-full bg-white motion-reduce:animate-none" aria-hidden="true" />
    {{ $t('results.followLive') }}<span class="sr-only"> {{ $t('a11y.newTab') }}</span>
  </a>
</template>
