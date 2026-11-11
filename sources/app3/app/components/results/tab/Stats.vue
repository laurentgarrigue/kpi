<script setup lang="ts">
import type { CompetitionDetails, Stat } from '#kpi-layer/utils/results/types'
import { isTimelineStat, medalLabel } from '~/utils/medals'

// « Stats » tab: available statistics, generic table, 20 lines then « see more » (100) — CMP-11.
const DEFAULT_LIMIT = 20
const MORE_LIMIT = 100
const props = defineProps<{ competition: CompetitionDetails }>()
const { apiPath } = useCompetitionTab(() => props.competition, 'stats')
const route = useRoute()

const { data: available } = await useApiResource<{ kinds: string[] }>(() => apiPath('stats'))
const kinds = computed(() => available.value?.kinds ?? [])
const kind = computed(() => (typeof route.query.stat === 'string' && kinds.value.includes(route.query.stat) ? route.query.stat : kinds.value[0] ?? null))
const limit = computed(() => (route.query.limit === String(MORE_LIMIT) ? MORE_LIMIT : DEFAULT_LIMIT))
const { data: stat, error } = await useApiResource<Stat>(() => (kind.value ? apiPath(`stats/${kind.value}?limit=${limit.value}`) : null))
// Timeline statistics (FEATURE_MATCH_MEDALS.md § 3.4): names, explanations and pictograms from the catalogue.
const { locale, t } = useI18n()
const { byCode } = await useMedalCatalog(() => props.competition.season)
const kindLabel = (kind: string) => {
  const item = byCode.value.get(kind)
  return item ? medalLabel(item, locale.value) : t(`stats.${kind}.title`)
}
// A players statistic with one figure reads as bars (CMP-22); any other keeps the generic table (CMP-11).
const asBars = computed(() => !!stat.value && stat.value.columns.length === 1 && stat.value.rows.some(row => 'last_name' in row))
</script>

<template>
  <div class="space-y-4">
    <nav v-if="kinds.length > 1" :aria-label="$t('results.statsKinds')" class="flex flex-wrap gap-2" data-testid="stat-kinds">
      <NuxtLink
        v-for="item in kinds"
        :key="item"
        :to="{ path: route.path, query: { ...route.query, stat: item, limit: undefined, team: undefined } }"
        :aria-current="item === kind ? 'page' : undefined"
        class="rounded border px-3 py-1 text-sm"
        :class="item === kind ? 'border-navy bg-navy text-white' : 'border-line hover:bg-wash'"
      >
        {{ kindLabel(item) }}
      </NuxtLink>
    </nav>
    <p v-if="error" class="text-kpi-red-600" data-testid="results-unavailable">{{ $t('results.unavailable') }}</p>
    <p v-else-if="!stat || stat.rows.length === 0" data-testid="no-stats">{{ $t('results.noStats') }}</p>
    <MedalStatView v-else-if="isTimelineStat(stat)" :stat="stat" :item="byCode.get(stat.kind)" :by-code="byCode" :competition="competition.code" />
    <template v-else>
      <h2 class="text-3xl text-kpi-blue-700">{{ $t(`stats.${stat.kind}.title`) }}</h2>
      <ResultsStatBars v-if="asBars" :stat="stat" :competition="competition.code" />
      <ResultsStatTable v-else :stat="stat" :competition="competition.code" />
      <NuxtLink
        v-if="limit === DEFAULT_LIMIT && stat.rows.length === DEFAULT_LIMIT"
        :to="{ path: route.path, query: { ...route.query, limit: MORE_LIMIT } }"
        class="inline-block underline"
        data-testid="stats-more"
      >
        {{ $t('results.seeMore') }}
      </NuxtLink>
    </template>
  </div>
</template>
