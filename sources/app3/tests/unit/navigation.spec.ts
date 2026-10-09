import { describe, expect, it } from 'vitest'
import {
  MAIN_MENU,
  findMenuLink,
  isActiveLink,
  isMenuGroup,
  resolveMenu,
  resolveMenuLink,
  stripLocalePrefix,
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
      'competitions(competitions-list,history)',
      'teams-clubs(teams,clubs)',
      'live',
    ])
  })

  it('NAV-10: only delivered pages are internal links (phase 3: CAL-07, HIS-05, TEA-05, CLB-05)', () => {
    const links = resolveMenu(MAIN_MENU, context('fr')).flatMap(item => (isMenuGroup(item) ? item.children : [item]))
    expect(links.filter(link => link.kind === 'internal').map(link => link.id)).toEqual(['home', 'calendar', 'competitions-list', 'history', 'teams', 'clubs'])
    expect(links.filter(link => link.kind === 'legacy').map(link => link.id)).toEqual(['news'])
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
