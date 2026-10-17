<script setup lang="ts">
import type { AdminCompetition } from '~/types/competitions'

// Short badges summarising a competition's ranking rules:
// goal average / points system (ICF = INT colour, FFCK = NAT colour), qualified / eliminated teams.
// Goal average and points do not apply to MULTI competitions.
const props = defineProps<{
  competition: Pick<AdminCompetition, 'codeTypeclt' | 'goalaverage' | 'points' | 'qualifies' | 'elimines'>
}>()

const { t } = useI18n()

const ICF_CLASS = 'bg-secondary-900 text-secondary-50'
const FFCK_CLASS = 'bg-secondary-700 text-secondary-50'

const isMulti = computed(() => props.competition.codeTypeclt === 'MULTI')
const isGaParticulier = computed(() => props.competition.goalaverage === 'part')
const isIcfPoints = computed(() => props.competition.points === '3-1-0-0')
</script>

<template>
  <div class="flex flex-wrap items-center gap-1">
    <template v-if="!isMulti">
      <span
        class="px-1.5 py-px text-[10px] font-medium rounded"
        :class="isGaParticulier ? FFCK_CLASS : ICF_CLASS"
        :title="`${t('competitions.form.goalaverage')} : ${t(`competitions.goalaverage_options.${isGaParticulier ? 'part_hint' : 'gen_hint'}`)}`"
      >
        {{ t('competitions.goalaverage_short') }} {{ t(`competitions.goalaverage_options.${isGaParticulier ? 'part' : 'gen'}`) }}
      </span>
      <span
        class="px-1.5 py-px text-[10px] font-medium rounded"
        :class="isIcfPoints ? ICF_CLASS : FFCK_CLASS"
        :title="`${t('competitions.form.points')} : ${t(`competitions.points_options.${isIcfPoints ? 'icf_hint' : 'ffck_hint'}`)}`"
      >
        {{ competition.points || '4-2-1-0' }}
      </span>
    </template>
    <span
      v-if="competition.qualifies > 0"
      class="inline-flex items-center gap-0.5 px-1.5 py-px text-[10px] font-medium rounded bg-header-100 dark:bg-header-800 text-header-900 dark:text-header-50"
      :title="t('competitions.form.qualifies')"
    >
      <UIcon name="heroicons:arrow-up-solid" class="w-3 h-3 text-success-600 dark:text-success-400" />
      {{ competition.qualifies }}
    </span>
    <span
      v-if="competition.elimines > 0"
      class="inline-flex items-center gap-0.5 px-1.5 py-px text-[10px] font-medium rounded bg-header-100 dark:bg-header-800 text-header-900 dark:text-header-50"
      :title="t('competitions.form.elimines')"
    >
      <UIcon name="heroicons:arrow-down-solid" class="w-3 h-3 text-danger-600 dark:text-danger-400" />
      {{ competition.elimines }}
    </span>
  </div>
</template>
