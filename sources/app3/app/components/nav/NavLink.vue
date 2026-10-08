<script setup lang="ts">
import type { ResolvedLink } from '~/utils/navigation'

// One resolved menu link: internal (NuxtLink) or external to the legacy site / app2 (SITE_NAVIGATION.md § 4).
const props = defineProps<{ link: ResolvedLink, active?: boolean }>()

const external = computed(() => props.link.kind !== 'internal')
const hint = computed(() => (props.link.kind === 'legacy' ? 'a11y.legacyLink' : 'a11y.externalLink'))
</script>

<template>
  <NuxtLink
    :to="link.href"
    :external="external"
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
