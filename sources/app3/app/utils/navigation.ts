/**
 * Main navigation: configuration and pure resolution rules (DOC/specs/public/SITE_NAVIGATION.md).
 * Delivering a page = setting `ready: true` on its entry; components never hard-code entries.
 */

export interface MenuLink {
  /** Stable identifier, also the i18n key suffix: `nav.<id>`. */
  id: string
  /** app3 route, without language prefix. */
  to?: string
  /** Equivalent page of the current (legacy) website, used until `ready`. */
  legacyPath?: string
  /** Link to the event-following application (app2). */
  app2?: true
  /** The app3 page is delivered. */
  ready: boolean
}

export interface MenuGroup {
  id: string
  children: MenuLink[]
}

export type MenuItem = MenuLink | MenuGroup

export const MAIN_MENU: readonly MenuItem[] = [
  { id: 'home', to: '/', ready: true },
  { id: 'news', to: '/news', legacyPath: '/', ready: false },
  { id: 'calendar', to: '/calendar', legacyPath: '/kpcalendrier.php', ready: true },
  { id: 'events', to: '/events', ready: true },
  {
    id: 'competitions',
    children: [
      { id: 'competitions-list', to: '/competitions', legacyPath: '/kpclassements.php', ready: true },
      { id: 'history', to: '/history', legacyPath: '/kphistorique.php', ready: true },
    ],
  },
  {
    id: 'teams-clubs',
    children: [
      { id: 'teams', to: '/teams', legacyPath: '/kpequipes.php', ready: true },
      { id: 'clubs', to: '/clubs', legacyPath: '/kpclubs.php', ready: true },
    ],
  },
  { id: 'live', app2: true, ready: true },
]

export interface LinkContext {
  locale: string
  legacyBaseUrl: string
  app2BaseUrl: string
  /** Localises an app3 route (`/news` → `/en/news`), i.e. Nuxt i18n `localePath`. */
  localePath: (path: string) => string
}

export type ResolvedLink =
  | { id: string, kind: 'internal', href: string, to: string }
  | { id: string, kind: 'legacy' | 'app2', href: string }

export interface ResolvedGroup {
  id: string
  children: ResolvedLink[]
}

export type ResolvedMenuItem = ResolvedLink | ResolvedGroup

/** Narrows a configured or resolved menu item to a group. */
export function isMenuGroup<T extends object>(item: T): item is Extract<T, { children: unknown }> {
  return 'children' in item
}

/** A menu link by id, at any level of the menu. */
export function findMenuLink(menu: readonly MenuItem[], id: string): MenuLink | undefined {
  return menu.flatMap(item => (isMenuGroup(item) ? item.children : [item])).find(link => link.id === id)
}

/** Target of a menu entry, or `null` when it has none (the entry is then hidden). */
export function resolveMenuLink(link: MenuLink, context: LinkContext): ResolvedLink | null {
  if (link.app2) {
    return { id: link.id, kind: 'app2', href: context.app2BaseUrl }
  }
  if (link.ready && link.to) {
    return { id: link.id, kind: 'internal', href: context.localePath(link.to), to: link.to }
  }
  if (link.legacyPath) {
    return { id: link.id, kind: 'legacy', href: `${context.legacyBaseUrl}${link.legacyPath}?lang=${context.locale}` }
  }
  return null
}

/** Resolves every entry, dropping hidden entries and groups left empty. */
export function resolveMenu(menu: readonly MenuItem[], context: LinkContext): ResolvedMenuItem[] {
  return menu.flatMap((item): ResolvedMenuItem[] => {
    if (isMenuGroup(item)) {
      const children = item.children
        .map(child => resolveMenuLink(child, context))
        .filter((child): child is ResolvedLink => child !== null)
      return children.length > 0 ? [{ id: item.id, children }] : []
    }
    const link = resolveMenuLink(item, context)
    return link ? [link] : []
  })
}

/** An internal route is active on its own path and its sub-paths; `/` only on itself. */
export function isActiveLink(to: string | undefined, currentPath: string): boolean {
  if (!to) {
    return false
  }
  if (to === '/') {
    return currentPath === '/'
  }
  return currentPath === to || currentPath.startsWith(`${to}/`)
}

/** Removes the language prefix of a non-default locale (`/en/news` → `/news`). */
export function stripLocalePrefix(path: string, prefixedLocales: readonly string[]): string {
  for (const locale of prefixedLocales) {
    if (path === `/${locale}`) {
      return '/'
    }
    if (path.startsWith(`/${locale}/`)) {
      return path.slice(locale.length + 1)
    }
  }
  return path
}
