<script setup lang="ts">
// Light / dark / system theme selector, as in app4 (SITE_LAYOUT.md § 3, LAY-13). The choice is kept by
// @nuxtjs/color-mode in the browser; rendered on the client only, since the server does not know it.
const colorMode = useColorMode()
const THEMES = [
  { value: 'light', icon: 'i-heroicons-sun' },
  { value: 'dark', icon: 'i-heroicons-moon' },
  { value: 'system', icon: 'i-heroicons-computer-desktop' },
] as const
</script>

<template>
  <div role="group" :aria-label="$t('theme.label')" class="flex items-center gap-0.5 rounded border border-line p-0.5" data-testid="color-mode">
    <ClientOnly>
      <button
        v-for="theme in THEMES"
        :key="theme.value"
        type="button"
        class="rounded p-1"
        :class="colorMode.preference === theme.value ? 'bg-kpi-blue-500 text-white' : 'text-kpi-blue-600 hover:bg-kpi-blue-50'"
        :aria-pressed="colorMode.preference === theme.value"
        :title="$t(`theme.${theme.value}`)"
        :data-theme="theme.value"
        @click="colorMode.preference = theme.value"
      >
        <UIcon :name="theme.icon" class="block size-4" aria-hidden="true" />
        <span class="sr-only">{{ $t(`theme.${theme.value}`) }}</span>
      </button>
      <template #fallback>
        <span class="block h-6 w-[4.75rem]" aria-hidden="true" />
      </template>
    </ClientOnly>
  </div>
</template>
