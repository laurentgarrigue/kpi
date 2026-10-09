import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import NavMain from '~/components/nav/NavMain.vue'
import NavGroup from '~/components/nav/NavGroup.vue'
import SiteLanguageSwitcher from '~/components/site/SiteLanguageSwitcher.vue'

const group = {
  id: 'competitions',
  children: [{ id: 'history', kind: 'legacy' as const, href: 'https://kpi.localhost/kphistorique.php?lang=fr' }],
}

describe('NavGroup', () => {
  it('NAV-07: the button toggles aria-expanded and shows the entries; Escape closes it', async () => {
    const wrapper = await mountSuspended(NavGroup, { props: { group, isActive: () => false } })
    const button = wrapper.find('button')
    expect(button.attributes('aria-expanded')).toBe('false')

    await button.trigger('click')
    expect(button.attributes('aria-expanded')).toBe('true')
    expect(wrapper.find('ul').isVisible()).toBe(true)

    await wrapper.trigger('keydown', { key: 'Escape' })
    expect(button.attributes('aria-expanded')).toBe('false')
  })

  it('marks legacy links for assistive technologies', async () => {
    const wrapper = await mountSuspended(NavGroup, { props: { group, isActive: () => false } })
    expect(wrapper.find('a[data-kind="legacy"] .sr-only').text()).toBe('(site actuel)')
  })
})

describe('NavMain', () => {
  it('NAV-08: the mobile toggle opens and closes the panel', async () => {
    const wrapper = await mountSuspended(NavMain)
    const toggle = wrapper.find('[data-testid="mobile-menu-toggle"]')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    await toggle.trigger('click')
    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(wrapper.find('[data-testid="main-nav-mobile"]').isVisible()).toBe(true)
    await toggle.trigger('click')
    expect(toggle.attributes('aria-expanded')).toBe('false')
  })

  it('NAV-10: delivered pages (phase 3: all but news) are internal, the current one marked; the others external', async () => {
    const wrapper = await mountSuspended(NavMain, { route: '/' })
    const desktop = wrapper.find('[data-testid="main-nav-desktop"]')
    const internal = desktop.findAll('a[data-kind="internal"]')
    expect(internal.map(link => link.attributes('href'))).toEqual(['/', '/calendar', '/competitions', '/history', '/teams', '/clubs'])
    expect(internal[0]?.attributes('aria-current')).toBe('page')
    expect(desktop.findAll('a').length).toBeGreaterThan(internal.length)
  })
})

describe('SiteLanguageSwitcher', () => {
  it('NAV-09: links each language to the current page, the current one marked', async () => {
    const wrapper = await mountSuspended(SiteLanguageSwitcher, { route: '/' })
    const links = wrapper.findAll('a')
    expect(links.map(link => link.attributes('href'))).toEqual(['/', '/en'])
    expect(links[0]?.attributes('aria-current')).toBe('true')
  })
})
