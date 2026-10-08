<script setup lang="ts">
import { joinURL } from 'ufo'
import type { GroupCompetition } from '#kpi-layer/utils/results/types'
import { competitionPath } from '~/utils/competitions'

// One competition of the list: visual, title, badges, compact ranking (8 teams, then « see all ») — CPL-04..07.
const COMPACT_RANKING_SIZE = 8
const props = defineProps<{ competition: GroupCompetition }>()
const localePath = useLocalePath()
const { legacyBaseUrl } = useRuntimeConfig().public

const visual = computed(() => {
  const path = props.competition.banner ?? props.competition.logo
  return path ? (path.startsWith('http') ? path : joinURL(legacyBaseUrl, 'img', path)) : null
})
const hasMore = computed(() => props.competition.ranking.length > COMPACT_RANKING_SIZE)
const caption = computed(() => props.competition.display_title)
</script>

<template>
  <article class="flex flex-col gap-3 rounded border border-line p-4" :data-competition="competition.code">
    <img v-if="visual" :src="visual" alt="" loading="lazy" class="max-h-20 self-start object-contain">
    <h2 class="text-2xl">
      <NuxtLink :to="localePath(competitionPath(competition.season, competition.code))" class="text-kpi-blue-600 hover:underline">
        {{ competition.display_title }}
      </NuxtLink>
    </h2>
    <p v-if="competition.soustitre2" class="-mt-2 text-sm">{{ competition.soustitre2 }}</p>
    <p class="flex gap-2">
      <UBadge variant="subtle" color="neutral">{{ $t(`results.type.${competition.type}`) }}</UBadge>
      <UBadge variant="subtle" :color="competition.status === 'ON' ? 'error' : 'primary'">{{ $t(`results.competitionStatus.${competition.status}`) }}</UBadge>
    </p>
    <p v-if="competition.ranking.length === 0" class="text-sm italic" data-testid="no-ranking">{{ $t('results.noRanking') }}</p>
    <template v-else>
      <ResultsRankingTable
        :rows="competition.ranking"
        :type="competition.type"
        :competition="competition.code"
        :qualified="competition.qualified"
        :eliminated="competition.eliminated"
        :caption="caption"
        :to="COMPACT_RANKING_SIZE"
        compact
      />
      <details v-if="hasMore" data-testid="ranking-more">
        <summary class="cursor-pointer text-sm underline">{{ $t('results.seeAll') }}</summary>
        <ResultsRankingTable
          :rows="competition.ranking"
          :type="competition.type"
          :competition="competition.code"
          :qualified="competition.qualified"
          :eliminated="competition.eliminated"
          :caption="caption"
          :from="COMPACT_RANKING_SIZE"
          compact
        />
      </details>
    </template>
  </article>
</template>
