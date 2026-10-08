<script setup lang="ts">
import { joinURL } from 'ufo'
import { NEW_TAB_ATTRS } from '~/utils/links'

// Header of the event and group views (EVT-02, GRP-02).
const props = defineProps<{ title: string, subtitle?: string, logo?: string | null, liveUrl: string }>()
const { legacyBaseUrl } = useRuntimeConfig().public
const logoUrl = computed(() => (props.logo ? joinURL(legacyBaseUrl, 'img', props.logo) : null))
</script>

<template>
  <header class="flex flex-wrap items-center gap-4" data-testid="scope-header">
    <img v-if="logoUrl" :src="logoUrl" alt="" class="size-20 object-contain">
    <div class="space-y-1">
      <h1 class="text-4xl text-kpi-blue-600 sm:text-5xl">{{ title }}</h1>
      <p v-if="subtitle" class="text-lg">{{ subtitle }}</p>
      <a :href="liveUrl" v-bind="NEW_TAB_ATTRS" class="text-sm underline" data-testid="scope-live">
        {{ $t('results.followLive') }}<span class="sr-only"> {{ $t('a11y.newTab') }}</span>
      </a>
    </div>
  </header>
</template>
