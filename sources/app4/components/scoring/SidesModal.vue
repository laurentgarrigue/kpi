<script setup lang="ts">
import type { Sides } from '~/utils/scoringSides'

/**
 * Pitch sides window (PAGE_SCORING.md §0.18), seen from the scoring table and the head referee:
 *  - `check` (D15): opened at kick-off — the operator checks which team is on the left and on the
 *    right before the clock of the first period starts; « Inverser » swaps the preview, « Les
 *    côtés sont bons » records it;
 *  - `swap` (D4): every manual swap is confirmed — the window names the period and shows the
 *    sides AFTER the swap; the page applies it on confirmation.
 * Emits `confirm` with the sides shown, `close` otherwise. Same alertdialog shell as
 * CardReasonModal (admin.css styles every [role="dialog"]).
 */
const props = defineProps<{
  open: boolean
  mode: 'check' | 'swap'
  /** check: the sides proposed; swap: the sides after the swap. */
  sides: Sides
  teamA: string
  teamB: string
  period: string
  /** Human label of the period (« Période 2 »); the code otherwise. */
  periodLabel?: string
}>()

const emit = defineEmits<{
  confirm: [sides: Sides]
  close: []
}>()

const { t } = useI18n()

const preview = ref<Sides>(props.sides)
watch(() => [props.open, props.sides] as const, ([open, sides]) => { if (open) preview.value = sides }, { immediate: true })

const halves = computed(() => {
  const a = { letter: 'A', name: props.teamA }
  const b = { letter: 'B', name: props.teamB }
  return preview.value === 'A_LEFT' ? [a, b] : [b, a]
})

const swapPreview = () => { preview.value = preview.value === 'A_LEFT' ? 'A_RIGHT' : 'A_LEFT' }
const confirm = () => emit('confirm', preview.value)

const onKeydown = (event: KeyboardEvent) => {
  if (event.key !== 'Escape') return
  event.preventDefault()
  event.stopPropagation()
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[9999] flex items-center justify-center p-4"
      role="alertdialog"
      aria-modal="true"
      :aria-label="mode === 'check' ? t('scoring.sides.check_title') : t('scoring.sides.swap_title')"
      @keydown="onKeydown"
    >
      <div class="absolute inset-0 bg-black/50" @click="emit('close')" />
      <div class="relative bg-white dark:bg-header-900 rounded-lg shadow-xl max-w-lg w-full p-5 space-y-4 text-header-900 dark:text-header-50">
        <h3 class="text-lg font-semibold">
          {{ mode === 'check' ? t('scoring.sides.check_title') : t('scoring.sides.swap_title') }}
        </h3>
        <p class="text-sm">
          {{ mode === 'check' ? t('scoring.sides.check_message') : t('scoring.sides.swap_message', { period: periodLabel ?? period }) }}
        </p>

        <!-- The pitch seen from the table: left half | right half -->
        <div class="grid grid-cols-2 rounded-lg border-2 border-header-400 dark:border-header-500 overflow-hidden">
          <div
            v-for="(half, i) in halves" :key="half.letter"
            :data-half="i === 0 ? 'L' : 'R'"
            class="p-3 text-center space-y-1"
            :class="i === 0 ? 'border-r-2 border-dashed border-header-400 dark:border-header-500' : ''"
          >
            <div class="text-xs uppercase tracking-wide text-header-600 dark:text-header-300">
              {{ i === 0 ? t('scoring.sides.left') : t('scoring.sides.right') }}
            </div>
            <div class="mx-auto w-fit rounded-md border-2 border-current px-3 text-3xl font-bold leading-tight">{{ half.letter }}</div>
            <div class="font-semibold break-words">{{ half.name }}</div>
          </div>
        </div>
        <p class="text-xs text-center text-header-600 dark:text-header-300">
          <UIcon name="i-heroicons-user" class="size-3.5 align-[-2px]" /> {{ t('scoring.sides.seen_from') }}
        </p>

        <div class="flex flex-wrap items-center justify-end gap-2">
          <UButton variant="ghost" color="neutral" @click="emit('close')">{{ t('scoring.sides.cancel') }}</UButton>
          <UButton
            v-if="mode === 'check'"
            variant="outline"
            color="neutral"
            icon="i-heroicons-arrows-right-left"
            @click="swapPreview"
          >{{ t('scoring.sides.check_swap') }}</UButton>
          <UButton :color="mode === 'check' ? 'success' : 'warning'" @click="confirm">
            {{ mode === 'check' ? t('scoring.sides.check_confirm') : t('scoring.sides.swap_confirm') }}
          </UButton>
        </div>
      </div>
    </div>
  </Teleport>
</template>
