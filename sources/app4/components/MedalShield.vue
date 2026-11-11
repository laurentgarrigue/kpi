<template>
  <span
    class="relative inline-grid shrink-0 place-items-center align-middle"
    :class="FAMILY_CLASSES[item.family]"
    :style="{ width: `${size}px`, height: `${size * 1.15}px` }"
    :data-medal="item.code"
  >
    <svg class="absolute inset-0 h-full w-full overflow-visible" viewBox="0 0 40 46" aria-hidden="true">
      <path d="M2 2.5h36v22C38 35 30 41 20 44.5 10 41 2 35 2 24.5z" fill="currentColor" />
      <path d="M2 2.5h36v8.5H2z" fill="#fff" fill-opacity=".16" />
      <path d="M5.5 6h29v18.5c0 8-6 13-14.5 16.5C11.5 37.5 5.5 32.5 5.5 24.5z" fill="none" stroke="var(--color-shield-ink)" stroke-width=".6" stroke-opacity=".45" />
      <text x="6.2" y="10.4" font-size="4.4" font-weight="800" letter-spacing=".25" fill="var(--color-shield-ink)" fill-opacity=".55">KPI</text>
    </svg>
    <!-- eslint-disable-next-line vue/no-v-html -- pictogram of the api2 catalogue, not user content -->
    <svg class="relative -mt-[4%] text-shield-ink" :width="Math.round(size * 0.6)" :height="Math.round(size * 0.6)" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="markup" />
  </span>
</template>

<script setup lang="ts">
import { FAMILY_CLASSES, svgWithUniqueIds, type AdminMedalItem } from '~/utils/medals'

// Medal shield (FEATURE_MATCH_MEDALS.md § 7.3, MED-62), same drawing as app2 and app3: plain « écu français moderne »
// of the family colour, « KPI » watermark top left.
const props = withDefaults(defineProps<{ item: AdminMedalItem, size?: number }>(), { size: 40 })
const markup = computed(() => svgWithUniqueIds(props.item.icon_svg, props.item.code))
</script>
