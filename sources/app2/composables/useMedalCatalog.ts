import type { MedalItem } from '~/utils/medals'

/**
 * Medals published on app2 (FEATURE_MATCH_MEDALS.md § 9.1): the catalogue by code, loaded on demand. Medals are
 * optional: a failure leaves the catalogue empty and never blocks the page that shows them.
 */
export function useMedalCatalog() {
  const { getApi } = useApi()
  const catalog = ref(new Map<string, MedalItem>())

  const load = async () => {
    try {
      const response = await getApi('/medals/catalog?surface=app2')
      if (response.ok) {
        const body = await response.json() as { items: MedalItem[] }
        catalog.value = new Map(body.items.map(item => [item.code, item]))
      }
    } catch {
      catalog.value = new Map()
    }
  }

  return { catalog, load }
}
