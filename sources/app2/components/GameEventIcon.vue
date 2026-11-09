<template>
  <!-- A goal is the canoe polo ball (FEATURE_MATCH_MEDALS.md § 7.2), in the colour of its team -->
  <!-- eslint-disable-next-line vue/no-v-html -- static pictogram (utils/goalIcon.ts), not user content -->
  <svg v-if="type === 'B'" class="w-6 h-6" :class="goalColor" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" role="img" :aria-label="t('MatchSheet.Goal')" v-html="GOAL_ICON_SVG" />
  <div v-else-if="type === 'V'" class="w-5 h-7 bg-green-500 rounded-sm transform -rotate-12"/>
  <div v-else-if="type === 'J'" class="w-5 h-7 bg-yellow-400 rounded-sm transform -rotate-12"/>
  <div v-else-if="type === 'R'" class="relative w-5 h-7">
    <div class="absolute bg-yellow-400 w-5 h-7 rounded-sm transform -rotate-3 translate-x-1 translate-y-1"/>
    <div class="relative bg-red-500 rounded-sm transform -rotate-12 w-5 h-7"/>
  </div>
  <div v-else-if="type === 'D'" class="w-5 h-7 bg-red-600 rounded-sm transform -rotate-12 flex items-center justify-center text-white font-bold">E</div>
  <div v-else class="w-6 h-6 bg-gray-400 rounded-full flex items-center justify-center text-white text-xs">?</div>
</template>

<script setup>
import { computed } from 'vue'
import { GOAL_ICON_SVG } from '~/utils/goalIcon'

const props = defineProps({
  // B = goal, V = green card, J = yellow card, R = red card, D = definitive exclusion
  type: { type: String, required: true },
  // 'A' | 'B': colours the ball like the team's names; neutral otherwise
  team: { type: String, default: null }
})
const { t } = useI18n()
const goalColor = computed(() => ({
  A: 'text-blue-700 dark:text-blue-300',
  B: 'text-red-700 dark:text-red-400'
}[props.team] ?? 'text-bar dark:text-gray-200'))
</script>
