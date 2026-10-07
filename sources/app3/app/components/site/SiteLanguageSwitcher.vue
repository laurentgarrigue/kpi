<script setup lang="ts">
// FR | EN switch leading to the same page in the other language (SITE_NAVIGATION.md § 5, NAV-09).
const { locale, locales } = useI18n()
const switchLocalePath = useSwitchLocalePath()
</script>

<template>
  <nav :aria-label="$t('a11y.language')">
    <ul class="flex items-center gap-1 text-sm font-semibold">
      <li v-for="item in locales" :key="item.code">
        <NuxtLink
          :to="switchLocalePath(item.code)"
          :lang="item.language"
          :aria-current="item.code === locale ? 'true' : undefined"
          :title="item.name"
          class="rounded px-2 py-1 uppercase"
          :class="item.code === locale ? 'bg-kpi-blue-500 text-white' : 'text-kpi-blue-600 hover:bg-kpi-blue-50'"
        >
          {{ item.code }}
        </NuxtLink>
      </li>
    </ul>
  </nav>
</template>
