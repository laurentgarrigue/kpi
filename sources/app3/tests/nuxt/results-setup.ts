import { h } from 'vue'
import { NuxtPage } from '#components'
import { mountSuspended } from '@nuxt/test-utils/runtime'

/** Mounts the page tree of a route (parent page + child tab), as the router would. */
export function mountRoute(route: string) {
  return mountSuspended({ setup: () => () => h(NuxtPage) }, { route })
}

/** The route renders the site's 404 (error state set by `useApiResource`); the real HTTP status is checked in e2e. */
export async function expectNotFound(route: string) {
  await mountRoute(route)
  const error = useError()
  const statusCode = error.value?.statusCode
  await clearError()
  return statusCode
}
