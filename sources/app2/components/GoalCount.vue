<template>
  <!-- Goals of a player: the ball and, from two goals, the same count pastille as the medals (FEATURE_MATCH_MEDALS.md § 9.1, MED-65) -->
  <span v-if="count > 0" class="relative inline-grid place-items-center align-middle text-gray-900" role="img" :class="count > 1 ? 'mr-4' : ''" :aria-label="t('Stats.GoalCount', { n: count }, count)" style="width: 26px; height: 26px">
    <!-- eslint-disable-next-line vue/no-v-html -- static pictogram (utils/goalIcon.ts), not user content -->
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true" v-html="GOAL_ICON_SVG" />
    <MedalCounter v-if="count > 1" :count="count" :size="46" placement="-right-3.5 -bottom-1" />
  </span>
</template>

<script setup lang="ts">
import { GOAL_ICON_SVG } from '~/utils/goalIcon'

defineProps<{ count: number }>()
const { t } = useI18n()
</script>
