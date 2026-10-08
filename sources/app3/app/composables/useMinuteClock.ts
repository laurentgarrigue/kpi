import { useIntervalFn } from '@vueuse/core'
import type { ShallowRef } from 'vue'

const MINUTE_MS = 60_000

/** Current time, updated every minute in the browser (« upcoming » games, live refresh); fixed on the server. */
export function useMinuteClock(): Readonly<ShallowRef<Date>> {
  const now = shallowRef(new Date())
  if (import.meta.client) {
    useIntervalFn(() => {
      now.value = new Date()
    }, MINUTE_MS)
  }
  return now
}
