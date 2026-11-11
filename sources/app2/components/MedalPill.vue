<template>
  <span
    class="group relative inline-flex gap-2 rounded-lg align-middle text-sm leading-tight"
    :class="stacked ? 'w-28 flex-col items-center text-center' : 'items-center'"
    tabindex="0"
    :aria-label="accessibleName"
    :data-medal="item.code"
  >
    <MedalShield :item="item" :size="shieldSize" :count="count" />
    <span v-if="!compact" aria-hidden="true">
      <b class="font-semibold">{{ name.main }}</b><span v-if="name.english" class="text-gray-500 italic"> · {{ name.english }}</span>
    </span>
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
import { medalExplanation, medalLabel, medalName, type MedalItem } from '~/utils/medals'

// Medal pill (FEATURE_MATCH_MEDALS.md § 7): pictogram in its family shield (counter on it), double name, tooltip. `stacked`: name under the shield.
const props = withDefaults(defineProps<{ item: MedalItem, count?: number, detail?: string, compact?: boolean, stacked?: boolean }>(), { count: 1, detail: '', compact: false, stacked: false })
const { locale, t } = useI18n()
const shieldSize = computed(() => (props.stacked ? 64 : props.compact ? 40 : 36))
const name = computed(() => medalName(props.item, locale.value))
const label = computed(() => medalLabel(props.item, locale.value))
const explanation = computed(() => medalExplanation(props.item, locale.value))
const accessibleName = computed(() => [props.count > 1 ? t('Medals.Times', { label: label.value, n: props.count }) : label.value, explanation.value, props.detail].filter(Boolean).join('. '))
</script>
