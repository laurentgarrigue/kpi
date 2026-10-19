<script setup lang="ts">
import type { TeamView, useScrutineeringNavigation } from '~/composables/useScrutineeringNavigation'

/** Team navigation of the event (PAGE_SCRUTINEERING.md § 1.2): selector, previous / next, same club / nation, view switch. */
const props = defineProps<{
  navigation: ReturnType<typeof useScrutineeringNavigation>
  teamId: number
  view: TeamView
}>()

const { t } = useI18n()
const otherView = computed<TeamView>(() => (props.view === 'presence' ? 'scrutineering' : 'presence'))
const chipClass = 'px-2 py-1 text-xs rounded-full border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 hover:border-primary-400 hover:bg-primary-50 dark:hover:bg-primary-950'
const arrowClass = 'p-2 rounded-lg border border-header-300 dark:border-header-700 text-header-900 dark:text-header-50 hover:bg-header-50 dark:hover:bg-header-800 disabled:opacity-40 disabled:cursor-not-allowed'
</script>

<template>
  <div v-if="navigation.teams.value.length > 0" class="mb-3 flex flex-col gap-2" data-testid="scrutineering-team-nav">
    <div class="flex flex-wrap items-center gap-2">
      <button
        type="button"
        :class="arrowClass"
        :disabled="!navigation.adjacent.value.previous"
        :title="t('scrutineering.nav.previous')"
        @click="navigation.adjacent.value.previous && navigation.go(navigation.adjacent.value.previous.team_id)"
      >
        <UIcon name="heroicons:chevron-left" class="w-5 h-5" />
      </button>
      <select
        :value="teamId"
        :aria-label="t('scrutineering.nav.select_team')"
        class="min-w-56 max-w-md px-2 py-2 text-sm font-semibold border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 cursor-pointer"
        @change="navigation.go(Number(($event.target as HTMLSelectElement).value))"
      >
        <optgroup v-for="group in navigation.groups.value" :key="group.code" :label="`${group.code} - ${group.label}`">
          <option v-for="team in group.teams" :key="team.team_id" :value="team.team_id">
            {{ team.label }}
          </option>
        </optgroup>
      </select>
      <button
        type="button"
        :class="arrowClass"
        :disabled="!navigation.adjacent.value.next"
        :title="t('scrutineering.nav.next')"
        @click="navigation.adjacent.value.next && navigation.go(navigation.adjacent.value.next.team_id)"
      >
        <UIcon name="heroicons:chevron-right" class="w-5 h-5" />
      </button>
      <button
        type="button"
        class="ml-auto flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg bg-primary-600 text-white hover:bg-primary-700"
        data-testid="switch-view"
        @click="navigation.go(teamId, otherView)"
      >
        <UIcon :name="otherView === 'scrutineering' ? 'heroicons:clipboard-document-check' : 'heroicons:user-group'" class="w-5 h-5" />
        {{ t(`scrutineering.nav.open_${otherView}`) }}
      </button>
    </div>

    <div v-if="navigation.sameClub.value.length > 0" class="flex flex-wrap items-center gap-1.5">
      <span class="text-xs font-medium text-header-600 dark:text-header-300">{{ t('scrutineering.nav.same_club') }}</span>
      <button v-for="team in navigation.sameClub.value" :key="team.team_id" type="button" :class="chipClass" @click="navigation.go(team.team_id)">
        {{ team.label }} <span class="text-header-500">· {{ team.competition_code }}</span>
      </button>
    </div>
    <div v-if="navigation.sameNation.value.length > 0" class="flex flex-wrap items-center gap-1.5">
      <span class="text-xs font-medium text-header-600 dark:text-header-300">{{ t('scrutineering.nav.same_nation', { nation: navigation.current.value?.nation }) }}</span>
      <button v-for="team in navigation.sameNation.value" :key="team.team_id" type="button" :class="chipClass" @click="navigation.go(team.team_id)">
        {{ team.label }} <span class="text-header-500">· {{ team.competition_code }}</span>
      </button>
    </div>
  </div>
</template>
