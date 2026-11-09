<template>
  <span
    class="group relative inline-flex items-center gap-1.5 rounded-full border border-gray-300 bg-surface align-middle text-sm leading-tight"
    :class="compact ? 'p-0.5' : 'py-0.5 pr-2.5 pl-0.5'"
    tabindex="0"
    :aria-label="accessibleName"
    :data-medal="item.code"
  >
    <span class="inline-grid h-7 w-7 shrink-0 place-items-center rounded-full ring-1" :class="[family.text, family.disc]">
      <!-- eslint-disable-next-line vue/no-v-html -- pictogram of the api2 catalogue, not user content -->
      <svg :width="compact ? 16 : 18" :height="compact ? 16 : 18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="markup" />
    </span>
    <span v-if="!compact" aria-hidden="true">
      <b class="font-semibold">{{ name.main }}</b><span v-if="name.english" class="text-gray-500 italic"> · {{ name.english }}</span>
    </span>
    <span v-if="count > 1" class="pr-1 font-bold tabular-nums" :class="family.text" aria-hidden="true">×{{ count }}</span>
    <span
      role="tooltip"
      aria-hidden="true"
      class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 hidden w-max max-w-64 -translate-x-1/2 rounded-md bg-bar px-2.5 py-2 text-left text-xs leading-snug text-white shadow-lg group-hover:block group-focus-visible:block"
    >
      <b v-if="compact" class="block">{{ label }}</b>{{ explanation }}<span v-if="detail" class="block text-on-bar-muted">{{ detail }}</span>
    </span>
  </span>
</template>

<script setup lang="ts">
import { FAMILY_CLASSES, medalExplanation, medalLabel, medalName, svgWithUniqueIds, type MedalItem } from '~/utils/medals'

// Medal pill (FEATURE_MATCH_MEDALS.md § 7): pictogram on its family disc, double name, counter, tooltip.
const props = withDefaults(defineProps<{ item: MedalItem, count?: number, detail?: string, compact?: boolean }>(), { count: 1, detail: '', compact: false })
const { locale, t } = useI18n()
const id = useId()
const markup = computed(() => svgWithUniqueIds(props.item.icon_svg, id.replace(/[^a-z0-9-]/gi, '')))
const name = computed(() => medalName(props.item, locale.value))
const label = computed(() => medalLabel(props.item, locale.value))
const explanation = computed(() => medalExplanation(props.item, locale.value))
const family = computed(() => FAMILY_CLASSES[props.item.family])
const accessibleName = computed(() => [props.count > 1 ? t('Medals.Times', { label: label.value, n: props.count }) : label.value, explanation.value, props.detail].filter(Boolean).join('. '))
</script>
