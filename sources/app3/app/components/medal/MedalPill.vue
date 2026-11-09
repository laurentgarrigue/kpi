<script setup lang="ts">
import { FAMILY_CLASSES, medalExplanation, medalLabel, medalName, type MedalItem } from '~/utils/medals'

/**
 * Medal pill (FEATURE_MATCH_MEDALS.md § 7, MED-42, MED-43): pictogram on its family disc, double name, counter,
 * explanation (and moment of the goal) in a tooltip shown on hover and keyboard focus. `compact`: pictogram only.
 */
const props = withDefaults(defineProps<{ item: MedalItem, count?: number, detail?: string, compact?: boolean }>(), { count: 1, detail: '', compact: false })
const { locale, t } = useI18n()
const name = computed(() => medalName(props.item, locale.value))
const label = computed(() => medalLabel(props.item, locale.value))
const explanation = computed(() => medalExplanation(props.item, locale.value))
const family = computed(() => FAMILY_CLASSES[props.item.family])
const accessibleName = computed(() => [props.count > 1 ? t('medals.times', { label: label.value, n: props.count }) : label.value, explanation.value, props.detail].filter(Boolean).join('. '))
</script>

<template>
  <span
    class="group relative inline-flex items-center gap-1.5 rounded-full border border-line bg-surface align-middle text-sm leading-tight"
    :class="compact ? 'p-0.5' : 'py-0.5 pr-2.5 pl-0.5'"
    tabindex="0"
    :aria-label="accessibleName"
    :data-medal="item.code"
    data-testid="medal-pill"
  >
    <span class="inline-grid size-7 shrink-0 place-items-center rounded-full ring-1" :class="[family.text, family.disc]">
      <MedalIcon :item="item" :size="compact ? 16 : 18" />
    </span>
    <span v-if="!compact" aria-hidden="true">
      <b class="font-semibold">{{ name.main }}</b><span v-if="name.english" class="text-muted italic"> · {{ name.english }}</span>
    </span>
    <span v-if="count > 1" class="pr-1 font-bold tabular-nums" :class="family.text" aria-hidden="true">×{{ count }}</span>
    <span
      role="tooltip"
      class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 hidden w-max max-w-64 -translate-x-1/2 rounded-md bg-navy px-2.5 py-2 text-left text-xs leading-snug text-white shadow-lg group-hover:block group-focus-visible:block"
      aria-hidden="true"
    >
      <b v-if="compact" class="block">{{ label }}</b>{{ explanation }}<span v-if="detail" class="block text-sky">{{ detail }}</span>
    </span>
  </span>
</template>
