<template>
  <div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ t('Stats.Title') }}</h1>

    <div class="bg-white rounded-lg shadow p-6">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
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
          <label class="block text-sm font-medium text-gray-700 mb-2">{{ t('Pitch') }}</label>
          <input
            v-model.number="selectedPitch"
            type="number"
            min="1"
            max="19"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
          />
        </div>
      </div>

      <div v-if="selectedEventId && selectedPitch" class="space-y-4">
        <div class="bg-gray-50 rounded-lg p-4">
          <h3 class="font-semibold text-lg mb-2">{{ t('Stats.GameInfo') }}</h3>
          <div class="grid grid-cols-2 gap-4 text-sm">
            <div>
              <span class="text-gray-600">{{ t('Event') }}:</span>
              <span class="ml-2 font-medium">{{ selectedEventName }}</span>
            </div>
            <div>
              <span class="text-gray-600">{{ t('Pitch') }}:</span>
              <span class="ml-2 font-medium">{{ selectedPitch }}</span>
            </div>
          </div>
        </div>

        <StatsCollector :event-id="selectedEventId" :pitch="selectedPitch" />
      </div>

      <div v-else class="text-center py-8 text-gray-500">
        {{ t('Stats.SelectEvent') }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { useEventStore } from '~/stores/eventStore'

const { t } = useI18n()
const eventStore = useEventStore()
const { getApi } = useApi()

const events = computed(() => eventStore.events)
const selectedEventId = ref(null)
const selectedPitch = ref(1)

const selectedEventName = computed(() => {
  const event = events.value.find(e => e.id === selectedEventId.value)
  return event ? event.name : ''
})

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

definePageMeta({
  middleware: 'auth',
})
</script>
