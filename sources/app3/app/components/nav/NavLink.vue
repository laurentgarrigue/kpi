<script setup lang="ts">
import { NEW_TAB_ATTRS } from '~/utils/links'
import type { ResolvedLink } from '~/utils/navigation'

// One resolved menu link: internal (NuxtLink) or external to the legacy site / app2 (SITE_NAVIGATION.md § 4).
const props = defineProps<{ link: ResolvedLink, active?: boolean }>()

const external = computed(() => props.link.kind !== 'internal')
// app2 opens in a new tab (SITE_LAYOUT.md § 2.6); the legacy site in the same tab.
const newTab = computed(() => props.link.kind === 'app2')
const hint = computed(() => (props.link.kind === 'legacy' ? 'a11y.legacyLink' : 'a11y.newTab'))
</script>

<template>
  <NuxtLink
    :to="link.href"
    :external="external"
    v-bind="newTab ? NEW_TAB_ATTRS : {}"
    :aria-current="active ? 'page' : undefined"
    :data-kind="link.kind"
    class="flex items-center gap-1"
  >
    {{ $t(`nav.${link.id}`) }}
    <template v-if="external">
      <UIcon name="i-heroicons-arrow-top-right-on-square" class="size-4 opacity-70" aria-hidden="true" />
      <span class="sr-only">{{ $t(hint) }}</span>
    </template>
  </NuxtLink>
</template>
