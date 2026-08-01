<script setup lang="ts">
import type { ScoringMatch, ScoringPlayer, TeamSide } from '~/types/scoring'

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
}>()

const emit = defineEmits<{
  'update-player': [team: TeamSide, matric: number, patch: { numero?: number, capitaine?: '-' | 'C' | 'E' }]
  'remove-player': [team: TeamSide, matric: number]
  'reload-players': [team: TeamSide]
  'save-officials': [officials: Record<string, string>]
  'load-match': [numberOrId: string]
}>()

const { t } = useI18n()

// ─── Officials (spec §7.2) — edited here, saved in one call ───
const OFFICIAL_FIELDS = [
  'secretaire', 'chronometre', 'timeshoot',
  'arbitrePrincipal', 'arbitreSecondaire', 'ligne1', 'ligne2'
] as const

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

const CAPTAIN_OPTIONS = [
  { label: '—', value: '-' },
  { label: 'C', value: 'C' },
  { label: 'E', value: 'E' }
]
</script>

<template>
  <div class="space-y-6">
    <!-- Control block: read-only match settings + escape hatches -->
    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="space-y-1">
        <div class="text-xs uppercase text-header-600 dark:text-header-300">{{ t('scoring.settings.type') }}</div>
        <UBadge variant="soft" color="neutral">
          {{ t('scoring.settings.type_' + (match.type === 'E' ? 'elimination' : 'ranking')) }}
        </UBadge>
        <p class="text-xs text-header-400 dark:text-header-500">{{ t('scoring.settings.read_only_hint') }}</p>
      </div>

      <div class="space-y-1">
        <div class="text-xs uppercase text-header-600 dark:text-header-300">{{ t('scoring.settings.publication') }}</div>
        <UBadge variant="soft" :color="match.publication === 'O' ? 'success' : 'neutral'">
          {{ t('scoring.settings.publication_' + (match.publication === 'O' ? 'public' : 'private')) }}
        </UBadge>
      </div>

      <div class="space-y-1">
        <label class="text-xs uppercase text-header-600 dark:text-header-300">{{ t('scoring.settings.load_game') }}</label>
        <div class="flex gap-1">
          <UInput
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
      </div>
    </section>

    <!-- Officials -->
    <section>
      <div class="flex items-center justify-between mb-2">
        <h3 class="font-semibold">{{ t('scoring.settings.officials') }}</h3>
        <UButton
          size="xs"
          :disabled="!canScore || !officialsDirty"
          @click="saveOfficials"
        >{{ t('scoring.edit.save') }}</UButton>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div v-for="field in OFFICIAL_FIELDS" :key="field">
          <label class="block text-xs text-header-600 dark:text-header-300 mb-1">{{ t('scoring.officials.' + field) }}</label>
          <UInput
            v-model="officials[field]"
            size="sm"
            :disabled="!canScore"
            @update:model-value="officialsDirty = true"
          />
        </div>
      </div>
    </section>

    <!-- Compositions -->
    <section class="grid grid-cols-1 md:grid-cols-2 gap-6">
      <div v-for="team in teams" :key="team.code">
        <div class="flex items-center justify-between mb-2">
          <h3 class="font-semibold">{{ team.name }}</h3>
          <UButton
            size="xs"
            variant="outline"
            icon="i-heroicons-arrow-path"
            :disabled="!canManagePlayers"
            @click="emit('reload-players', team.code)"
          >{{ t('scoring.settings.reload_players') }}</UButton>
        </div>

        <div v-if="team.players.length === 0" class="text-sm text-header-400 dark:text-header-500">
          {{ t('scoring.settings.no_player') }}
        </div>
        <table v-else class="w-full text-sm">
          <thead class="text-xs text-header-600 dark:text-header-300">
            <tr>
              <th class="text-left w-16">{{ t('scoring.settings.number') }}</th>
              <th class="text-left">{{ t('scoring.settings.player') }}</th>
              <th class="text-left w-20">{{ t('scoring.settings.captain') }}</th>
              <th class="w-8" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="p in team.players" :key="p.matric" class="border-t border-header-100 dark:border-header-800">
              <td class="py-1">
                <UInput
                  :model-value="p.numero"
                  type="number"
                  size="xs"
                  class="w-14"
                  :disabled="!canManagePlayers"
                  @change="emit('update-player', team.code, p.matric, { numero: Number(($event.target as HTMLInputElement).value) })"
                />
              </td>
              <td class="py-1">{{ p.nom.toUpperCase() }} {{ p.prenom }}</td>
              <td class="py-1">
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
              <td class="py-1">
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
      </div>
    </section>
  </div>
</template>
