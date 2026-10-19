<template>
  <!-- Loading spinner for initial load -->
  <div v-if="isInitialLoading" class="mt-3 text-center">
    <UIcon name="i-heroicons-arrow-path" class="h-8 w-8 animate-spin text-blue-600 mx-auto" />
    <p class="mt-2 text-gray-600">{{ t('Event.Loading') }}</p>
  </div>

  <div v-else-if="preferenceStore.preferences" class="rounded-lg border border-gray-200 bg-surface p-4 shadow-sm sm:p-6">
    <!-- Followed event or group -->
    <div v-if="hasSelection && !showSelector" class="flex flex-col items-center gap-4 text-center sm:flex-row sm:text-left">
      <img
        v-if="followedEvent?.logo"
        class="h-16 w-16 shrink-0 object-contain"
        :src="`${backendBaseUrl}/img/${followedEvent.logo}`"
        alt=""
      >
      <div class="min-w-0 flex-1">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">
          {{ followedEvent ? t('Home.FollowedEvent') : t('Home.FollowedGroup') }}
        </p>
        <h2 class="text-xl font-bold text-gray-900 lg:text-2xl">
          {{ followedEvent ? followedEvent.libelle : `${preferenceStore.preferences.lastGroup.code} - ${getGroupLabel(preferenceStore.preferences.lastGroup)}` }}
        </h2>
        <p class="text-gray-600">{{ followedEvent ? followedEvent.place : preferenceStore.preferences.lastSeason }}</p>
      </div>
      <button
        type="button"
        class="flex shrink-0 cursor-pointer items-center gap-1 rounded border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100"
        @click="loadEvents"
      >
        <UIcon name="i-heroicons-arrows-right-left" class="h-4 w-4" />
        {{ t('Event.Change') }}
      </button>
    </div>

    <!-- Nothing followed yet -->
    <div v-else-if="!showSelector" class="space-y-3 text-center">
      <h2 class="text-xl font-bold text-gray-900">{{ t('Home.ChooseTitle') }}</h2>
      <p class="mx-auto max-w-xl text-gray-600">{{ t('Home.ChooseHint') }}</p>
      <button type="button" class="inline-flex cursor-pointer items-center gap-2 rounded bg-blue-600 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-700" @click="loadEvents">
        <UIcon name="i-heroicons-magnifying-glass" class="h-5 w-5" />
        {{ t('Event.SelectEvent') }}
      </button>
    </div>

    <!-- Event selection form -->
    <form v-if="showSelector" class="text-center">
      <!-- Event mode selector -->
      <div class="mb-1 inline-flex rounded-md shadow-sm" role="group">
        <button
          type="button"
          @click="changeEventMode('group')"
          :class="['px-4 py-1 text-sm font-medium rounded-l-lg border border-gray-200 cursor-pointer', eventMode === 'group' ? 'bg-blue-600 text-white' : 'bg-surface text-gray-900 hover:bg-gray-100']"
        >
          {{ t('Event.Competitions') }}
        </button>
        <button
          type="button"
          @click="changeEventMode('std')"
          :class="['px-4 py-1 text-sm font-medium rounded-r-md border border-gray-200 cursor-pointer', eventMode === 'std' ? 'bg-blue-600 text-white' : 'bg-surface text-gray-900 hover:bg-gray-100']"
        >
          {{ t('Event.StdEvents') }}
        </button>
      </div>
      <div v-if="eventMode === 'std'" class="mb-4 text-sm text-gray-600 italic">
        ({{ t('Event.ModeDescription') }})
      </div>

      <!-- Event dropdown (for std and champ modes) -->
      <div v-if="eventMode !== 'group'" class="my-2 max-w-md mx-auto">
        <!-- Season selector for events -->
        <div class="mb-2 flex items-center justify-center gap-2">
          <label class="text-sm text-gray-600">{{ t('Season.Label') }}:</label>
          <select
            v-model="selectedEventSeason"
            @change="onEventSeasonChange"
            class="px-3 py-2 border border-gray-400 rounded focus:outline-none focus:ring focus:border-blue-500 cursor-pointer"
          >
            <option v-for="year in availableEventSeasons" :key="year" :value="year">
              {{ year }}
            </option>
          </select>
        </div>
        <!-- Event dropdown -->
        <select v-model="eventSelectedId" @change="changeButton = true" class="block w-full px-3 py-2 border border-gray-400 rounded focus:outline-none focus:ring focus:border-blue-500 cursor-pointer">
          <option disabled :value="null">
            ▼ {{ t('Event.PleaseSelectOne') }} ▼
          </option>
          <option v-for="event in filteredEvents" :key="event.id" :value="event.id">
            ({{ event.id }}) - {{ event.libelle }} - {{ event.place }}
          </option>
        </select>
      </div>

      <!-- Group mode: season selector + group dropdown -->
      <div v-else class="my-2 max-w-md mx-auto">
        <!-- Season selector -->
        <div class="mb-2 flex items-center justify-center gap-2">
          <label class="text-sm text-gray-600">{{ t('Season.Label') }}:</label>
          <select
            v-model="selectedSeason"
            @change="onSeasonChange"
            class="px-3 py-2 border border-gray-400 rounded focus:outline-none focus:ring focus:border-blue-500 cursor-pointer"
          >
            <option v-for="year in availableSeasons" :key="year" :value="year">
              {{ year }}
            </option>
          </select>
        </div>
        <!-- Group dropdown -->
        <select v-model="selectedGroupCode" @change="changeButton = true" class="block w-full px-3 py-2 border border-gray-400 rounded focus:outline-none focus:ring focus:border-blue-500 cursor-pointer">
          <option disabled :value="null">
            ▼ {{ t('Event.PleaseSelectGroup') }} ▼
          </option>
          <optgroup v-for="section in groupSections" :key="section.section" :label="t(`Section.${section.label}`)">
            <option v-for="group in section.groups" :key="group.code" :value="group.code">
              ({{ group.code }}) {{ getGroupLabel(group) }}
            </option>
          </optgroup>
        </select>
      </div>

      <!-- Action buttons -->
      <div class="flex justify-center space-x-4">
        <button @click.prevent="cancelEvent" class="px-4 py-1 bg-gray-500 text-white text-sm rounded cursor-pointer">
          {{ t('Event.Cancel') }}
        </button>
        <button v-if="changeButton" @click.prevent="changeEvent" class="px-4 py-1 bg-blue-600 text-white text-sm rounded cursor-pointer">
          {{ t('Event.Confirm') }}
        </button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, toRaw } from 'vue'
import { usePreferenceStore } from '~/stores/preferenceStore';
import { useEventStore } from '~/stores/eventStore';
import { useGroupStore } from '~/stores/groupStore';
import { useGames } from '~/composables/useGames';
import db from '~/utils/db'
const { t } = useI18n()
const { getGroupLabel } = useGroupLabel()
const { backendBaseUrl } = useRuntimeConfig().public

// Stores & Composables
const preferenceStore = usePreferenceStore()
const eventStore = useEventStore()
const groupStore = useGroupStore()
const { clearAllData: clearGamesData } = useGames()
const { getApi } = useApi()

// State
const showSelector = ref(false)
const eventSelectedId = ref(null)
const selectedGroupCode = ref(null)
const changeButton = ref(false)
const eventMode = ref('std') // Default mode: 'std', 'champ', or 'group'
const isInitialLoading = ref(true)
const groupSections = ref([])

// Season management
const currentYear = new Date().getFullYear()
const selectedSeason = ref(currentYear.toString())
const selectedEventSeason = ref(currentYear.toString())
const availableSeasons = computed(() => {
  const seasons = []
  for (let i = 0; i < 6; i++) {
    seasons.push((currentYear - i).toString())
  }
  return seasons
})

// Available seasons for events (based on loaded events)
const availableEventSeasons = computed(() => {
  const years = new Set()
  eventStore.events.forEach(event => {
    if (event.year) {
      years.add(event.year.toString())
    }
  })
  // Sort descending and return array
  const sortedYears = Array.from(years).sort((a, b) => parseInt(b) - parseInt(a))
  // If no years found, return default list
  if (sortedYears.length === 0) {
    return availableSeasons.value
  }
  return sortedYears
})


// Filtered events by selected season
const filteredEvents = computed(() => {
  const selectedYear = parseInt(selectedEventSeason.value)
  const filtered = eventStore.events.filter(event => event.year === selectedYear)
  if (eventMode.value === 'std') {
    return [...filtered].sort((a, b) => b.id - a.id)
  }
  return filtered
})

const followedEvent = computed(() => preferenceStore.preferences?.lastEvent ?? null)

const hasSelection = computed(() => {
  return (preferenceStore.preferences?.lastEvent !== undefined && preferenceStore.preferences?.lastEvent !== null) ||
         (preferenceStore.preferences?.lastGroup !== undefined && preferenceStore.preferences?.lastGroup !== null)
})

// Methods
const changeEventMode = async (mode) => {
  if (mode !== eventMode.value) {
    eventMode.value = mode
    changeButton.value = false
    if (mode === 'group') {
      // Sync group season with event season when switching to group mode
      if (selectedEventSeason.value) {
        selectedSeason.value = selectedEventSeason.value
      }
      await loadGroups()
    } else {
      // Sync event season with group season when switching to event mode
      if (selectedSeason.value) {
        selectedEventSeason.value = selectedSeason.value
      }
      await loadEvents()
    }
  }
}

const loadEvents = async () => {
  if (eventMode.value === 'group') {
    await loadGroups()
    return
  }

  eventStore.loading = true
  showSelector.value = false
  try {
    const result = await getApi(`/events/${eventMode.value}`)
    if (!result.ok) {
      throw new Error(`HTTP error! status: ${result.status}`)
    }
    const eventsData = await result.json()
    const eventsResult = eventsData.map(event => ({ ...event, id: parseInt(event.id) }))

    await eventStore.clearAndUpdateEvents(eventsResult)

    // Restore event and season from preferences
    const lastEvent = preferenceStore.preferences.lastEvent
    if (lastEvent) {
      eventSelectedId.value = lastEvent.id
      // Restore season from the last selected event
      if (lastEvent.year) {
        selectedEventSeason.value = lastEvent.year.toString()
      }
    } else {
      eventSelectedId.value = null
    }
    showSelector.value = true
  } catch (error) {
    eventStore.error = error
    console.error('Failed to load events:', error)
  } finally {
    eventStore.loading = false
  }
}

const loadGroups = async () => {
  groupStore.loading = true
  showSelector.value = false
  try {
    // Use selected season (restored from preferences in onMounted if needed)
    const season = selectedSeason.value
    groupStore.selectSeason(season)

    const result = await getApi(`/groups/${season}`)
    if (!result.ok) {
      throw new Error(`HTTP error! status: ${result.status}`)
    }
    const data = await result.json()
    groupStore.setSections(data.sections)
    groupSections.value = data.sections

    // Restore group selection only if it matches the current season
    const lastGroup = preferenceStore.preferences.lastGroup
    const lastSeason = preferenceStore.preferences.lastSeason
    if (lastGroup && lastSeason === season) {
      selectedGroupCode.value = lastGroup.code
    } else {
      selectedGroupCode.value = null
    }
    showSelector.value = true
  } catch (error) {
    groupStore.error = error
    console.error('Failed to load groups:', error)
  } finally {
    groupStore.loading = false
  }
}

// Called when season is changed in the dropdown (for groups)
const onSeasonChange = async () => {
  const previousSeason = groupStore.getCurrentSeason
  groupStore.selectSeason(selectedSeason.value)

  // Keep the current group selection if changing season only
  // Show confirm button if we have a group selected (even from a previous season)
  const hadGroupSelected = selectedGroupCode.value !== null
  selectedGroupCode.value = null // Reset group selection for new season's groups

  await loadGroups()

  // If there was a previously selected group and we changed season, show confirm button
  // This allows user to confirm the season change with a new group
  if (hadGroupSelected || previousSeason !== selectedSeason.value) {
    changeButton.value = true
  }
}

// Called when season is changed for events
const onEventSeasonChange = () => {
  // Reset event selection when changing season
  const hadEventSelected = eventSelectedId.value !== null
  eventSelectedId.value = null

  // Show confirm button if we had an event selected
  if (hadEventSelected) {
    changeButton.value = true
  }
}

// Reset game filters directly via preferences (avoids composable context issues)
const resetGameFilters = async () => {
  await Promise.all([
    preferenceStore.putItem('fav_categories', '[]'),
    preferenceStore.putItem('fav_teams', '[]'),
    preferenceStore.putItem('fav_dates', ''),
    preferenceStore.putItem('show_flags', true)
  ])
}

const changeEvent = async () => {
  if (eventMode.value === 'group') {
    await changeGroup()
    return
  }

  if (!eventSelectedId.value) return

  const selectedEvent = eventStore.getEventById(eventSelectedId.value)
  if (!selectedEvent) return

  // Clear old event data from IndexedDB and stores when changing events
  const lastEvent = preferenceStore.preferences.lastEvent
  if (lastEvent && lastEvent.id !== eventSelectedId.value) {
    // Clear IndexedDB
    await Promise.all([
      db.games.clear(),
      db.charts.clear(),
      preferenceStore.putItem('games_last_api_load', 0),
      preferenceStore.putItem('charts_last_api_load', 0)
    ])
    // Clear Pinia stores and singleton refs
    clearGamesData()
  }

  await preferenceStore.putItem('lastEvent', toRaw(selectedEvent))
  await preferenceStore.putItem('lastGroup', null)
  await preferenceStore.putItem('lastSeason', null)
  await preferenceStore.putItem('eventMode', eventMode.value)
  await preferenceStore.putItem('last_team', null)

  // Reset all game filters when changing event
  await resetGameFilters()

  showSelector.value = false
  changeButton.value = false
}

const changeGroup = async () => {
  if (!selectedGroupCode.value) return

  const selectedGroup = groupStore.getGroupByCode(selectedGroupCode.value)
  if (!selectedGroup) return

  const season = groupStore.getCurrentSeason

  // Clear old data from IndexedDB and stores when changing groups
  await Promise.all([
    db.games.clear(),
    db.charts.clear(),
    preferenceStore.putItem('games_last_api_load', 0),
    preferenceStore.putItem('charts_last_api_load', 0)
  ])
  // Clear Pinia stores and singleton refs
  clearGamesData()

  await preferenceStore.putItem('lastGroup', toRaw(selectedGroup))
  await preferenceStore.putItem('lastSeason', season)
  await preferenceStore.putItem('lastEvent', null)
  await preferenceStore.putItem('eventMode', 'group')
  await preferenceStore.putItem('last_team', null)

  // Reset all game filters when changing group
  await resetGameFilters()

  showSelector.value = false
  changeButton.value = false
}

const cancelEvent = () => {
  showSelector.value = false
  changeButton.value = false
}

onMounted(async () => {
  const route = useRoute()

  try {
    await preferenceStore.fetchItems()

    // Set initial selected event/group based on preferences
    if (preferenceStore.preferences.lastEvent) {
      eventSelectedId.value = preferenceStore.preferences.lastEvent.id
      eventMode.value = preferenceStore.preferences.eventMode || 'std'
    } else if (preferenceStore.preferences.lastGroup) {
      selectedGroupCode.value = preferenceStore.preferences.lastGroup.code
      eventMode.value = 'group'
      // Restore season from preferences
      if (preferenceStore.preferences.lastSeason) {
        selectedSeason.value = preferenceStore.preferences.lastSeason
        groupStore.selectSeason(preferenceStore.preferences.lastSeason)
      }
    } else if (preferenceStore.preferences.lastSeason) {
      // Restore season even if no group is selected (for season selector display)
      selectedSeason.value = preferenceStore.preferences.lastSeason
      groupStore.selectSeason(preferenceStore.preferences.lastSeason)
    }
  } catch (error) {
    console.error('Failed to load preferences:', error)
  } finally {
    isInitialLoading.value = false

    // Check if there's a pending redirect from query parameter
    if (hasSelection.value && route.query.redirect) {
      const targetUrl = String(route.query.redirect)
      await navigateTo(targetUrl, { replace: true })
    }

    // Auto-open selector if requested via query parameter
    if (route.query.openSelector) {
      await loadEvents()
    }
  }
})
</script>
