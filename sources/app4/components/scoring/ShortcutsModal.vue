<script setup lang="ts">
import { SHORTCUT_ACTIONS, shortcutKeyLabel, type ShortcutAction } from '~/composables/useScoringShortcuts'

/**
 * Keyboard shortcuts settings (spec §6.5 — per-device preference, decision §0.9).
 * Click a row's "change" button then press the desired key; one key = one action
 * (assigning a key steals it from any other action). Same hand-rolled modal pattern
 * as admin/ConfirmModal.vue.
 */
const props = defineProps<{
  open: boolean
  bindings: Record<ShortcutAction, string>
}>()

const emit = defineEmits<{
  close: []
  set: [action: ShortcutAction, key: string]
  reset: []
}>()

const { t } = useI18n()

// Action awaiting its new key (capture mode).
const capturing = ref<ShortcutAction | null>(null)

const onCaptureKey = (event: KeyboardEvent) => {
  if (!capturing.value) return
  event.preventDefault()
  event.stopPropagation()
  if (event.key !== 'Escape') emit('set', capturing.value, event.key)
  capturing.value = null
}

watch(() => props.open, (open) => {
  if (typeof window === 'undefined') return
  if (open) window.addEventListener('keydown', onCaptureKey, true)
  else {
    window.removeEventListener('keydown', onCaptureKey, true)
    capturing.value = null
  }
})
onUnmounted(() => {
  if (typeof window !== 'undefined') window.removeEventListener('keydown', onCaptureKey, true)
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
      @click.self="emit('close')"
    >
      <div class="absolute inset-0 bg-black/50" @click="emit('close')" />

      <div class="relative bg-white dark:bg-header-900 rounded-lg shadow-xl max-w-md w-full">
        <div class="p-4 border-b border-header-200 dark:border-header-800">
          <h3 class="text-lg font-semibold text-header-900 dark:text-header-50">
            {{ t('scoring.shortcuts.title') }}
          </h3>
          <p class="text-xs text-header-600 dark:text-header-300 mt-1">{{ t('scoring.shortcuts.hint') }}</p>
        </div>

        <div class="p-4 space-y-1">
          <div
            v-for="action in SHORTCUT_ACTIONS"
            :key="action"
            class="flex items-center justify-between gap-2 px-2 py-1 rounded hover:bg-header-50 dark:hover:bg-header-800"
          >
            <span class="text-sm">{{ t('scoring.shortcuts.' + action) }}</span>
            <div class="flex items-center gap-2">
              <UBadge color="neutral" variant="soft" class="font-mono min-w-16 justify-center">
                {{ capturing === action ? t('scoring.shortcuts.press_key') : shortcutKeyLabel(bindings[action]) }}
              </UBadge>
              <UButton
                size="xs"
                variant="outline"
                :disabled="capturing !== null && capturing !== action"
                @click="capturing = capturing === action ? null : action"
              >
                {{ t('scoring.shortcuts.change') }}
              </UButton>
            </div>
          </div>
        </div>

        <div class="flex justify-between gap-2 p-4 pt-3 border-t border-header-200 dark:border-header-800 bg-header-50 dark:bg-header-950 rounded-b-lg">
          <UButton size="xs" variant="ghost" @click="emit('reset')">
            {{ t('scoring.shortcuts.reset') }}
          </UButton>
          <UButton size="xs" @click="emit('close')">
            {{ t('common.close') }}
          </UButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>
