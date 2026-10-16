<script setup lang="ts">
// Breadcrumb trail; the last item is the current page (SITE_LAYOUT.md § 7, introduced in phase 2).
defineProps<{ items: { label: string, to?: string }[] }>()
</script>

<template>
  <nav :aria-label="$t('a11y.breadcrumb')" class="text-sm" data-testid="breadcrumb">
    <ol class="flex flex-wrap items-center gap-1 text-ink/70">
      <li v-for="(item, index) in items" :key="index" class="flex items-center gap-1">
        <UIcon v-if="index > 0" name="i-heroicons-chevron-right" class="size-3" aria-hidden="true" />
        <NuxtLink v-if="item.to && index < items.length - 1" :to="item.to" class="hover:underline">{{ item.label }}</NuxtLink>
        <span v-else aria-current="page" class="text-ink">{{ item.label }}</span>
      </li>
    </ol>
  </nav>
</template>
