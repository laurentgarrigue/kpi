import { withQuery } from 'ufo'
import { MAIN_MENU, isActiveLink, resolveMenu, stripLocalePrefix, withEditorialMenu, type EditorialMenuEntry, type ResolvedLink } from '~/utils/navigation'
import { cmsLanguageQuery } from '~/utils/news'
import { PREFIXED_LOCALES } from '~~/shared/locales'

/**
 * Main menu resolved for the current language, configuration and route (SITE_NAVIGATION.md), with the editorial
 * entries of api2 `GET /menu` (PAGE_CONTENT.md § 3): rendered by the server, cached with the page; an api2 error
 * only hides the editorial entry (CNT-04).
 */
export async function useMainMenu() {
  const { locale } = useI18n()
  const localePath = useLocalePath()
  const route = useRoute()
  const api2 = useApi2()
  const { legacyBaseUrl, app2BaseUrl } = useRuntimeConfig().public

  const { data: editorial } = await useAsyncData(
    () => `editorial-menu:${locale.value}`,
    () => api2<EditorialMenuEntry[]>(withQuery('/menu', cmsLanguageQuery(locale.value))).catch(() => []),
  )

  const items = computed(() => withEditorialMenu(
    resolveMenu(MAIN_MENU, {
      locale: locale.value,
      legacyBaseUrl,
      app2BaseUrl,
      localePath: path => localePath(path),
    }),
    editorial.value ?? [],
    path => localePath(path),
  ))

  const currentPath = computed(() => stripLocalePrefix(route.path, PREFIXED_LOCALES))

  const isActive = (link: ResolvedLink): boolean => link.kind === 'internal' && isActiveLink(link.to, currentPath.value)

  return { items, isActive }
}
