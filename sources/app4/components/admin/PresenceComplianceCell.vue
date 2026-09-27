<script setup lang="ts">
import type { Player } from '~/types/presence'

/**
 * Licence / paddle / certificate value of a presence-sheet player, with a uniform
 * issue badge when the competition's eligibility rules flag it ("joueur en règle").
 * Rules come from api2 (PlayerEligibilityRules.php) through player.eligibilityErrors.
 */
interface Props {
  player: Player
  field: 'licence' | 'pagaie' | 'certif'
  season: string
  enforcement?: 'block' | 'warn' | null
  minPagaie?: string | null
}

const props = withDefaults(defineProps<Props>(), {
  enforcement: null,
  minPagaie: null
})

const { t } = useI18n()
const { errorLabel } = useEligibilityMessages()

const hasError = (code: string) => !!props.player.eligibilityErrors?.includes(code)

// Red when the rule blocks, amber when it only warns
const issueClass = computed(() => props.enforcement === 'warn'
  ? 'bg-amber-500 text-white dark:bg-amber-500 dark:text-header-950'
  : 'bg-danger-600 text-white dark:bg-danger-500')

const licenceNumber = computed(() => props.player.icf ? `ICF-${props.player.icf}` : props.player.matric.toString())
const isOldLicence = computed(() => !!props.player.origine && !!props.season && props.player.origine < props.season)
</script>

<template>
  <span class="inline-flex flex-wrap items-center justify-center gap-1.5">
    <!-- Licence number + season / type issues -->
    <template v-if="field === 'licence'">
      <NuxtLink :to="`/athletes?matric=${player.matric}`" class="link-value">
        {{ licenceNumber }}
      </NuxtLink>
      <span
        v-if="isOldLicence"
        class="issue-pill"
        :class="hasError('Saison_licence') ? issueClass : 'bg-header-200 text-header-800 dark:bg-header-700 dark:text-header-100'"
        :title="hasError('Saison_licence') ? errorLabel('Saison_licence') : undefined"
      >{{ player.origine }}</span>
      <span
        v-if="hasError('Type_licence')"
        class="issue-pill"
        :class="issueClass"
        :title="`${errorLabel('Type_licence')}${player.typeLicence ? ` — ${player.typeLicence}` : ''}`"
      >{{ t('presence.licence_not_competition') }}</span>
    </template>

    <!-- Paddle -->
    <template v-else-if="field === 'pagaie'">
      <span
        v-if="hasError('Pagaie_couleur')"
        class="issue-pill"
        :class="issueClass"
        :title="errorLabel('Pagaie_couleur', minPagaie)"
      >{{ player.pagaieLabel || t('presence.paddle_none') }}</span>
      <span
        v-else-if="!enforcement && player.pagaieValide === 0"
        class="text-danger-600 dark:text-danger-400"
        :title="t('presence.invalid_paddle')"
      >({{ player.pagaieLabel }})</span>
      <span v-else class="text-header-900 dark:text-header-50">{{ player.pagaieLabel }}</span>
    </template>

    <!-- Competition certificate -->
    <template v-else>
      <span
        v-if="hasError('Certif')"
        class="issue-pill"
        :class="issueClass"
        :title="errorLabel('Certif')"
      >{{ t('common.no') }}</span>
      <span v-else-if="player.certifCK === 'OUI'" class="text-success-500 dark:text-success-400">{{ t('common.yes') }}</span>
      <span v-else class="text-danger-600 dark:text-danger-400">{{ t('common.no') }}</span>
    </template>
  </span>
</template>

<style scoped>
.issue-pill {
  display: inline-flex;
  align-items: center;
  border-radius: 9999px;
  padding: 0.125rem 0.625rem;
  font-family: ui-sans-serif, system-ui, sans-serif;
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.25rem;
  white-space: nowrap;
  cursor: help;
}
</style>
