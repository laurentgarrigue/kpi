<template>
  <span
    class="relative inline-grid shrink-0 place-items-center align-middle"
    :class="family.text"
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
    <svg class="relative -mt-[4%] text-shield-ink" :width="iconSize" :height="iconSize" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="markup" />
    <MedalCounter v-if="count > 1" :count="count" :size="size" />
  </span>
</template>

<script setup lang="ts">
import { FAMILY_CLASSES, svgWithUniqueIds, type MedalItem } from '~/utils/medals'

// Medal shield (FEATURE_MATCH_MEDALS.md § 7.3, MED-62): the pictogram in a plain « écu français moderne » of its family
// colour, « KPI » watermark top left, counter pastille. 64 px stacked, 36 px next to a name, 40 px in tables.
const props = withDefaults(defineProps<{ item: MedalItem, size?: number, count?: number }>(), { size: 56, count: 1 })
const id = useId()
const markup = computed(() => svgWithUniqueIds(props.item.icon_svg, id.replace(/[^a-z0-9-]/gi, '')))
const family = computed(() => FAMILY_CLASSES[props.item.family])
const iconSize = computed(() => Math.round(props.size * 0.6))
</script>
