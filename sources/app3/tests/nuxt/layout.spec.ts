import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import DefaultLayout from '~/layouts/default.vue'
import SiteBetaBanner from '~/components/site/SiteBetaBanner.vue'
import SiteFooter from '~/components/site/SiteFooter.vue'
import SiteHeader from '~/components/site/SiteHeader.vue'

describe('default layout (SITE_LAYOUT.md)', () => {
  it('LAY-01: renders skip link, beta banner, header, nav, main#content and footer in order', async () => {
    const wrapper = await mountSuspended(DefaultLayout, { slots: { default: () => 'page' } })
    const landmarks = wrapper.findAll('a[href="#content"], [data-testid="beta-banner"], header, nav[aria-label], main, footer')
      .map(element => element.element.tagName.toLowerCase() + (element.attributes('data-testid') ? '#banner' : ''))
    expect(landmarks.filter(tag => tag !== 'nav')).toEqual(['a', 'div#banner', 'header', 'main', 'footer'])
    expect(wrapper.find('main').attributes('id')).toBe('content')
    expect(wrapper.find('nav[aria-label="Navigation principale"]').exists()).toBe(true)
  })
})

describe('beta banner', () => {
  it('LAY-02: is shown in beta and links to the legacy website', async () => {
    const wrapper = await mountSuspended(SiteBetaBanner)
    expect(wrapper.find('[data-testid="beta-banner"] a').attributes('href')).toBe('https://kpi.localhost')
  })
})

describe('site header', () => {
  it('LAY-03: shows the FFCK logo with an alternative text, linking to the home page', async () => {
    const wrapper = await mountSuspended(SiteHeader)
    const home = wrapper.find('[data-testid="home-link"]')
    expect(home.attributes('href')).toBe('/')
    expect(home.find('img').attributes('alt')).toBeTruthy()
  })
})

describe('site footer', () => {
  it('LAY-04: contains the Facebook, app2, KIP Sport, legacy and administration links and the year', async () => {
    const wrapper = await mountSuspended(SiteFooter)
    const hrefs = wrapper.findAll('a').map(link => link.attributes('href'))
    expect(hrefs).toEqual(expect.arrayContaining([
      'https://www.facebook.com/ffckkp/',
      'https://app.kpi.localhost',
      'https://www.facebook.com/KIPsport',
      'https://kpi.localhost',
      'https://kpi.localhost/AdminChoice.php',
    ]))
    expect(wrapper.find('[data-testid="copyright"]').text()).toContain(String(new Date().getFullYear()))
  })
})
