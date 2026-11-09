<template>
  <section v-if="hasMedals || game.reliable === false" class="mt-4 border border-gray-300 rounded-lg bg-surface" aria-labelledby="medals-title">
    <div class="flex items-center justify-between rounded-t-lg bg-bar px-3 py-2 text-sm text-white">
      <h2 id="medals-title" class="font-bold">{{ t('Medals.Title') }}</h2>
      <a :href="explanationsUrl" target="_blank" rel="noopener" class="text-on-bar-muted underline">{{ t('Medals.How') }}</a>
    </div>
    <div v-if="labels.length" class="flex flex-wrap gap-1.5 px-3 pt-3">
      <MedalPill v-for="item in labels" :key="item.code" :item="item" />
    </div>
    <p v-if="game.reliable === false" class="mx-3 mt-3 rounded-md border border-dashed border-gray-300 bg-gray-100 px-2.5 py-2 text-sm text-gray-700">
      {{ t('Medals.Incomplete') }}
    </p>
    <div class="grid grid-cols-1 md:grid-cols-2">
      <div v-for="side in SIDES" :key="side" class="px-3 py-3" :class="{ 'border-t md:border-t-0 md:border-l border-gray-300': side === 'B' }">
        <h3 class="mb-2 text-sm font-bold">{{ side === 'A' ? teamALabel : teamBLabel }}</h3>
        <div v-if="teamMedals(side).length" class="flex flex-wrap gap-1.5">
          <MedalPill v-for="medal in teamMedals(side)" :key="medal.item.code" :item="medal.item" :count="medal.count" />
        </div>
        <p v-else class="text-sm text-gray-500">{{ t('Medals.NoTeamMedal') }}</p>
        <ul v-if="players(side).length" class="mt-2 space-y-1.5">
          <li v-for="player in players(side)" :key="player.key" class="flex flex-wrap items-center gap-1.5 text-sm">
            <MedalPill v-for="medal in player.medals" :key="medal.item.code" :item="medal.item" :count="medal.count" compact />
            <span><b>{{ player.name }}</b> <small v-if="player.number !== null" class="text-gray-500">#{{ player.number }}</small></span>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import type { MedalItem } from '~/utils/medals'

/**
 * « Medals » block of the score sheet (FEATURE_MATCH_MEDALS.md § 9.1, MED-45/46): game labels, then one column per
 * team (team medals named, player medals compact before the name). Only what api2 publishes on app2.
 */
interface GameSheetMedals {
  reliable: boolean | null
  labels: string[]
  team: { code: string, side: 'A' | 'B', count: number }[]
  player: { code: string, side: 'A' | 'B', first_name: string | null, last_name: string | null, number: number | null }[]
  referee: { code: string }[]
}

const SIDES = ['A', 'B'] as const
const props = defineProps<{ game: GameSheetMedals, catalog: Map<string, MedalItem>, teamALabel: string, teamBLabel: string }>()
const { t, locale } = useI18n()
const { publicSiteUrl } = useRuntimeConfig().public
const explanationsUrl = computed(() => `${publicSiteUrl}${locale.value === 'en' ? '/en' : ''}/medals`)

const known = (code: string) => props.catalog.get(code)
const labels = computed(() => props.game.labels.flatMap(code => known(code) ?? []))
const teamMedals = (side: 'A' | 'B') => props.game.team
  .filter(medal => medal.side === side && known(medal.code))
  .map(medal => ({ item: known(medal.code)!, count: medal.count }))
const players = (side: 'A' | 'B') => {
  const byPlayer = new Map<string, { key: string, name: string, number: number | null, medals: Map<string, { item: MedalItem, count: number }> }>()
  for (const medal of props.game.player.filter(entry => entry.side === side && known(entry.code))) {
    const key = `${medal.last_name}-${medal.first_name}-${medal.number}`
    const player = byPlayer.get(key) ?? { key, name: [medal.first_name, medal.last_name].filter(Boolean).join(' '), number: medal.number, medals: new Map() }
    const current = player.medals.get(medal.code)
    player.medals.set(medal.code, { item: known(medal.code)!, count: (current?.count ?? 0) + 1 })
    byPlayer.set(key, player)
  }
  return [...byPlayer.values()].map(player => ({ ...player, medals: [...player.medals.values()] }))
}
const hasMedals = computed(() => labels.value.length > 0 || SIDES.some(side => teamMedals(side).length || players(side).length))
</script>
