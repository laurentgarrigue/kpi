import type { MaybeRefOrGetter } from 'vue'
import type { MedalCatalog, MedalItem } from '~/utils/medals'

/**
 * Published medals, labels and statistics (FEATURE_MATCH_MEDALS.md § 6.4), explanations rendered with the thresholds
 * of `season` (the most recent computed season without it). Never blocks a page: unavailable → no medal shown.
 */
export async function useMedalCatalog(season?: MaybeRefOrGetter<string | null | undefined>) {
  const { data, error } = await useApiResource<MedalCatalog>(() => {
    const value = toValue(season)
    return value ? `/medals/catalog?season=${value}` : '/medals/catalog'
  }, { notFoundIsError: false })
  const byCode = computed(() => new Map<string, MedalItem>((data.value?.items ?? []).map(item => [item.code, item])))

  return { catalog: data, byCode, error }
}
