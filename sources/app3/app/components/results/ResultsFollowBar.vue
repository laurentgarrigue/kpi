<script setup lang="ts">
// Hint « touch a team to follow it », and « Stop following {team} » while one is followed (CMP-24).
defineProps<{ following: boolean, team: string }>()
const emit = defineEmits<{ stop: [] }>()
</script>

<template>
  <p class="flex flex-wrap items-baseline justify-between gap-x-5 gap-y-2 text-sm text-muted">
    <span>{{ $t('results.follow.hint') }}</span>
    <button v-if="following" type="button" class="text-kpi-blue-700 underline" data-testid="unfollow" @click="emit('stop')">
      {{ $t('results.follow.stop', { team }) }}
    </button>
  </p>
</template>
