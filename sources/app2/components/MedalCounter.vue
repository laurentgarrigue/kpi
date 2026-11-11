<template>
  <!-- The « ×13 » pastille of a medal and of the goals of a player (FEATURE_MATCH_MEDALS.md § 7.3, § 9.1): current colour -->
  <span
    class="absolute min-w-[1.7em] rounded-full bg-surface px-1.5 text-center leading-[1.25] font-bold tabular-nums ring-[1.5px] ring-current"
    :class="placement"
    :style="{ fontSize: `${Math.max(11, Math.round(size * 0.24))}px` }"
    aria-hidden="true"
  >×{{ count }}</span>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ count: number, size: number, placement?: string }>(), { placement: '-right-[9%] -bottom-[2%]' })
</script>
