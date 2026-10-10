<script setup lang="ts">
import { NEW_TAB_ATTRS } from '~/utils/links'

// « Follow live » call to action, opened in the live application: a red button with a pulsing dot, easy to spot
// among the plain links of the headers (CMP-02, EVT-02).
defineProps<{ href: string }>()
</script>

<template>
  <a :href="href" v-bind="NEW_TAB_ATTRS" class="inline-flex items-center gap-2 rounded bg-kpi-red-600 px-4 py-2 font-semibold text-white shadow-sm hover:bg-kpi-red-700" data-live-button>
    <span class="size-2.5 animate-pulse rounded-full bg-white motion-reduce:animate-none" aria-hidden="true" />
    {{ $t('results.followLive') }}<span class="sr-only"> {{ $t('a11y.newTab') }}</span>
  </a>
</template>
