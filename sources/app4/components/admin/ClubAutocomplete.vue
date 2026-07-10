<script setup lang="ts">
const props = defineProps<{
  modelValue: string // club code
  placeholder?: string
  disabled?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void
}>()

defineExpose({ focus: () => inputRef.value?.focus() })

const { t } = useI18n()
const api = useApi()

interface ClubResult {
  numero: string
  nom: string
  departement: string | null
  label: string
}

const searchQuery = ref('')
const results = ref<ClubResult[]>([])
const isLoading = ref(false)
const isOpen = ref(false)
const dropdownRef = ref<HTMLElement>()
const inputRef = ref<HTMLInputElement>()

let debounceTimeout: ReturnType<typeof setTimeout> | null = null

async function performSearch() {
  if (searchQuery.value.length < 2) {
    results.value = []
    isOpen.value = false
    return
  }
  isLoading.value = true
  try {
    const data = await api.get<ClubResult[]>(
      '/admin/operations/autocomplete/clubs',
      { q: searchQuery.value }
    )
    results.value = data || []
    isOpen.value = true
  } catch {
    results.value = []
  } finally {
    isLoading.value = false
  }
}

function debouncedSearch() {
  if (debounceTimeout) clearTimeout(debounceTimeout)
  debounceTimeout = setTimeout(() => performSearch(), 300)
}

watch(searchQuery, () => debouncedSearch())

function selectClub(club: ClubResult) {
  emit('update:modelValue', club.numero)
  searchQuery.value = club.label
  results.value = []
  isOpen.value = false
}

function clearSelection() {
  emit('update:modelValue', '')
  searchQuery.value = ''
  results.value = []
  isOpen.value = false
}

function handleClickOutside(event: MouseEvent) {
  if (dropdownRef.value && !dropdownRef.value.contains(event.target as Node)) {
    isOpen.value = false
  }
}

watch(
  () => props.modelValue,
  (val) => { if (!val) { searchQuery.value = ''; results.value = [] } }
)

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
        ref="inputRef"
        v-model="searchQuery"
        type="text"
        :disabled="disabled"
        :placeholder="placeholder || t('common.club')"
        class="w-full px-2 py-1 border border-header-300 dark:border-header-700 rounded text-xs bg-white dark:bg-header-900 text-header-900 dark:text-header-50 placeholder-header-400 dark:placeholder-header-500 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 disabled:bg-header-200 dark:disabled:bg-header-800 disabled:cursor-not-allowed"
        @focus="isOpen = results.length > 0"
      >
      <div class="absolute inset-y-0 right-0 flex items-center pr-2">
        <div v-if="isLoading" class="animate-spin h-3 w-3 border border-primary-500 border-t-transparent rounded-full" />
        <button
          v-else-if="searchQuery && !disabled"
          type="button"
          class="text-header-600 dark:text-header-300 hover:text-header-900 dark:hover:text-header-50"
          @click="clearSelection"
        >
          <UIcon name="i-heroicons-x-mark" class="w-3 h-3" />
        </button>
      </div>
    </div>

    <div
      v-if="isOpen && !disabled"
      class="absolute z-50 w-56 mt-1 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg shadow-lg max-h-48 overflow-y-auto"
    >
      <div
        v-if="!isLoading && results.length === 0 && searchQuery.length >= 2"
        class="px-3 py-2 text-xs text-header-600 dark:text-header-300 text-center"
      >
        {{ t('common.no_results') }}
      </div>
      <button
        v-for="club in results"
        :key="club.numero"
        type="button"
        class="w-full px-3 py-2 text-left hover:bg-primary-50 dark:hover:bg-primary-950 border-b border-header-100 dark:border-header-800 last:border-b-0 transition-colors"
        @click="selectClub(club)"
      >
        <div class="text-xs font-medium text-header-900 dark:text-header-50">{{ club.nom }}</div>
        <div class="text-xs text-header-600 dark:text-header-300 font-mono">{{ club.numero }}</div>
      </button>
    </div>
  </div>
</template>
