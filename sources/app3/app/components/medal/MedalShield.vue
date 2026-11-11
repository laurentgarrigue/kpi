<script setup lang="ts">
import { FAMILY_CLASSES, type MedalItem } from '~/utils/medals'

/**
 * Medal shield (FEATURE_MATCH_MEDALS.md § 7.3, MED-62): the pictogram in a plain « écu français moderne » of its
 * family colour, with the « KPI » watermark top left and the counter as a pastille. 56 px by default, 40 px in
 * tables, 88 px on the explanations page.
 */
const props = withDefaults(defineProps<{ item: MedalItem, size?: number, count?: number }>(), { size: 56, count: 1 })
const family = computed(() => FAMILY_CLASSES[props.item.family])
const counterSize = computed(() => `${Math.max(11, Math.round(props.size * 0.24))}px`)
</script>

<template>
  <span
    class="relative inline-grid shrink-0 place-items-center align-middle"
    :class="family.text"
    :style="{ width: `${size}px`, height: `${size * 1.15}px` }"
    :data-medal="item.code"
    data-testid="medal-shield"
  >
    <svg class="absolute inset-0 size-full overflow-visible" viewBox="0 0 40 46" aria-hidden="true">
      <path d="M2 2.5h36v22C38 35 30 41 20 44.5 10 41 2 35 2 24.5z" fill="currentColor" />
      <path d="M2 2.5h36v8.5H2z" fill="#fff" fill-opacity=".16" />
      <path d="M5.5 6h29v18.5c0 8-6 13-14.5 16.5C11.5 37.5 5.5 32.5 5.5 24.5z" fill="none" stroke="var(--color-shield-ink)" stroke-width=".6" stroke-opacity=".45" />
      <text x="6.2" y="10.4" font-size="4.4" font-weight="800" letter-spacing=".25" fill="var(--color-shield-ink)" fill-opacity=".55">KPI</text>
    </svg>
    <MedalIcon :item="item" :size="Math.round(size * 0.6)" class="relative -mt-[4%] text-shield-ink" />
    <span
      v-if="count > 1"
      class="absolute -right-[9%] -bottom-[2%] min-w-[1.7em] rounded-full bg-surface px-1.5 text-center leading-[1.25] font-bold tabular-nums ring-[1.5px] ring-current"
      :style="{ fontSize: counterSize }"
      data-testid="medal-shield-count"
      aria-hidden="true"
    >×{{ count }}</span>
  </span>
</template>
