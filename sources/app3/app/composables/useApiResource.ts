import type { MaybeRefOrGetter } from 'vue'

const NOT_FOUND = 404

/**
 * One api2 resource of a results page (`useAsyncData` + `useApi2`), refetched when its path changes; a `null`
 * path means « nothing to fetch yet ». An api2 404 (unknown or unpublished) becomes the site's 404 page
 * (CMP-14, EVT-05, GRP-04) unless `notFoundIsError` is false; other errors are left to the page, which shows an
 * « unavailable » state.
 */
export async function useApiResource<T>(path: MaybeRefOrGetter<string | null>, options: { notFoundIsError?: boolean } = {}) {
  const api2 = useApi2()
  const result = await useAsyncData(
    () => `api2:${toValue(path) ?? 'none'}`,
    () => {
      const current = toValue(path)
      return current === null ? Promise.resolve(null) : api2<T>(current)
    },
  )

  if (options.notFoundIsError !== false) {
    const raiseNotFound = () => {
      if (result.error.value?.statusCode === NOT_FOUND) {
        throw createError({ statusCode: NOT_FOUND, fatal: true })
      }
    }
    raiseNotFound()
    watch(result.error, () => {
      try {
        raiseNotFound()
      } catch (error) {
        showError(error as Parameters<typeof showError>[0])
      }
    })
  }

  return result
}
