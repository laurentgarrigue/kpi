<template>
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ t('Manager.Title') }}</h1>

    <!-- Event and Pitch Selection -->
    <div class="bg-white rounded-lg shadow p-6 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('Event') }}</label>
          <select
            v-model="selectedEventId"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
          >
            <option :value="null">-- {{ t('Stats.SelectEvent') }} --</option>
            <option v-for="event in events" :key="event.id" :value="event.id">
              {{ event.name }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('Pitches') }}</label>
          <input
            v-model.number="pitchCount"
            type="number"
            min="1"
            max="19"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
          />
        </div>
      </div>

      <div class="mt-4">
        <label class="flex items-center">
          <input v-model="syncToDb" type="checkbox" class="mr-2 h-4 w-4 text-green-600" />
          <span class="text-sm text-gray-700">{{ t('Manager.SyncToDatabase') }}</span>
        </label>
      </div>
    </div>

    <!-- Connection Manager -->
    <ManagerConnectionList
      :pitch-count="pitchCount"
      :event-id="selectedEventId"
      :sync-to-database="syncToDb"
    />
  </div>
</template>

<script setup>
import { useEventStore } from '~/stores/eventStore'
import { usePreferencesStore } from '~/stores/preferencesStore'

const { t } = useI18n()
const eventStore = useEventStore()
const preferencesStore = usePreferencesStore()
const { getApi } = useApi()

const events = computed(() => eventStore.events)
const selectedEventId = ref(preferencesStore.selectedEvent)
const pitchCount = ref(preferencesStore.pitchCount || 1)
const syncToDb = ref(preferencesStore.syncToDatabase || false)

// Load events on mount
onMounted(async () => {
  if (events.value.length === 0) {
    try {
      const data = await getApi('/events/all')
      if (data && Array.isArray(data)) {
        await eventStore.setEvents(data)
      }
    } catch (error) {
      console.error('Failed to load events:', error)
    }
  }
})

// Watch for changes and save to preferences
watch(selectedEventId, async value => {
  await preferencesStore.savePref('selectedEvent', value)
})

watch(pitchCount, async value => {
  await preferencesStore.savePref('pitchCount', value)
})

watch(syncToDb, async value => {
  await preferencesStore.savePref('syncToDatabase', value)
})

// Require authentication
definePageMeta({
  middleware: 'auth',
})
</script>
