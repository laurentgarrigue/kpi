<script setup lang="ts">
import { scrollArrows } from '~/utils/scroll'

// Floating « back to top » / « go to bottom » arrows, as in app4 (SITE_LAYOUT.md § 2, LAY-11). Hidden until the
// page is mounted: the server does not know the scroll position.
const arrows = ref({ up: false, down: false })

const pageHeight = () => document.documentElement.scrollHeight

function update() {
  arrows.value = scrollArrows(window.scrollY, pageHeight(), window.innerHeight)
}

function scrollToY(top: number) {
  const reduced = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
  window.scrollTo({ top, behavior: reduced ? 'auto' : 'smooth' })
}

let observer: ResizeObserver | undefined
onMounted(() => {
  update()
  window.addEventListener('scroll', update, { passive: true })
  window.addEventListener('resize', update, { passive: true })
  // Content loaded or expanded after mounting changes the page height.
  if (typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(update)
    observer.observe(document.body)
  }
})
onBeforeUnmount(() => {
  window.removeEventListener('scroll', update)
  window.removeEventListener('resize', update)
  observer?.disconnect()
})
</script>

<template>
  <div class="fixed bottom-4 right-4 z-40 flex flex-col items-center gap-2 print:hidden" data-testid="scroll-arrows">
    <button
      v-if="arrows.up"
      type="button"
      class="rounded-full bg-kpi-blue-600 p-3 text-white shadow-lg hover:bg-kpi-blue-700"
      :title="$t('a11y.scrollTop')"
      :aria-label="$t('a11y.scrollTop')"
      data-testid="scroll-top"
      @click="scrollToY(0)"
    >
      <UIcon name="i-heroicons-arrow-up" class="size-6" aria-hidden="true" />
    </button>
    <button
      v-if="arrows.down"
      type="button"
      class="rounded-full bg-kpi-blue-600 p-3 text-white shadow-lg hover:bg-kpi-blue-700"
      :title="$t('a11y.scrollBottom')"
      :aria-label="$t('a11y.scrollBottom')"
      data-testid="scroll-bottom"
      @click="scrollToY(pageHeight())"
    >
      <UIcon name="i-heroicons-arrow-down" class="size-6" aria-hidden="true" />
    </button>
  </div>
</template>
