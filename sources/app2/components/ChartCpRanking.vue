<template>
  <div class="bg-surface rounded-lg p-4 shadow-sm border">
    <h6 class="font-semibold text-gray-700 mb-3">
      {{ chartStatus === 'END' ? t('Charts.FinalRanking') : t('Charts.ProvisionalRanking') }}
    </h6>
    <div v-if="chartRanking && chartRanking.length > 0">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="w-8 px-1 py-3" />
              <th class="px-6 py-3 text-center w-20">{{ t('Charts.Ranking') }}</th>
              <th class="px-6 py-3 text-left">{{ t('Charts.Team') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(team, index) in chartRanking" :key="index" class="border-t">
              <td class="px-1 py-3 text-center">
                <RankingMark :team="team" :index="index" :total="chartRanking.length" :qualified="qualified" :eliminated="eliminated" />
              </td>
              <td class="px-6 py-3 text-center font-bold text-lg">{{ team.t_clt_cp || 0 }}</td>
              <td class="px-6 py-3">
                <div class="flex items-center">
                  <img v-if="showFlags && team.t_logo" :src="getTeamLogo(team.t_logo)" class="h-8 w-8 mr-3" alt="" />
                  <TeamName
                    :team-label="team.t_label"
                    :team-id="team.t_id"
                    :is-winner="false"
                    :is-highlighted="team.t_highlighted"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <div v-else class="text-gray-500 text-sm">
      {{ t('Charts.NoData') }}
    </div>
  </div>
</template>

<script setup>
import TeamName from '~/components/TeamName.vue'
import RankingMark from '~/components/RankingMark.vue'

const { t } = useI18n()

defineProps({
  chartRanking: {
    type: Array,
    default: () => []
  },
  chartStatus: {
    type: String,
    default: ''
  },
  showFlags: {
    type: Boolean,
    default: true
  },
  qualified: {
    type: Number,
    default: 0
  },
  eliminated: {
    type: Number,
    default: 0
  },
  competitionType: {
    type: String,
    default: ''
  }
})

const runtimeConfig = useRuntimeConfig()
const baseUrl = runtimeConfig.public.backendBaseUrl

const getTeamLogo = (logo) => {
  return `${baseUrl}/img/${logo}`
}
</script>