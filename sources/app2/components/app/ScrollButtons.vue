<template>
  <div class="fixed bottom-8 right-4 flex flex-col gap-2">
    <button
      class="bg-bar hover:bg-bar-hover text-white font-bold p-3 rounded-full cursor-pointer"
      :title="t('nav.ScrollTop')"
      @click="scrollToTop"
    >
      <UIcon name="i-heroicons-arrow-up" class="h-6 w-6" />
      <span class="sr-only">{{ t('nav.ScrollTop') }}</span>
    </button>
    <button
      v-if="hasContentBelow"
      class="bg-bar hover:bg-bar-hover text-white font-bold p-3 rounded-full cursor-pointer"
      :title="t('nav.ScrollBottom')"
      @click="scrollToBottom"
    >
      <UIcon name="i-heroicons-arrow-down" class="h-6 w-6" />
      <span class="sr-only">{{ t('nav.ScrollBottom') }}</span>
    </button>
  </div>
</template>

<script setup>
// Floating « back to top » button, with a « go to bottom » one below it while there is content further down
// the page (it is re-evaluated on scroll, on resize and when the page content grows, e.g. after data loads).
const { t } = useI18n()

// Distance from the bottom (px) under which the page is considered scrolled to the end.
const BOTTOM_THRESHOLD = 50

const hasContentBelow = ref(false)
let resizeObserver = null

const update = () => {
  const { scrollHeight } = document.documentElement
  hasContentBelow.value = window.scrollY + window.innerHeight < scrollHeight - BOTTOM_THRESHOLD
}

const scrollToTop = () => {
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const scrollToBottom = () => {
  window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' })
}

onMounted(() => {
  window.addEventListener('scroll', update, { passive: true })
  window.addEventListener('resize', update)
  resizeObserver = new ResizeObserver(update)
  resizeObserver.observe(document.body)
  update()
})

onBeforeUnmount(() => {
  window.removeEventListener('scroll', update)
  window.removeEventListener('resize', update)
  resizeObserver?.disconnect()
})
</script>
