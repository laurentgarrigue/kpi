import type { MaybeRefOrGetter } from 'vue'

/** Part of the viewport below which a heading is not yet « being read »: the top 30 % counts (CNT-08). */
const READING_ZONE = '0px 0px -70% 0px'

/**
 * Anchor of the heading being read, among `ids` (the « On this page » summary follows the reading, CNT-08). Browser
 * only: `null` during the server rendering and without IntersectionObserver.
 */
export function useReadingPosition(ids: MaybeRefOrGetter<string[]>) {
  const current = ref<string | null>(null)
  let observer: IntersectionObserver | null = null

  function observe() {
    observer?.disconnect()
    if (typeof IntersectionObserver === 'undefined') {
      return
    }
    observer = new IntersectionObserver((entries) => {
      const visible = entries.find(entry => entry.isIntersecting)
      if (visible) {
        current.value = visible.target.id
      }
    }, { rootMargin: READING_ZONE })
    for (const id of toValue(ids)) {
      const heading = document.getElementById(id)
      if (heading) {
        observer.observe(heading)
      }
    }
  }

  onMounted(observe)
  watch(() => toValue(ids), () => nextTick(observe))
  onBeforeUnmount(() => observer?.disconnect())
  return current
}
