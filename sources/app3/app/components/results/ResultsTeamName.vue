<script setup lang="ts">
import { teamLink } from '~/utils/page-links'

// Team name linking to its team page (legacy until /teams is delivered, CMP-12); waiting labels are plain text.
const props = defineProps<{ label: string | null, number: number | null, competition: string, strong?: boolean }>()
const context = usePageLinkContext()
const link = computed(() => (props.number === null ? null : teamLink({ number: props.number, competition: props.competition }, context.value)))
</script>

<template>
  <a v-if="link && label" :href="link.href" class="hover:underline" :class="{ 'font-semibold': strong }" data-kind="legacy">
    {{ label }}<span class="sr-only"> {{ $t('a11y.legacyLink') }}</span>
  </a>
  <span v-else class="italic text-ink/70">{{ label ?? '—' }}</span>
</template>
