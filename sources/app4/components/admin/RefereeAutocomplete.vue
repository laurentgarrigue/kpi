<script setup lang="ts">
interface RefereeResult {
  type: string
  matric?: string
  nom?: string
  prenom?: string
  libelle?: string
  arbitre?: string
  label: string
  value?: string
}

const props = defineProps<{
  modelValue: string
  matric: number
  journeeId: number | null
  placeholder?: string
  disabled?: boolean
  compact?: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'update:matric': [value: number]
  confirm: []
  cancel: []
}>()

const { t, locale } = useI18n()
const api = useApi()

const searchQuery = ref('')
const results = ref<RefereeResult[]>([])
const isLoading = ref(false)
const isOpen = ref(false)
const activeIndex = ref(-1)
const dropdownRef = ref<HTMLElement>()
const inputRef = ref<HTMLInputElement>()

// Position du dropdown téléporté (fixed, calculé depuis l'input)
const dropdownStyle = ref({ top: '0px', left: '0px', width: '0px' })

function updateDropdownPosition() {
  if (!inputRef.value) return
  const rect = inputRef.value.getBoundingClientRect()
  dropdownStyle.value = {
    top: `${rect.bottom + 4}px`,
    left: `${rect.left}px`,
    width: `${Math.max(rect.width, 256)}px`,
  }
}

let debounceTimeout: ReturnType<typeof setTimeout> | null = null
let skipNextSearch = false
let justSelected = false

// Selectable items only (skip separators/errors)
const selectableResults = computed(() =>
  results.value.map((item, idx) => ({ item, idx })).filter(({ item }) => item.type !== 'separator' && item.type !== 'error'),
)

// Initialize search query from modelValue
watch(
  () => props.modelValue,
  (newVal) => {
    if (!isOpen.value) {
      skipNextSearch = true
      searchQuery.value = newVal || ''
    }
  },
  { immediate: true },
)

// Reset active index when results change
watch(results, () => { activeIndex.value = -1 })

async function performSearch() {
  if (searchQuery.value.length < 2 || !props.journeeId) {
    results.value = []
    isOpen.value = false
    return
  }

  isLoading.value = true
  try {
    const data = await api.get<RefereeResult[]>(
      '/admin/games/autocomplete/referees',
      { q: searchQuery.value, journeeId: props.journeeId, lang: locale.value },
    )
    results.value = data || []
    updateDropdownPosition()
    isOpen.value = true
  }
  catch {
    results.value = []
  }
  finally {
    isLoading.value = false
  }
}

function debouncedSearch() {
  if (debounceTimeout) clearTimeout(debounceTimeout)
  debounceTimeout = setTimeout(() => performSearch(), 300)
}

watch(searchQuery, () => {
  if (skipNextSearch) {
    skipNextSearch = false
    return
  }
  emit('update:matric', 0)
  debouncedSearch()
})

function selectItem(item: RefereeResult) {
  justSelected = true
  skipNextSearch = true
  const value = item.value || item.label
  searchQuery.value = value
  emit('update:modelValue', value)
  emit('update:matric', item.matric ? parseInt(String(item.matric)) || 0 : 0)
  results.value = []
  isOpen.value = false
  activeIndex.value = -1
  emit('confirm')
}

function clearSelection() {
  justSelected = true
  searchQuery.value = ''
  emit('update:modelValue', '')
  emit('update:matric', 0)
  results.value = []
  isOpen.value = false
  activeIndex.value = -1
  emit('confirm')
}

function moveActive(dir: 1 | -1) {
  if (!isOpen.value || selectableResults.value.length === 0) return
  const len = selectableResults.value.length
  // Find current position among selectable items
  const pos = selectableResults.value.findIndex(({ idx }) => idx === activeIndex.value)
  const nextPos = pos === -1 ? (dir === 1 ? 0 : len - 1) : (pos + dir + len) % len
  activeIndex.value = selectableResults.value[nextPos]!.idx
  // Scroll active item into view
  nextTick(() => {
    dropdownRef.value?.querySelector<HTMLElement>(`[data-idx="${activeIndex.value}"]`)?.scrollIntoView({ block: 'nearest' })
  })
}

function handleKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    e.preventDefault()
    isOpen.value = false
    results.value = []
    activeIndex.value = -1
    searchQuery.value = props.modelValue || ''
    inputRef.value?.blur()
    emit('cancel')
  }
  else if (e.key === 'ArrowDown') {
    e.preventDefault()
    if (!isOpen.value && results.value.length > 0) isOpen.value = true
    moveActive(1)
  }
  else if (e.key === 'ArrowUp') {
    e.preventDefault()
    moveActive(-1)
  }
  else if (e.key === 'Tab') {
    // Tab selects the active item if one is highlighted, otherwise commits free text
    if (isOpen.value && activeIndex.value >= 0) {
      e.preventDefault()
      const active = results.value[activeIndex.value]
      if (active && active.type !== 'separator' && active.type !== 'error') {
        selectItem(active)
        return
      }
    }
    // No active item: close dropdown and commit free text (let Tab move focus naturally)
    isOpen.value = false
    results.value = []
    activeIndex.value = -1
    const current = searchQuery.value.trim()
    if (current !== props.modelValue) {
      emit('update:modelValue', current)
      emit('update:matric', 0)
    }
  }
  else if (e.key === 'Enter') {
    e.preventDefault()
    if (isOpen.value && activeIndex.value >= 0) {
      const active = results.value[activeIndex.value]
      if (active && active.type !== 'separator' && active.type !== 'error') {
        selectItem(active)
        return
      }
    }
    isOpen.value = false
    results.value = []
    activeIndex.value = -1
    if (props.compact) {
      justSelected = true
      const current = searchQuery.value.trim()
      if (current !== props.modelValue) {
        emit('update:modelValue', current)
        emit('update:matric', 0)
      }
      emit('confirm')
      inputRef.value?.blur()
    }
    else {
      const current = searchQuery.value.trim()
      if (current !== props.modelValue) {
        emit('update:modelValue', current)
        emit('update:matric', 0)
      }
    }
  }
}

function handleBlur() {
  setTimeout(() => {
    if (justSelected) {
      justSelected = false
      return
    }
    isOpen.value = false
    results.value = []
    activeIndex.value = -1
    const current = searchQuery.value.trim()
    if (current !== props.modelValue) {
      emit('update:modelValue', current)
      emit('update:matric', 0)
      if (props.compact) emit('confirm')
    }
    else if (props.compact) {
      emit('cancel')
    }
  }, 200)
}

onMounted(() => {
  if (inputRef.value) {
    inputRef.value.focus()
    inputRef.value.select()
  }
})

onUnmounted(() => {
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
        maxlength="60"
        :disabled="disabled"
        :placeholder="placeholder || t('games.referee_placeholder')"
        :class="[
          compact
            ? 'w-full px-1 py-0 text-xs border border-primary-400 rounded bg-white dark:bg-header-900 text-header-900 dark:text-header-50'
            : 'w-full px-3 py-2 text-sm border border-header-300 dark:border-header-700 rounded-lg bg-white dark:bg-header-900 text-header-900 dark:text-header-50 focus:ring-2 focus:ring-primary-500 focus:border-primary-500',
          disabled ? 'bg-header-200 dark:bg-header-800 cursor-not-allowed' : '',
        ]"
        @focus="isOpen = results.length > 0"
        @keydown="handleKeydown"
        @blur="handleBlur"
      >
      <div v-if="!compact" class="absolute inset-y-0 right-0 flex items-center pr-2">
        <div
          v-if="isLoading"
          class="animate-spin h-4 w-4 border-2 border-primary-500 border-t-transparent rounded-full"
        />
        <button
          v-else-if="searchQuery && !disabled"
          type="button"
          class="text-header-600 hover:text-header-900"
          @mousedown.prevent="clearSelection"
        >
          <UIcon name="i-heroicons-x-mark" class="w-4 h-4" />
        </button>
      </div>
      <!-- Compact mode: clear button inline -->
      <button
        v-if="compact && searchQuery && !disabled"
        type="button"
        class="absolute right-0.5 top-1/2 -translate-y-1/2 text-header-600 hover:text-header-900"
        @mousedown.prevent="clearSelection"
      >
        <UIcon name="i-heroicons-x-mark" class="w-3 h-3" />
      </button>
    </div>

    <!-- Dropdown results (téléporté dans body pour ne pas être clippé par overflow) -->
    <Teleport to="body">
      <div
        v-if="isOpen && !disabled && results.length > 0"
        class="fixed z-9999 bg-white dark:bg-header-900 border border-header-300 dark:border-header-700 rounded-lg shadow-lg max-h-60 overflow-y-auto"
        :style="dropdownStyle"
      >
        <template v-for="(item, idx) in results" :key="idx">
          <!-- Separator -->
          <div
            v-if="item.type === 'separator'"
            class="px-3 py-1 text-[10px] font-semibold text-header-600 dark:text-header-600 bg-header-50 dark:bg-header-800 uppercase tracking-wider"
          >
            {{ item.label }}
          </div>
          <!-- Error -->
          <div
            v-else-if="item.type === 'error'"
            class="px-3 py-2 text-xs text-danger-500 text-center"
          >
            {{ item.label }}
          </div>
          <!-- Selectable item -->
          <button
            v-else
            :data-idx="idx"
            type="button"
            class="w-full px-3 py-1.5 text-left border-b border-header-50 dark:border-header-800 text-header-900 dark:text-header-50 transition-colors text-xs"
            :class="idx === activeIndex ? 'bg-primary-100 dark:bg-primary-800' : 'hover:bg-primary-50 dark:hover:bg-header-800'"
            @mousedown.prevent="selectItem(item)"
            @mousemove="activeIndex = idx"
          >
            <span class="font-medium">{{ item.label }}</span>
          </button>
        </template>
      </div>
    </Teleport>
  </div>
</template>
