import type { Ref } from 'vue'
import { SUGGESTION_DELAY_MS, searchQuery } from '~/utils/search'

/**
 * Suggestions while typing (SRC-02, TEA-01): requested in the browser only, `SUGGESTION_DELAY_MS` after the last
 * keystroke, once the query is long enough; a newer query discards the answer of an older one.
 */
export function useSuggestions<T>(query: Ref<string>, apiPath: (query: string) => string) {
  const api2 = useApi2()
  const results = ref<T | null>(null) as Ref<T | null>
  const failed = ref(false)
  let timer: ReturnType<typeof setTimeout> | undefined
  let latest = 0

  watch(query, (value) => {
    clearTimeout(timer)
    const current = searchQuery(value)
    if (current === null) {
      results.value = null
      return
    }
    timer = setTimeout(async () => {
      const request = ++latest
      try {
        // Typed by the caller: the Nitro route inference of `$fetch` does not apply to api2.
        const answer = await api2<T>(apiPath(current)) as T
        if (request === latest) {
          results.value = answer
          failed.value = false
        }
      } catch {
        if (request === latest) {
          results.value = null
          failed.value = true
        }
      }
    }, SUGGESTION_DELAY_MS)
  })

  onBeforeUnmount(() => clearTimeout(timer))

  return { results, failed }
}
