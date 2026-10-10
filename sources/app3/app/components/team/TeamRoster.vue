<script setup lang="ts">
import { playerName, type RosterPlayer } from '~/utils/teams'

// Roster of a competition: number, NAME First name, category, role, goals and cards, icons AND text (TEA-03).
// No licence number, birth date nor individual photo (strategy § 11).
defineProps<{ players: RosterPlayer[], caption: string }>()
const CARDS = [
  { key: 'green', label: 'teams.col.green', class: 'bg-kpi-green-500' },
  { key: 'yellow', label: 'teams.col.yellow', class: 'bg-kpi-gold-400' },
  { key: 'red', label: 'teams.col.red', class: 'bg-kpi-red-600' },
  { key: 'red_final', label: 'teams.col.redFinal', class: 'bg-kpi-red-900' },
] as const
</script>

<template>
  <p v-if="players.length === 0" data-testid="no-roster">{{ $t('teams.noRoster') }}</p>
  <div v-else class="overflow-x-auto lg:overflow-visible">
    <table class="w-full text-left text-sm" data-testid="roster">
      <caption class="sr-only">{{ caption }}</caption>
      <thead>
        <tr class="border-b border-line">
          <th scope="col" class="py-1 pr-2 text-right">{{ $t('teams.col.number') }}</th>
          <th scope="col" class="py-1">{{ $t('teams.col.player') }}</th>
          <th scope="col" class="py-1">{{ $t('teams.col.category') }}</th>
          <th scope="col" class="py-1">{{ $t('teams.col.role') }}</th>
          <th scope="col" class="py-1 text-right">{{ $t('teams.col.goals') }}</th>
          <th v-for="card in CARDS" :key="card.key" scope="col" class="py-1 text-center">
            <span class="inline-block h-4 w-3 rounded-sm" :class="card.class" aria-hidden="true" /><span class="sr-only">{{ $t(card.label) }}</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(player, index) in players" :key="index" class="border-b border-line/60" data-testid="roster-player">
          <td class="py-1 pr-2 text-right tabular-nums">{{ player.number ?? '' }}</td>
          <th scope="row" class="py-1 font-normal">{{ playerName(player) }}</th>
          <td class="py-1">{{ player.category ?? '' }}</td>
          <td class="py-1">{{ player.role ? $t(`teams.role.${player.role}`) : '' }}</td>
          <td class="py-1 text-right tabular-nums">{{ player.goals || '' }}</td>
          <td v-for="card in CARDS" :key="card.key" class="py-1 text-center tabular-nums">{{ player[card.key] || '' }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
