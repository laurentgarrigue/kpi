<template>
  <div class="p-2">
    <div v-if="loading" class="flex justify-center items-center py-12">
      <UIcon name="i-heroicons-arrow-path" class="h-8 w-8 animate-spin text-gray-400" />
    </div>

    <div v-else-if="error" class="bg-red-50 border-l-4 border-red-400 p-4">
      <p class="text-red-800 font-medium">{{ error }}</p>
    </div>

    <div v-else-if="categories.length === 0" class="text-center py-12 text-gray-400">
      {{ t('Scrutineering.Overview.NoTeams') }}
    </div>

    <div v-else class="overflow-x-auto">
      <table class="min-w-full bg-white border border-gray-300 text-sm">
        <thead class="bg-gray-100">
          <tr>
            <th class="px-3 py-2 text-left font-semibold text-gray-700 border-b border-r border-gray-300 sticky left-0 bg-gray-100 z-10 min-w-40">
              {{ t('Scrutineering.Overview.Club') }}
            </th>
            <th
              v-for="cat in categories"
              :key="cat"
              class="px-3 py-2 text-center font-semibold text-gray-700 border-b border-r border-gray-200 min-w-40"
            >
              {{ cat }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="club in clubs"
            :key="club.code"
            class="border-b hover:bg-gray-50"
          >
            <td class="px-3 py-2 border-r border-gray-300 sticky left-0 bg-white z-10">
              <span class="font-medium text-gray-800 leading-tight">{{ club.label }}</span>
            </td>
            <td
              v-for="cat in categories"
              :key="cat"
              class="px-2 py-1 border-r border-gray-200 text-center"
            >
              <div v-if="getTeams(club.code, cat).length" class="flex flex-col gap-1">
                <button
                  v-for="team in getTeams(club.code, cat)"
                  :key="team.team_id"
                  type="button"
                  class="w-full flex items-center gap-1.5 px-2 py-1.5 rounded hover:bg-gray-100 transition-colors text-left cursor-pointer"
                  :class="getCellClass(team.status)"
                  :title="getStatusLabel(team.status)"
                  @click="$emit('select-team', team)"
                >
                  <UIcon :name="getStatusIcon(team.status)" class="h-5 w-5 flex-shrink-0" />
                  <span class="text-xs leading-tight line-clamp-2">{{ team.label }}</span>
                </button>
              </div>
              <span v-else class="text-gray-300">—</span>
            </td>
          </tr>
        </tbody>
      </table>

      <div class="mt-4 flex flex-wrap gap-4 text-sm">
        <div class="flex items-center gap-1.5">
          <UIcon name="i-heroicons-check-circle-solid" class="h-5 w-5 text-green-600" />
          <span class="text-gray-600">{{ t('Scrutineering.Overview.Complete') }}</span>
        </div>
        <div class="flex items-center gap-1.5">
          <UIcon name="i-heroicons-ellipsis-horizontal-circle-solid" class="h-5 w-5 text-yellow-500" />
          <span class="text-gray-600">{{ t('Scrutineering.Overview.Partial') }}</span>
        </div>
        <div class="flex items-center gap-1.5">
          <UIcon name="i-heroicons-clock" class="h-5 w-5 text-gray-400" />
          <span class="text-gray-600">{{ t('Scrutineering.Overview.None') }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const props = defineProps({
  eventId: {
    type: Number,
    required: true
  }
})

defineEmits(['select-team'])

const { t } = useI18n()
const { getApi } = useApi()

const loading = ref(false)
const error = ref(null)
const categories = ref([])
const clubs = ref([])   // [{code, label, logo}]
const teams = ref([])

// Index for O(1) cell lookup: "clubCode||category" → team[]
// A club can field several teams in the same category, so each cell holds a list.
const teamIndex = computed(() => {
  const idx = {}
  for (const team of teams.value) {
    const key = `${team.club}||${team.category}`
    ;(idx[key] ||= []).push(team)
  }
  return idx
})

const getTeams = (clubCode, category) => teamIndex.value[`${clubCode}||${category}`] || []

const getStatusIcon = (status) => {
  if (status === 'complete') return 'i-heroicons-check-circle-solid'
  if (status === 'partial')  return 'i-heroicons-ellipsis-horizontal-circle-solid'
  return 'i-heroicons-clock'
}

const getCellClass = (status) => {
  if (status === 'complete') return 'text-green-700'
  if (status === 'partial')  return 'text-yellow-600'
  return 'text-gray-400'
}

const getStatusLabel = (status) => {
  if (status === 'complete') return t('Scrutineering.Overview.Complete')
  if (status === 'partial')  return t('Scrutineering.Overview.Partial')
  return t('Scrutineering.Overview.None')
}

const load = async () => {
  loading.value = true
  error.value = null
  try {
    const response = await getApi(`/staff/${props.eventId}/overview`)
    const data = await response.json()
    categories.value = data.categories
    clubs.value = data.clubs
    teams.value = data.teams
  } catch {
    error.value = t('Scrutineering.Overview.LoadError')
  } finally {
    loading.value = false
  }
}

onMounted(load)

defineExpose({ load })
</script>
