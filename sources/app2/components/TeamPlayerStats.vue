<template>
  <div class="overflow-x-auto">
    <table class="w-full">
      <thead :class="headClass">
        <tr>
          <th class="py-2 px-3 border-b text-left">{{ t('Stats.Player') }}</th>
          <th class="py-2 px-2 border-b text-center">
            <!-- The ball stands for « Goals » (FEATURE_MATCH_MEDALS.md § 7.2) -->
            <svg class="inline-block h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" role="img" :aria-label="t('Stats.Goals')" v-html="GOAL_ICON_SVG" />
          </th>
          <th class="py-2 px-2 border-b text-center">
            <div class="inline-block bg-green-500 w-6 h-8 transform -rotate-12 rounded-sm"></div>
          </th>
          <th class="py-2 px-2 border-b text-center">
            <div class="inline-block bg-yellow-400 w-6 h-8 transform -rotate-12 rounded-sm"></div>
          </th>
          <th class="py-2 px-2 border-b text-center">
            <div class="relative inline-block">
              <div class="absolute bg-yellow-400 w-6 h-8 rounded-sm transform -rotate-3 translate-x-0.5 translate-y-0.5"></div>
              <div class="relative bg-red-500 w-6 h-8 rounded-sm transform -rotate-12"></div>
            </div>
          </th>
          <th class="py-2 px-2 border-b text-center">
            <div class="flex items-center justify-center bg-red-500 w-6 h-8 transform -rotate-12 rounded-sm text-white font-bold text-xs">E</div>
          </th>
          <th v-if="hasMedals" class="hidden sm:table-cell py-2 px-3 border-b text-left">{{ t('Stats.Medals') }}</th>
        </tr>
      </thead>
      <tbody>
        <template v-for="player in players" :key="player.licence">
          <tr class="hover:bg-gray-100" :class="medalsOf(player).length ? 'sm:border-b' : 'border-b'">
            <td class="py-2 px-3">
              <div class="font-medium flex items-center">
                <span v-if="player.captain !== 'E'" class="text-sm text-gray-500 mr-2">#{{ player.number }}</span>
                <span class="text-xs md:text-sm lg:text-base ml-1">{{ formatPrenom(player.firstname) }} {{ formatNom(player.name) }}</span>
                <span v-if="player.captain === 'C'" class="ml-2 bg-black text-white text-xs font-bold w-4 h-4 flex items-center justify-center rounded-sm">C</span>
                <span v-if="player.captain === 'E'" class="ml-2 text-xs text-gray-500">({{ t('Stats.Coach') }})</span>
              </div>
            </td>
            <td class="py-2 px-2 text-center">{{ player.goals > 0 ? player.goals : '' }}</td>
            <td class="py-2 px-2 text-center">{{ player.green_cards > 0 ? player.green_cards : '' }}</td>
            <td class="py-2 px-2 text-center">{{ player.yellow_cards > 0 ? player.yellow_cards : '' }}</td>
            <td class="py-2 px-2 text-center">{{ player.red_cards > 0 ? player.red_cards : '' }}</td>
            <td class="py-2 px-2 text-center">{{ player.exclusions > 0 ? player.exclusions : '' }}</td>
            <td v-if="hasMedals" class="hidden sm:table-cell py-1 px-3 align-middle">
              <div class="flex flex-wrap gap-1"><MedalPill v-for="medal in medalsOf(player)" :key="medal.item.code" :item="medal.item" :count="medal.count" compact /></div>
            </td>
          </tr>
          <!-- Small screens: the medals go on a line under the player, to keep the table on one screen width -->
          <tr v-if="medalsOf(player).length" class="sm:hidden border-b">
            <td colspan="6" class="px-3 pb-2">
              <div class="flex flex-wrap gap-1"><MedalPill v-for="medal in medalsOf(player)" :key="medal.item.code" :item="medal.item" :count="medal.count" compact /></div>
            </td>
          </tr>
        </template>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { formatNom, formatPrenom } from '~/utils/nameFormat'
import { GOAL_ICON_SVG } from '~/utils/goalIcon'
import { playerKey, type MedalItem } from '~/utils/medals'

/**
 * Player statistics of a team page (goals as numbers under the ball, cards) and, from api2, the medals of each
 * player (FEATURE_MATCH_MEDALS.md § 9.1, MED-65). Only the medals the catalogue publishes on app2 appear.
 */
interface StatPlayer { licence: string, name: string, firstname: string, number: number | null, captain: string | null, goals: number, green_cards: number, yellow_cards: number, red_cards: number, exclusions: number }
interface MedalPlayer { first_name: string | null, last_name: string | null, medals: { code: string, count: number }[] }

const props = withDefaults(defineProps<{ players: StatPlayer[], medalPlayers?: MedalPlayer[], catalog?: Map<string, MedalItem>, headClass?: string }>(), {
  medalPlayers: () => [],
  catalog: () => new Map(),
  headClass: 'bg-bar text-white'
})
const { t } = useI18n()
const byPlayer = computed(() => new Map(props.medalPlayers.map(player => [playerKey(player.first_name, player.last_name), player.medals])))
const medalsOf = (player: StatPlayer) => (byPlayer.value.get(playerKey(player.firstname, player.name)) ?? [])
  .flatMap(medal => props.catalog.get(medal.code) ? [{ item: props.catalog.get(medal.code)!, count: medal.count }] : [])
const hasMedals = computed(() => props.players.some(player => medalsOf(player).length > 0))
</script>
