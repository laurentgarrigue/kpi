<script setup lang="ts">
import { CARD_REASONS } from '~/utils/scoringCardReasons'

/**
 * Card reason (motif) picker: a small modal of pictograms replacing the dropdown, so the
 * whole entry stays on the keyboard. The current reason (default « Autre / Non précisé »)
 * is focused on opening: `Entrée` validates it, arrows move, a click picks, `Échap` closes
 * and keeps the current reason. The page then focuses the player number.
 */
const props = defineProps<{
  open: boolean
  modelValue: string
}>()

const emit = defineEmits<{
  'update:modelValue': [reason: string]
  'close': []
}>()

const { t } = useI18n()
const COLUMNS = 3
const buttons = ref<HTMLButtonElement[]>([])

const pick = (reason: string) => {
  emit('update:modelValue', reason)
  emit('close')
}

const focusAt = (index: number) => {
  const count = CARD_REASONS.length
  buttons.value[((index % count) + count) % count]?.focus()
}

const MOVES: Record<string, number> = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -COLUMNS, ArrowDown: COLUMNS }

const onKeydown = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    event.stopPropagation()
    emit('close')
    return
  }
  const move = MOVES[event.key]
  if (move === undefined) return
  event.preventDefault()
  const current = buttons.value.findIndex(b => b === document.activeElement)
  focusAt(current + move)
}

watch(() => props.open, async (open) => {
  if (!open) return
  await nextTick()
  const selected = CARD_REASONS.findIndex(r => r.code === props.modelValue)
  focusAt(selected === -1 ? CARD_REASONS.length - 1 : selected)
}, { immediate: true, flush: 'post' })
</script>

<template>
  <!-- role="alertdialog", not "dialog": assets/css/admin.css styles every [role="dialog"]
       (white cards, fixed centring) for the headless-ui modals, which breaks dark mode here. -->
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
      role="alertdialog"
      aria-modal="true"
      :aria-label="t('scoring.reason.label')"
      @keydown="onKeydown"
    >
      <div class="absolute inset-0 bg-black/50" @click="emit('close')" />

      <div class="relative bg-white dark:bg-header-900 rounded-lg shadow-xl max-w-sm w-full p-4">
        <div class="flex items-center justify-between mb-3">
          <h3 class="text-lg font-semibold text-header-900 dark:text-header-50">{{ t('scoring.reason.label') }}</h3>
          <span class="text-xs text-header-600 dark:text-header-300">
            <kbd class="rounded border border-current/40 px-1 font-mono">↵</kbd> {{ t('scoring.reason.hint_enter') }}
            · <kbd class="rounded border border-current/40 px-1 font-mono">Esc</kbd> {{ t('scoring.reason.hint_esc') }}
          </span>
        </div>
        <div class="grid grid-cols-3 gap-2">
          <button
            v-for="reason in CARD_REASONS"
            :key="reason.code"
            ref="buttons"
            type="button"
            class="flex flex-col items-center justify-center gap-1 rounded-lg border-2 p-2 text-center text-sm leading-tight transition-colors focus:outline-none focus-visible:ring-4 focus-visible:ring-primary-400 focus-visible:bg-primary-100 dark:focus-visible:bg-header-600"
            :class="modelValue === reason.code
              ? 'border-primary-500 bg-primary-50 dark:bg-primary-950 text-header-900 dark:text-header-50'
              : 'bg-white dark:bg-header-900 border-header-200 dark:border-header-700 text-header-800 dark:text-header-100 hover:bg-header-50 dark:hover:bg-header-800'"
            :aria-pressed="modelValue === reason.code"
            @click="pick(reason.code)"
          >
            <!-- eslint-disable-next-line vue/no-v-html -- static pictogram of utils/scoringCardReasons -->
            <svg class="h-9 w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" v-html="reason.icon" />
            <span>{{ t('scoring.reason.' + reason.code) }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
