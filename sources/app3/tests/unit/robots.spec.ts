import { describe, expect, it } from 'vitest'
import { robotsDirective, robotsTxt } from '../../shared/utils/robots'

describe('robotsTxt (PLT-02)', () => {
  it('PLT-02: disallows everything while the site is in beta', () => {
    expect(robotsTxt({ beta: true, siteUrl: 'https://beta.kayak-polo.info' })).toBe('User-agent: *\nDisallow: /\n')
  })

  it('PLT-02: allows crawling and points to the sitemap once live', () => {
    expect(robotsTxt({ beta: false, siteUrl: 'https://www.kayak-polo.info/' })).toBe(
      'User-agent: *\nAllow: /\n\nSitemap: https://www.kayak-polo.info/sitemap.xml\n',
    )
  })
})

describe('robotsDirective (PLT-03)', () => {
  it('PLT-03: is "noindex, nofollow" in beta', () => {
    expect(robotsDirective(true)).toBe('noindex, nofollow')
  })

  it('PLT-03: is absent once live', () => {
    expect(robotsDirective(false)).toBeNull()
  })
})
