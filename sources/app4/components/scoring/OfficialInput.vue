<script setup lang="ts">
import type { OfficialRole, OfficialSuggestion, OfficialSuggestionsResponse } from '~/types/scoring'

/**
 * Official field with suggestions (spec §7.2). The players of the designated referee team
 * come first — listed as soon as the field gets focus — then, once two characters are
 * typed, any other licence holder. Free text stays allowed: the field stores what the
 * operator typed or picked (legacy format "NOM Prénom (matric)" for a pick).
 *
 * The page owns the API: suggestions come through the `suggest` prop.
 */
const props = defineProps<{
  modelValue: string
  role: OfficialRole
  suggest: (role: OfficialRole, q: string) => Promise<OfficialSuggestionsResponse>
  inputId?: string
  disabled?: boolean
}>()

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const { t } = useI18n()

const SEARCH_DEBOUNCE_MS = 250

const team = ref<string | null>(null)
const items = ref<OfficialSuggestion[]>([])
const open = ref(false)
const active = ref(-1)
const listId = useId()

let searchTimer: ReturnType<typeof setTimeout> | null = null
let requestSeq = 0

const search = async (q: string) => {
  const seq = ++requestSeq
  try {
    const res = await props.suggest(props.role, q)
    if (seq !== requestSeq) return // a newer search already answered
    team.value = res.team
    items.value = res.items
    active.value = -1
    open.value = res.items.length > 0
  } catch { /* useApi already toasts */ }
}

const onFocus = () => {
  if (!props.disabled) void search(props.modelValue.trim())
}
const onInput = (value: string | number) => {
  emit('update:modelValue', String(value))
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => { void search(String(value).trim()) }, SEARCH_DEBOUNCE_MS)
}
const close = () => {
  open.value = false
  active.value = -1
}
const pick = (item: OfficialSuggestion) => {
  emit('update:modelValue', item.value)
  close()
}
const onKeydown = (e: KeyboardEvent) => {
  if (!open.value) return
  if (e.key === 'ArrowDown') {
    e.preventDefault()
    active.value = (active.value + 1) % items.value.length
  } else if (e.key === 'ArrowUp') {
    e.preventDefault()
    active.value = active.value <= 0 ? items.value.length - 1 : active.value - 1
  } else if (e.key === 'Enter' && active.value >= 0) {
    e.preventDefault()
    pick(items.value[active.value]!)
  } else if (e.key === 'Escape') {
    close()
  }
}
onUnmounted(() => { if (searchTimer) clearTimeout(searchTimer) })

/** A group heading is shown before the first item of each group. */
const startsGroup = (i: number) => i === 0 || items.value[i - 1]!.group !== items.value[i]!.group
const groupLabel = (g: OfficialSuggestion['group']) =>
  g === 'team' ? (team.value ?? '') : t('scoring.settings.other_licensees')
</script>

<template>
  <div class="relative">
    <UInput
      :id="inputId"
      :model-value="modelValue"
      size="sm"
      class="w-full"
      autocomplete="off"
      role="combobox"
      aria-autocomplete="list"
      :aria-expanded="open"
      :aria-controls="listId"
      :aria-activedescendant="active >= 0 ? `${listId}-${active}` : undefined"
      :disabled="disabled"
      @update:model-value="onInput"
      @focus="onFocus"
      @blur="close"
      @keydown="onKeydown"
    />
    <ul
      v-if="open"
      :id="listId"
      role="listbox"
      class="absolute z-50 mt-1 w-full max-h-64 overflow-auto rounded-lg border border-header-200 dark:border-header-700 bg-white dark:bg-header-900 shadow-lg py-1 text-sm"
    >
      <template v-for="(item, i) in items" :key="item.value">
        <li v-if="startsGroup(i)" role="presentation" class="px-3 pt-2 pb-1 text-xs text-header-500 dark:text-header-400">
          {{ groupLabel(item.group) }}
        </li>
        <li
          :id="`${listId}-${i}`"
          role="option"
          :aria-selected="active === i"
          class="px-3 py-1.5 cursor-pointer"
          :class="active === i ? 'bg-primary-100 dark:bg-primary-900/50' : 'hover:bg-header-50 dark:hover:bg-header-800'"
          @mousedown.prevent="pick(item)"
          @mouseenter="active = i"
        >{{ item.value }}</li>
      </template>
    </ul>
  </div>
</template>
