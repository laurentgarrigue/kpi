<script setup lang="ts">
/** Search a team (kp_equipe) by its label; the model is its number. */
const props = defineProps<{
  modelValue: number | null
  placeholder?: string
  disabled?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: number | null): void
}>()

const { t } = useI18n()
const api = useApi()

interface TeamResult {
  numero: number
  libelle: string
  codeClub: string | null
  club: string | null
  label: string
}

const MIN_CHARS = 2
const DEBOUNCE_MS = 300

const searchQuery = ref('')
const results = ref<TeamResult[]>([])
const isLoading = ref(false)
const isOpen = ref(false)
const dropdownRef = ref<HTMLElement>()

let selectedLabel = ''
let debounceTimeout: ReturnType<typeof setTimeout> | null = null

async function performSearch() {
  if (searchQuery.value.length < MIN_CHARS) {
    results.value = []
    isOpen.value = false
    return
  }
  isLoading.value = true
  try {
    results.value = (await api.get<TeamResult[]>('/admin/operations/autocomplete/teams', { q: searchQuery.value })) || []
    isOpen.value = true
  }
  catch {
    results.value = []
  }
  finally {
    isLoading.value = false
  }
}

function select(team: TeamResult) {
  selectedLabel = team.label
  emit('update:modelValue', team.numero)
  searchQuery.value = team.label
  results.value = []
  isOpen.value = false
}

function clearSelection() {
  emit('update:modelValue', null)
  searchQuery.value = ''
  results.value = []
  isOpen.value = false
}

// Typing again invalidates the previous choice
watch(searchQuery, (value) => {
  if (value === selectedLabel) {
    return
  }
  if (props.modelValue !== null) {
    emit('update:modelValue', null)
  }
  if (debounceTimeout) clearTimeout(debounceTimeout)
  debounceTimeout = setTimeout(performSearch, DEBOUNCE_MS)
})
watch(() => props.modelValue, (value) => {
  if (value === null && searchQuery.value === selectedLabel) {
    selectedLabel = ''
    searchQuery.value = ''
    results.value = []
  }
})

function handleClickOutside(event: MouseEvent) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
    isOpen.value = false
  }
}
onMounted(() => document.addEventListener('click', handleClickOutside))
onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
  if (debounceTimeout) clearTimeout(debounceTimeout)
})
</script>

<template>
  <div ref="dropdownRef" class="relative">
    <div class="relative">
      <input
        v-model="searchQuery"
        type="text"
        :disabled="disabled"
        :placeholder="placeholder"
        class="w-full rounded border border-header-300 bg-white px-2 py-1.5 text-sm text-header-900 placeholder-header-600 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-header-200 dark:border-header-700 dark:bg-header-900 dark:text-header-50 dark:placeholder-header-300 dark:disabled:bg-header-800"
        @focus="isOpen = results.length > 0"
      >
      <div class="absolute inset-y-0 right-0 flex items-center pr-2">
        <div v-if="isLoading" class="h-3 w-3 animate-spin rounded-full border border-primary-500 border-t-transparent" />
        <button
          v-else-if="searchQuery && !disabled"
          type="button"
          class="p-2 text-header-600 hover:text-header-900 dark:text-header-300 dark:hover:text-header-50"
          :aria-label="t('common.delete')"
          @click="clearSelection"
        >
          <UIcon name="i-heroicons-x-mark" class="h-5 w-5" />
        </button>
      </div>
    </div>
    <div
      v-if="isOpen && !disabled"
      class="absolute z-50 mt-1 max-h-56 w-full min-w-64 overflow-y-auto rounded-lg border border-header-300 bg-white shadow-lg dark:border-header-700 dark:bg-header-900"
    >
      <div v-if="!isLoading && results.length === 0" class="px-3 py-2 text-center text-sm text-header-600 dark:text-header-300">
        {{ t('common.no_results') }}
      </div>
      <button
        v-for="team in results"
        :key="team.numero"
        type="button"
        class="w-full border-b border-header-100 px-3 py-2 text-left transition-colors last:border-b-0 hover:bg-primary-50 dark:border-header-800 dark:hover:bg-primary-950"
        @click="select(team)"
      >
        <span class="block text-sm font-medium text-header-900 dark:text-header-50">{{ team.libelle }}</span>
        <span class="block text-xs text-header-600 dark:text-header-300">{{ team.club }} · n° {{ team.numero }}</span>
      </button>
    </div>
  </div>
</template>
