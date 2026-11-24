<script setup lang="ts">
/**
 * Fine adjust of a countdown — ONE system for the game clock, the shot clock and the
 * penalties (spec §6.4/§6.5/§7.4): the countdown itself is the trigger, a click opens a
 * popover under it with the ±10/±1 s steps around a live copy of the value. The copy is what
 * makes the correction readable: the popover may cover the countdown it adjusts (a penalty
 * chip at the bottom of the board, a narrow screen), the operator still sees the result.
 *
 * `disabled` (post-match, no scoring right) or `canAdjust` false (clock running…) make the
 * countdown a plain display: no popover at all rather than greyed steps (2026-11-08) — only a
 * `lockedHint` tooltip says why. A popover left open closes when the clock starts.
 * Props down / events up: emits `adjust` with the delta in seconds, the page applies it.
 */
const props = withDefaults(defineProps<{
  /** The value as displayed ("9:52", "45", "01:30") — repeated inside the popover. */
  display: string
  steps?: readonly number[]
  canAdjust?: boolean
  lockedHint?: string
  /** Accessible name of the trigger (what is being adjusted). */
  label?: string
  disabled?: boolean
  triggerClass?: string
}>(), {
  steps: () => [-10, -1, 1, 10],
  canAdjust: true,
  lockedHint: '',
  label: '',
  disabled: false,
  triggerClass: ''
})

const emit = defineEmits<{ adjust: [deltaSec: number] }>()

const minus = computed(() => props.steps.filter(d => d < 0))
const plus = computed(() => props.steps.filter(d => d > 0))
const stepLabel = (d: number): string => (d > 0 ? `+${d}` : `−${-d}`)

// Explicit contrast in both themes: generic ghost buttons were barely readable in dark mode.
const STEP_CLASS = 'min-w-11 px-2 py-1.5 rounded-md text-sm font-semibold tabular-nums cursor-pointer '
  + 'bg-header-100 text-header-900 hover:bg-header-200 dark:bg-header-700 dark:text-header-50 dark:hover:bg-header-600 '
  + 'focus-visible:outline-2 focus-visible:outline-primary-500'
</script>

<template>
  <span v-if="disabled || !canAdjust" :class="triggerClass" :title="disabled ? undefined : lockedHint || undefined"><slot /></span>
  <UPopover
    v-else
    :content="{ side: 'bottom', align: 'center' }"
    :ui="{ content: 'ring-header-200 dark:ring-header-600' }"
  >
    <button
      type="button"
      class="cursor-pointer rounded focus-visible:outline-2 focus-visible:outline-primary-500"
      :class="triggerClass"
      :title="label"
      :aria-label="label || undefined"
    ><slot /></button>
    <template #content>
      <!-- Explicit surface + text in both themes. Two admin.css traps (unlayered, they beat the
           utilities): [role="dialog"] > div > div gets a white background — the popover content
           IS a role="dialog", hence a <section> here, never a <div>; and any [class*="bg-white"]
           gets dark text in light mode — no such class here or on the trigger. -->
      <section class="p-2 space-y-1 rounded-md bg-header-50 text-header-950 dark:bg-header-900 dark:text-header-50">
        <div class="flex items-center gap-1">
          <button
            v-for="d in minus" :key="d"
            type="button" :class="STEP_CLASS"
            @click="emit('adjust', d)"
          >{{ stepLabel(d) }}</button>
          <span class="scoring-digits text-3xl min-w-24 px-2 text-center text-header-950 dark:text-white" aria-live="polite">{{ display }}</span>
          <button
            v-for="d in plus" :key="d"
            type="button" :class="STEP_CLASS"
            @click="emit('adjust', d)"
          >{{ stepLabel(d) }}</button>
        </div>
      </section>
    </template>
  </UPopover>
</template>
