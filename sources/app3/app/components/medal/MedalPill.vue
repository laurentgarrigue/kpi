<script setup lang="ts">
import { medalExplanation, medalLabel, medalName, type MedalItem } from '~/utils/medals'

/**
 * Medal pill (FEATURE_MATCH_MEDALS.md § 7, MED-42, MED-43): pictogram in its family shield (counter on the shield), double name,
 * explanation (and moment of the goal) in a tooltip shown on hover and keyboard focus. `compact`: pictogram only.
 */
const props = withDefaults(defineProps<{ item: MedalItem, count?: number, detail?: string, compact?: boolean }>(), { count: 1, detail: '', compact: false })
const { locale, t } = useI18n()
const name = computed(() => medalName(props.item, locale.value))
const label = computed(() => medalLabel(props.item, locale.value))
const explanation = computed(() => medalExplanation(props.item, locale.value))
const accessibleName = computed(() => [props.count > 1 ? t('medals.times', { label: label.value, n: props.count }) : label.value, explanation.value, props.detail].filter(Boolean).join('. '))
</script>

<template>
  <span
    class="group relative inline-flex items-center gap-2 rounded-lg align-middle text-sm leading-tight"
    tabindex="0"
    :aria-label="accessibleName"
    :data-medal="item.code"
    data-testid="medal-pill"
  >
    <MedalShield :item="item" :size="compact ? 40 : 36" :count="count" />
    <span v-if="!compact" aria-hidden="true">
      <b class="font-semibold">{{ name.main }}</b><span v-if="name.english" class="text-muted italic"> · {{ name.english }}</span>
    </span>
    <span
      role="tooltip"
      class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 hidden w-max max-w-64 -translate-x-1/2 rounded-md bg-navy px-2.5 py-2 text-left text-xs leading-snug text-white shadow-lg group-hover:block group-focus-visible:block"
      aria-hidden="true"
    >
      <b v-if="compact" class="block">{{ label }}</b>{{ explanation }}<span v-if="detail" class="block text-sky">{{ detail }}</span>
    </span>
  </span>
</template>
