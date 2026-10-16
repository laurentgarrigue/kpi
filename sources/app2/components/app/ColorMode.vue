<script setup lang="ts">
// Light / dark / system theme selector, as in app3 and app4. The choice is kept by @nuxtjs/color-mode in the
// browser (storage key in nuxt.config.ts); colours follow the tokens of assets/css/theme.css.
const { t } = useI18n()
const colorMode = useColorMode()
const THEMES = [
  { value: 'light', icon: 'i-heroicons-sun' },
  { value: 'dark', icon: 'i-heroicons-moon' },
  { value: 'system', icon: 'i-heroicons-computer-desktop' },
] as const
</script>

<template>
  <div role="group" :aria-label="t('theme.label')" class="flex items-center gap-0.5 rounded border border-bar-hover p-0.5">
    <ClientOnly>
      <button
        v-for="theme in THEMES"
        :key="theme.value"
        type="button"
        class="rounded p-1 cursor-pointer"
        :class="colorMode.preference === theme.value ? 'bg-bar-hover text-green-400' : 'text-on-bar-muted hover:text-green-400'"
        :aria-pressed="colorMode.preference === theme.value"
        :title="t(`theme.${theme.value}`)"
        @click="colorMode.preference = theme.value"
      >
        <UIcon :name="theme.icon" class="block size-4" aria-hidden="true" />
        <span class="sr-only">{{ t(`theme.${theme.value}`) }}</span>
      </button>
      <template #fallback>
        <span class="block h-6 w-[4.75rem]" aria-hidden="true" />
      </template>
    </ClientOnly>
  </div>
</template>
