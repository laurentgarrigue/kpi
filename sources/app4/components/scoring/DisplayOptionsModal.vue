<script setup lang="ts">
import { DISPLAY_DEFAULTS, mergeStoredDisplayOptions, type DisplayKind, type DisplayOptions } from '~/utils/scoringDisplayOptions'

/**
 * Options of a full-screen display, asked before it opens (PAGE_SCORING.md §0.15): the
 * display is a popup without an editable address bar, so `?theme=light` & co. can no longer
 * be typed afterwards. The last choice is remembered on the device. "Open" emits the
 * options; the page opens the window (still within the click: popups are allowed).
 * Same hand-rolled modal pattern as ShortcutsModal.vue.
 */
const props = defineProps<{
  /** The display being opened, null when the dialog is closed. */
  kind: DisplayKind | null
}>()

const emit = defineEmits<{
  close: []
  open: [kind: DisplayKind, options: DisplayOptions[DisplayKind]]
}>()

const { t } = useI18n()

const STORAGE_PREFIX = 'kpi.scoring.display.'
const options = ref<Record<string, boolean | string>>({})

const load = (kind: DisplayKind) => {
  let stored: Record<string, unknown> = {}
  try {
    stored = JSON.parse(window.localStorage.getItem(STORAGE_PREFIX + kind) ?? '{}')
  } catch { /* unavailable or corrupted storage → defaults */ }
  options.value = { ...mergeStoredDisplayOptions(kind, stored) }
}

watch(() => props.kind, (kind) => { if (kind) load(kind) }, { immediate: true })

/** The boolean options of this display, in their declaration order (theme apart). */
const switches = computed(() => (props.kind
  ? Object.keys(DISPLAY_DEFAULTS[props.kind]).filter(key => key !== 'theme')
  : []))

const submit = () => {
  const kind = props.kind
  if (!kind) return
  try {
    window.localStorage.setItem(STORAGE_PREFIX + kind, JSON.stringify(options.value))
  } catch { /* the choice still applies to this opening */ }
  emit('open', kind, options.value as unknown as DisplayOptions[DisplayKind])
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="kind"
      class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
      @click.self="emit('close')"
    >
      <div class="absolute inset-0 bg-black/50" @click="emit('close')" />

      <form class="relative bg-white dark:bg-header-900 rounded-lg shadow-xl max-w-sm w-full" @submit.prevent="submit">
        <div class="p-4 border-b border-header-200 dark:border-header-800">
          <h3 class="text-lg font-semibold text-header-900 dark:text-header-50">
            {{ t('scoring.display_options.title_' + kind) }}
          </h3>
          <p class="text-xs text-header-600 dark:text-header-300 mt-1">{{ t('scoring.display_options.hint') }}</p>
        </div>

        <div class="p-4 space-y-4">
          <fieldset>
            <legend class="text-sm font-medium text-header-800 dark:text-header-100 mb-1">{{ t('scoring.display_options.theme') }}</legend>
            <div class="inline-flex rounded-lg border border-header-200 dark:border-header-700 p-0.5">
              <UButton
                v-for="theme in (['dark', 'light'] as const)" :key="theme"
                size="sm"
                :variant="options.theme === theme ? 'solid' : 'ghost'"
                :color="options.theme === theme ? 'primary' : 'neutral'"
                :aria-pressed="options.theme === theme"
                @click="options.theme = theme"
              >{{ t('scoring.display_options.theme_' + theme) }}</UButton>
            </div>
          </fieldset>

          <USwitch
            v-for="key in switches" :key="key"
            :model-value="Boolean(options[key])"
            :label="t('scoring.display_options.' + kind + '_' + key)"
            @update:model-value="options[key] = $event"
          />
        </div>

        <div class="flex justify-end gap-2 p-4 pt-3 border-t border-header-200 dark:border-header-800 bg-header-50 dark:bg-header-950 rounded-b-lg">
          <UButton size="sm" variant="ghost" color="neutral" @click="emit('close')">{{ t('common.cancel') }}</UButton>
          <UButton size="sm" type="submit" icon="i-heroicons-arrow-top-right-on-square">{{ t('scoring.display_options.open') }}</UButton>
        </div>
      </form>
    </div>
  </Teleport>
</template>
