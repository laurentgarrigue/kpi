<script setup lang="ts">
import type { NuxtError } from '#app'

// Error page inside the site template, without technical details (SITE_LAYOUT.md § 2.5, LAY-07).
const props = defineProps<{ error: NuxtError }>()
const { t } = useI18n()
const localePath = useLocalePath()
const { legacyBaseUrl } = useRuntimeConfig().public

const notFound = computed(() => props.error.statusCode === 404)
useSiteSeo()
const titleKey = computed(() => (notFound.value ? 'error.notFoundTitle' : 'error.genericTitle'))
useSeoMeta({ title: () => t(titleKey.value) })
</script>

<template>
  <UApp>
    <NuxtLayout>
      <div class="space-y-4">
        <h1 class="text-5xl text-kpi-blue-600">{{ $t(titleKey) }}</h1>
        <p>{{ $t(notFound ? 'error.notFoundText' : 'error.genericText') }}</p>
        <div class="flex flex-wrap gap-3">
          <UButton :to="localePath('/')">{{ $t('error.backHome') }}</UButton>
          <UButton :to="legacyBaseUrl" external variant="outline">{{ $t('error.legacySite') }}</UButton>
        </div>
      </div>
    </NuxtLayout>
  </UApp>
</template>
