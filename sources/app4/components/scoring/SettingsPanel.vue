<script setup lang="ts">
import type { OfficialRole, OfficialSuggestionsResponse, ScoringMatch, ScoringPlayer, TeamSide } from '~/types/scoring'

/**
 * "Paramètres du match" view (spec §7.1/§7.2/§7.8): everything the scoring table checks
 * BEFORE kick-off — officials, both compositions, and the escape hatches (load another
 * match, reload the presence sheet).
 *
 * Read-only on purpose (spec §7.1, "écart assumé vs legacy"): match type and publication
 * are shown for control but edited in the match management, not here.
 *
 * Props down / events up: no direct API call, the page owns the store mutations.
 */
const props = defineProps<{
  match: ScoringMatch
  playersA: ScoringPlayer[]
  playersB: ScoringPlayer[]
  canScore?: boolean
  canManagePlayers?: boolean
  /** Officials suggestions (page-owned API call), see ScoringOfficialInput. */
  suggestOfficials: (role: OfficialRole, q: string) => Promise<OfficialSuggestionsResponse>
}>()

const emit = defineEmits<{
  'update-player': [team: TeamSide, matric: number, patch: { numero?: number, capitaine?: '-' | 'C' | 'E' }]
  'remove-player': [team: TeamSide, matric: number]
  'reload-players': [team: TeamSide]
  'save-officials': [officials: Record<string, string>]
  'load-match': [numberOrId: string]
  'load-next': []
}>()

const { t } = useI18n()

// ─── Officials (spec §7.2) — edited here, saved in one call ───
// Grouped by who sits where: the scoring table, then the referees on the pitch.
const OFFICIAL_GROUPS = [
  { key: 'officials_table', fields: ['secretaire', 'chronometre', 'timeshoot'] },
  { key: 'officials_referees', fields: ['arbitrePrincipal', 'arbitreSecondaire', 'ligne1', 'ligne2'] }
] as const
const OFFICIAL_FIELDS = OFFICIAL_GROUPS.flatMap(g => g.fields)

// Which referee team provides each official (spec §7.2): the first referee team staffs the
// scoring table, the second one the line judges. Referee fields themselves: free text.
const OFFICIAL_ROLE: Partial<Record<(typeof OFFICIAL_FIELDS)[number], OfficialRole>> = {
  secretaire: 'table',
  chronometre: 'table',
  timeshoot: 'table',
  ligne1: 'line',
  ligne2: 'line'
}

const officials = ref<Record<string, string>>({})
const officialsDirty = ref(false)

// (Re)fill the form whenever the loaded match changes.
watch(() => props.match.id, () => {
  const m = props.match as unknown as Record<string, string | null>
  officials.value = Object.fromEntries(
    OFFICIAL_FIELDS.map(f => [f, m[f] ?? ''])
  ) as Record<string, string>
  officialsDirty.value = false
}, { immediate: true })

const saveOfficials = () => {
  emit('save-officials', { ...officials.value })
  officialsDirty.value = false
}

// ─── Load another match by short number or full id (spec §7.8) ───
const gameLookup = ref('')

// ─── Player edition ───
const teams = computed(() => [
  { code: 'A' as TeamSide, name: props.match.equipeA, players: props.playersA },
  { code: 'B' as TeamSide, name: props.match.equipeB, players: props.playersB }
])

// Mobile: one composition at a time behind A/B tabs.
const activeTeam = ref<TeamSide>('A')

const CAPTAIN_OPTIONS = [
  { label: '—', value: '-' },
  { label: 'C', value: 'C' },
  { label: 'E', value: 'E' }
]
</script>

<template>
  <!-- Aside (match facts, officials, other match) | compositions, which take the room -->
  <div class="grid gap-6 lg:grid-cols-[22rem_minmax(0,1fr)] lg:items-start">
    <aside class="space-y-4">
      <!-- Escape hatch: load another match by short number or full id (spec §7.8) -->
      <section class="rounded-xl border border-header-200 dark:border-header-700 p-4 space-y-2">
        <label for="scoring-game-lookup" class="block text-sm font-semibold">{{ t('scoring.settings.load_game') }}</label>
        <div class="flex gap-2">
          <UInput
            id="scoring-game-lookup"
            v-model="gameLookup"
            size="sm"
            class="flex-1"
            :placeholder="t('scoring.settings.load_game_placeholder')"
            @keyup.enter="emit('load-match', gameLookup)"
          />
          <UButton size="sm" :disabled="!gameLookup" @click="emit('load-match', gameLookup)">
            {{ t('scoring.settings.load') }}
          </UButton>
        </div>
        <UButton
          block
          size="sm"
          variant="outline"
          icon="i-heroicons-forward"
          @click="emit('load-next')"
        >{{ t('scoring.settings.load_next') }}</UButton>
      </section>

      <!-- Match: read-only control (spec §7.1, edited in the match management) -->
      <section class="rounded-xl border border-header-200 dark:border-header-700 p-4 space-y-3">
        <div class="flex items-baseline gap-2">
          <h2 class="text-xl">{{ t('scoring.settings.match') }}</h2>
          <span class="text-sm text-header-500 dark:text-header-400">ID#{{ match.id }}</span>
        </div>
        <dl class="grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-2 text-sm">
          <dt class="text-header-600 dark:text-header-300">{{ t('scoring.settings.type') }}</dt>
          <dd>
            <UBadge variant="soft" color="neutral">
              {{ t('scoring.settings.type_' + (match.type === 'E' ? 'elimination' : 'ranking')) }}
            </UBadge>
          </dd>
          <dt class="text-header-600 dark:text-header-300">{{ t('scoring.settings.publication') }}</dt>
          <dd>
            <UBadge variant="soft" :color="match.publication === 'O' ? 'success' : 'neutral'">
              {{ t('scoring.settings.publication_' + (match.publication === 'O' ? 'public' : 'private')) }}
            </UBadge>
          </dd>
        </dl>
        <p class="text-xs text-header-500 dark:text-header-400">{{ t('scoring.settings.read_only_hint') }}</p>
      </section>

      <!-- Officials (spec §7.2) — edited here, saved in one call -->
      <section class="rounded-xl border border-header-200 dark:border-header-700 p-4 space-y-4">
        <h2 class="text-xl">{{ t('scoring.settings.officials') }}</h2>
        <fieldset v-for="group in OFFICIAL_GROUPS" :key="group.key" class="space-y-2">
          <legend class="text-sm font-semibold mb-1">{{ t('scoring.settings.' + group.key) }}</legend>
          <UFormField
            v-for="field in group.fields" :key="field"
            :label="t('scoring.officials.' + field)"
            :name="field"
            size="sm"
          >
            <ScoringOfficialInput
              v-if="OFFICIAL_ROLE[field]"
              v-model="officials[field]!"
              :input-id="'official-' + field"
              :role="OFFICIAL_ROLE[field]!"
              :suggest="suggestOfficials"
              :disabled="!canScore"
              @update:model-value="officialsDirty = true"
            />
            <UInput
              v-else
              v-model="officials[field]"
              size="sm"
              class="w-full"
              :disabled="!canScore"
              @update:model-value="officialsDirty = true"
            />
          </UFormField>
        </fieldset>
        <UButton
          block
          icon="i-heroicons-check"
          :variant="officialsDirty ? 'solid' : 'outline'"
          :disabled="!canScore || !officialsDirty"
          @click="saveOfficials"
        >{{ t('scoring.edit.save') }}</UButton>
      </section>

    </aside>

    <!-- Compositions: side by side when there is room, A/B tabs on mobile -->
    <div class="space-y-3">
      <div class="md:hidden grid grid-cols-2 rounded-lg border border-header-200 dark:border-header-700 p-0.5" role="tablist">
        <button
          v-for="team in teams" :key="team.code"
          type="button"
          role="tab"
          class="truncate rounded-md px-3 py-2 text-sm"
          :class="activeTeam === team.code ? 'bg-primary-500 text-white' : 'text-header-700 dark:text-header-200'"
          :aria-selected="activeTeam === team.code"
          @click="activeTeam = team.code"
        >{{ team.name }}</button>
      </div>

      <div class="grid gap-4 md:grid-cols-2">
        <section
          v-for="team in teams" :key="team.code"
          class="rounded-xl border border-header-200 dark:border-header-700 p-4"
          :class="activeTeam === team.code ? '' : 'max-md:hidden'"
        >
          <div class="flex items-center justify-between gap-2 mb-3">
            <h2 class="text-xl truncate">{{ team.name }}</h2>
            <UButton
              size="xs"
              variant="outline"
              icon="i-heroicons-arrow-path"
              :disabled="!canManagePlayers"
              @click="emit('reload-players', team.code)"
            >{{ t('scoring.settings.reload_players') }}</UButton>
          </div>

          <p v-if="team.players.length === 0" class="text-sm text-header-500 dark:text-header-400">
            {{ t('scoring.settings.no_player') }}
          </p>
          <table v-else class="w-full text-sm">
            <thead class="text-xs text-header-600 dark:text-header-300">
              <tr>
                <th class="text-left font-normal w-16 pb-1">{{ t('scoring.settings.number') }}</th>
                <th class="text-left font-normal pb-1">{{ t('scoring.settings.player') }}</th>
                <th class="text-left font-normal w-20 pb-1">{{ t('scoring.settings.captain') }}</th>
                <th class="w-8" />
              </tr>
            </thead>
            <tbody>
              <tr v-for="p in team.players" :key="p.matric" class="border-t border-header-100 dark:border-header-800">
                <td class="py-1.5">
                  <UInput
                    :model-value="p.numero"
                    type="number"
                    size="xs"
                    class="w-14 scoring-digits"
                    :disabled="!canManagePlayers"
                    @change="emit('update-player', team.code, p.matric, { numero: Number(($event.target as HTMLInputElement).value) })"
                  />
                </td>
                <td class="py-1.5 pr-2">{{ p.nom.toUpperCase() }} {{ p.prenom }}</td>
                <td class="py-1.5">
                  <USelect
                    :model-value="p.capitaine"
                    :items="CAPTAIN_OPTIONS"
                    value-key="value"
                    size="xs"
                    class="w-16"
                    :disabled="!canManagePlayers"
                    @update:model-value="emit('update-player', team.code, p.matric, { capitaine: $event as '-' | 'C' | 'E' })"
                  />
                </td>
                <td class="py-1.5">
                  <UButton
                    size="xs"
                    variant="ghost"
                    color="error"
                    icon="i-heroicons-trash"
                    :disabled="!canManagePlayers"
                    :aria-label="t('scoring.settings.remove_player')"
                    @click="emit('remove-player', team.code, p.matric)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </section>
      </div>
    </div>
  </div>
</template>
