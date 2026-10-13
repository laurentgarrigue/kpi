import { describe, expect, it } from 'vitest'
import {
  EDITORIAL_MENU_ID,
  MAIN_MENU,
  findMenuLink,
  isActiveLink,
  isMenuGroup,
  resolveMenu,
  resolveMenuLink,
  stripLocalePrefix,
  withEditorialMenu,
  type LinkContext,
  type MenuItem,
} from '../../app/utils/navigation'

const context = (locale: string): LinkContext => ({
  locale,
  legacyBaseUrl: 'https://www.kayak-polo.info',
  app2BaseUrl: 'https://app.kayak-polo.info',
  localePath: path => (locale === 'fr' ? path : `/${locale}${path === '/' ? '' : path}`),
})

describe('MAIN_MENU', () => {
  it('NAV-01: lists the entries in the specified order', () => {
    const ids = MAIN_MENU.map(item =>
      isMenuGroup(item) ? `${item.id}(${item.children.map(child => child.id).join(',')})` : item.id,
    )
    expect(ids).toEqual([
      'home',
      'news',
      'calendar',
      'events',
      'competitions(competitions-list,history)',
      'teams-clubs(teams,clubs)',
      'live',
    ])
  })

  it('NAV-10: only delivered pages are internal links (phase 4: NEWS-08, every entry is delivered)', () => {
    const links = resolveMenu(MAIN_MENU, context('fr')).flatMap(item => (isMenuGroup(item) ? item.children : [item]))
    expect(links.filter(link => link.kind === 'internal').map(link => link.id)).toEqual(['home', 'news', 'calendar', 'events', 'competitions-list', 'history', 'teams', 'clubs'])
    expect(links.filter(link => link.kind === 'legacy').map(link => link.id)).toEqual([])
    expect(links.filter(link => link.kind === 'app2').map(link => link.id)).toEqual(['live'])
  })
})

describe('resolveMenuLink', () => {
  const news = { id: 'news', to: '/news', legacyPath: '/', ready: true }

  it('NAV-02: returns a localised internal link for a ready entry', () => {
    expect(resolveMenuLink(news, context('fr'))).toEqual({ id: 'news', kind: 'internal', href: '/news', to: '/news' })
    expect(resolveMenuLink(news, context('en'))).toEqual({ id: 'news', kind: 'internal', href: '/en/news', to: '/news' })
  })

  it('NAV-03: returns the absolute legacy URL with the language for an entry not delivered yet', () => {
    const calendar = { id: 'calendar', to: '/calendar', legacyPath: '/kpcalendrier.php', ready: false }
    expect(resolveMenuLink(calendar, context('fr'))?.href).toBe('https://www.kayak-polo.info/kpcalendrier.php?lang=fr')
    expect(resolveMenuLink(calendar, context('en'))).toEqual({
      id: 'calendar',
      kind: 'legacy',
      href: 'https://www.kayak-polo.info/kpcalendrier.php?lang=en',
    })
  })

  it('NAV-04: returns the app2 URL for the live entry', () => {
    expect(resolveMenuLink({ id: 'live', app2: true, ready: true }, context('fr'))).toEqual({
      id: 'live',
      kind: 'app2',
      href: 'https://app.kayak-polo.info',
    })
  })

  it('NAV-05: hides an entry without any target', () => {
    expect(resolveMenuLink({ id: 'orphan', to: '/orphan', ready: false }, context('fr'))).toBeNull()
  })
})

describe('resolveMenu', () => {
  it('NAV-05: hides a group whose entries are all hidden', () => {
    const menu: MenuItem[] = [
      { id: 'home', to: '/', ready: true },
      { id: 'empty', children: [{ id: 'orphan', to: '/orphan', ready: false }] },
    ]
    expect(resolveMenu(menu, context('fr')).map(item => item.id)).toEqual(['home'])
  })
})

describe('isActiveLink', () => {
  it('NAV-06: a section is active on its own path and on its sub-paths', () => {
    expect(isActiveLink('/competitions', '/competitions')).toBe(true)
    expect(isActiveLink('/competitions', '/competitions/2026/N1')).toBe(true)
    expect(isActiveLink('/competitions', '/competitions-archive')).toBe(false)
  })

  it('NAV-06: the home page is only active on "/"', () => {
    expect(isActiveLink('/', '/')).toBe(true)
    expect(isActiveLink('/', '/news')).toBe(false)
  })

  it('NAV-06: an entry without internal route is never active', () => {
    expect(isActiveLink(undefined, '/')).toBe(false)
  })
})

describe('stripLocalePrefix', () => {
  it('removes the prefix of a non-default locale', () => {
    expect(stripLocalePrefix('/en/competitions/2026', ['en'])).toBe('/competitions/2026')
    expect(stripLocalePrefix('/en', ['en'])).toBe('/')
  })

  it('leaves default-locale paths and look-alike segments untouched', () => {
    expect(stripLocalePrefix('/competitions', ['en'])).toBe('/competitions')
    expect(stripLocalePrefix('/english-cup', ['en'])).toBe('/english-cup')
  })
})

describe('findMenuLink', () => {
  it('HOME-02: finds an entry inside a group, by id', () => {
    expect(findMenuLink(MAIN_MENU, 'competitions-list')?.to).toBe('/competitions')
  })

  it('returns undefined for an unknown id', () => {
    expect(findMenuLink(MAIN_MENU, 'unknown')).toBeUndefined()
  })
})

describe('withEditorialMenu', () => {
  const resolved = resolveMenu(MAIN_MENU, context('en'))
  const entries = [
    { label: 'The sport', path: '/le-kayak-polo', external: false, children: [{ label: 'Refereeing', path: '/le-kayak-polo/arbitrage', external: false, children: [
      { label: 'Rules', path: '/le-kayak-polo/arbitrage/regles', external: false, children: [] },
    ] }] },
    { label: 'FFCK', path: 'https://www.ffck.org', external: true, children: [] },
  ]

  it('CNT-04: inserts « Le kayak-polo » between teams-clubs and live, each entry followed by its sub-entries (3 levels)', () => {
    const menu = withEditorialMenu(resolved, entries, context('en').localePath)
    expect(menu.map(item => item.id)).toEqual(['home', 'news', 'calendar', 'events', 'competitions', 'teams-clubs', EDITORIAL_MENU_ID, 'live'])
    const group = menu.find(item => item.id === EDITORIAL_MENU_ID)
    expect(group && isMenuGroup(group) ? group.children : []).toEqual([
      { id: 'editorial-0', kind: 'internal', href: '/en/le-kayak-polo', to: '/le-kayak-polo', label: 'The sport', level: 1 },
      { id: 'editorial-1', kind: 'internal', href: '/en/le-kayak-polo/arbitrage', to: '/le-kayak-polo/arbitrage', label: 'Refereeing', level: 2 },
      { id: 'editorial-2', kind: 'internal', href: '/en/le-kayak-polo/arbitrage/regles', to: '/le-kayak-polo/arbitrage/regles', label: 'Rules', level: 3 },
      { id: 'editorial-3', kind: 'external', href: 'https://www.ffck.org', label: 'FFCK', level: 1 },
    ])
  })

  it('CNT-04: no editorial entry → no group', () => {
    expect(withEditorialMenu(resolved, [], context('en').localePath)).toEqual(resolved)
  })
})
