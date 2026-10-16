<script setup lang="ts">
import { NEW_TAB_ATTRS } from '~/utils/links'

// Link leaving app3; in a new tab, announced to assistive technologies (SITE_LAYOUT.md § 2.6, LAY-09).
const props = defineProps<{ href: string, newTab?: boolean }>()
const attrs = computed(() => (props.newTab ? NEW_TAB_ATTRS : {}))
</script>

<template>
  <a :href="href" v-bind="attrs">
    <slot />
    <span v-if="newTab" class="sr-only">{{ $t('a11y.newTab') }}</span>
  </a>
</template>
