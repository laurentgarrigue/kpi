<script setup lang="ts">
import type { SearchHit } from '~/utils/search'

// Search field with keyboard-accessible suggestions (ARIA « combobox » pattern, SRC-02, TEA-01). Without
// JavaScript it is a plain GET form to `action`. The parent fetches the suggestions (`groups`) from `query`.
const props = defineProps<{
  id: string
  action: string
  label: string
  placeholder?: string
  groups: { label: string, hits: SearchHit[] }[]
  seeAll?: boolean
  compact?: boolean
}>()
const query = defineModel<string>({ default: '' })
const localePath = useLocalePath()
const { t } = useI18n()

const open = ref(false)
const active = ref(-1)
const options = computed(() => props.groups.flatMap(group => group.hits))
const listId = `${props.id}-list`
const optionId = (index: number) => `${props.id}-option-${index}`
const expanded = computed(() => open.value && options.value.length > 0)

watch(options, () => {
  active.value = -1
  open.value = options.value.length > 0
})

function move(step: number) {
  if (options.value.length === 0) {
    return
  }
  open.value = true
  active.value = (active.value + step + options.value.length) % options.value.length
}

function close() {
  open.value = false
  active.value = -1
}

function choose(event: KeyboardEvent) {
  const hit = options.value[active.value]
  if (expanded.value && hit) {
    event.preventDefault()
    close()
    navigateTo(localePath(hit.to))
  }
}

const announcement = computed(() => (open.value && query.value ? t('common.suggestionsCount', options.value.length) : ''))
</script>

<template>
  <form method="get" :action="localePath(action)" role="search" class="relative" :class="compact ? 'w-full' : ''" @keydown.esc="close">
    <label :for="id" :class="compact ? 'sr-only' : 'mb-1 block text-sm'">{{ label }}</label>
    <div class="flex gap-2">
      <input
        :id="id"
        v-model="query"
        name="q"
        type="search"
        autocomplete="off"
        :placeholder="placeholder"
        maxlength="50"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="expanded"
        :aria-controls="listId"
        :aria-activedescendant="active >= 0 ? optionId(active) : undefined"
        class="w-full rounded border border-line px-2 py-1"
        data-testid="search-input"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter="choose"
        @blur="close"
      >
      <UButton type="submit" variant="outline" icon="i-heroicons-magnifying-glass" :aria-label="label" />
    </div>
    <div v-show="expanded" :id="listId" role="listbox" :aria-label="$t('common.suggestions')" class="absolute z-40 mt-1 w-full min-w-72 rounded border border-line bg-white p-2 shadow-lg" data-testid="suggestions">
      <template v-for="group in groups" :key="group.label">
        <p class="px-2 pt-2 text-xs font-semibold uppercase text-ink/70" aria-hidden="true">{{ group.label }}</p>
        <NuxtLink
          v-for="hit in group.hits"
          :id="optionId(options.indexOf(hit))"
          :key="hit.key"
          :to="localePath(hit.to)"
          role="option"
          :aria-selected="active === options.indexOf(hit)"
          class="block rounded px-2 py-1"
          :class="active === options.indexOf(hit) ? 'bg-kpi-blue-100' : 'hover:bg-kpi-blue-50'"
          @mousedown.prevent
          @click="close"
        >
          <span class="font-semibold">{{ hit.label }}</span>
          <span v-if="hit.detail" class="block text-xs text-ink/70">{{ hit.detail }}</span>
        </NuxtLink>
      </template>
      <NuxtLink v-if="seeAll" :to="{ path: localePath(action), query: { q: query } }" class="mt-1 block border-t border-line px-2 pt-2 text-sm underline" @mousedown.prevent @click="close">
        {{ $t('common.seeAllResults') }}
      </NuxtLink>
    </div>
    <p class="sr-only" aria-live="polite">{{ announcement }}</p>
  </form>
</template>
