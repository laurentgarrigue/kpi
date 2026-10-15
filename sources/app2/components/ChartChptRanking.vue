<template>
  <div class="bg-surface rounded-lg p-4 shadow-sm border">
    <h6 class="font-semibold text-gray-700 mb-3">{{ t('Charts.ChampionshipRanking') }}</h6>
    <div v-if="chartRanking && chartRanking.length > 0">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="w-8 px-1 py-2" />
              <th class="px-3 py-2 text-left">#</th>
              <th class="px-3 py-2 text-left">{{ t('Charts.Team') }}</th>
              <th class="px-3 py-2 text-center">{{ t('Charts.Pts') }}</th>
              <th class="px-3 py-2 text-center">{{ t('Charts.Pld') }}</th>
              <th class="px-3 py-2 text-center">{{ t('Charts.W') }}</th>
              <th class="px-3 py-2 text-center">{{ t('Charts.D') }}</th>
              <th class="px-3 py-2 text-center">{{ t('Charts.L') }}</th>
              <th class="px-3 py-2 text-center">{{ t('Charts.GF') }}</th>
              <th class="px-3 py-2 text-center">{{ t('Charts.GA') }}</th>
              <th class="px-3 py-2 text-center">{{ t('Charts.GD') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(team, index) in chartRanking" :key="index" class="border-t">
              <td class="px-1 py-2 text-center">
                <RankingMark :team="team" :index="index" :total="chartRanking.length" :qualified="qualified" :eliminated="eliminated" />
              </td>
              <td class="px-3 py-2">{{ team.t_clt || 0 }}</td>
              <td class="px-3 py-2">
                <div class="flex items-center">
                  <img v-if="showFlags && team.t_logo" :src="getTeamLogo(team.t_logo)" class="h-8 w-8 mr-2" alt="" />
                  <TeamName
                    :team-label="team.t_label"
                    :team-id="team.t_id"
                    :is-winner="false"
                    :is-highlighted="team.t_highlighted"
                  />
                </div>
              </td>
              <td class="px-3 py-2 text-center font-bold">{{ team.t_pts || 0 }}</td>
              <td class="px-3 py-2 text-center">{{ team.t_pld || 0 }}</td>
              <td class="px-3 py-2 text-center">{{ team.t_won || 0 }}</td>
              <td class="px-3 py-2 text-center">{{ team.t_draw || 0 }}</td>
              <td class="px-3 py-2 text-center">{{ team.t_lost || 0 }}</td>
              <td class="px-3 py-2 text-center">{{ team.t_plus || 0 }}</td>
              <td class="px-3 py-2 text-center">{{ team.t_minus || 0 }}</td>
              <td class="px-3 py-2 text-center">{{ team.t_diff || 0 }}</td>
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
  }
})

const runtimeConfig = useRuntimeConfig()
const baseUrl = runtimeConfig.public.backendBaseUrl

const getTeamLogo = (logo) => {
  return `${baseUrl}/img/${logo}`
}
</script>