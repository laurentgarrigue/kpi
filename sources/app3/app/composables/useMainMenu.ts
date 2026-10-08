import { MAIN_MENU, isActiveLink, resolveMenu, stripLocalePrefix, type ResolvedLink } from '~/utils/navigation'
import { PREFIXED_LOCALES } from '~~/shared/locales'

/** Main menu resolved for the current language, configuration and route (SITE_NAVIGATION.md). */
export function useMainMenu() {
  const { locale } = useI18n()
  const localePath = useLocalePath()
  const route = useRoute()
  const { legacyBaseUrl, app2BaseUrl } = useRuntimeConfig().public

  const items = computed(() =>
    resolveMenu(MAIN_MENU, {
      locale: locale.value,
      legacyBaseUrl,
      app2BaseUrl,
      localePath: path => localePath(path),
    }),
  )

  const currentPath = computed(() => stripLocalePrefix(route.path, PREFIXED_LOCALES))

  const isActive = (link: ResolvedLink): boolean => link.kind === 'internal' && isActiveLink(link.to, currentPath.value)

  return { items, isActive }
}
