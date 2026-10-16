<template>
  <span
    v-if="mark === 'medal'"
    class="inline-flex size-6 items-center justify-center rounded-full text-xs font-bold"
    :class="MEDAL_CLASSES[team.t_medal]"
    :title="t('Charts.Medal', { rank: team.t_medal })"
  >
    {{ team.t_medal }}
  </span>
  <span v-else-if="mark === 'qualified'" class="inline-flex text-green-600" :title="t('Charts.Qualified')">
    <UIcon name="i-heroicons-arrow-up-circle" class="size-5" aria-hidden="true" />
    <span class="sr-only">{{ t('Charts.Qualified') }}</span>
  </span>
  <span v-else-if="mark === 'eliminated'" class="inline-flex text-red-600" :title="t('Charts.Eliminated')">
    <UIcon name="i-heroicons-arrow-down-circle" class="size-5" aria-hidden="true" />
    <span class="sr-only">{{ t('Charts.Eliminated') }}</span>
  </span>
</template>

<script setup>
// Mark of a final ranking line, as in app3 (ResultsRankingMark): the medal computed by api2 (top 3 of a finished
// final round) first, otherwise « qualified » for the first `qualified` lines and « eliminated » for the last
// `eliminated` ones (Qualifies / Elimines of the competition).
const { t } = useI18n()

const props = defineProps({
  team: { type: Object, required: true },
  index: { type: Number, required: true },
  total: { type: Number, required: true },
  qualified: { type: Number, default: 0 },
  eliminated: { type: Number, default: 0 }
})

const MEDAL_CLASSES = {
  1: 'bg-yellow-400 text-black',
  2: 'bg-gray-300 text-gray-900',
  3: 'bg-amber-700 text-white'
}

const mark = computed(() => {
  if (props.team.t_medal) return 'medal'
  if (props.index < props.qualified) return 'qualified'
  if (props.index >= props.total - props.eliminated) return 'eliminated'
  return null
})
</script>
