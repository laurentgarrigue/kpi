import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import DefaultLayout from '~/layouts/default.vue'
import SiteHeader from '~/components/site/SiteHeader.vue'
import SiteSearch from '~/components/site/SiteSearch.vue'
import { fakeApi2 } from '../fixtures/api2'
import { expectNotFound, mountRoute } from './results-setup'

// Phase 3 pages (PAGE_CALENDAR.md, PAGE_HISTORY.md, PAGE_TEAM.md, PAGE_CLUBS.md, FEATURE_SEARCH.md), on real api2
// responses captured on SQL/fixtures.
const { api2 } = vi.hoisted(() => ({ api2: vi.fn() }))
mockNuxtImport('useApi2', () => () => api2)
beforeEach(() => {
  api2.mockReset()
  api2.mockImplementation(fakeApi2)
  clearNuxtData()
})

const SUGGESTION_WAIT_MS = 400
const waitForSuggestions = async () => {
  await new Promise(resolve => setTimeout(resolve, SUGGESTION_WAIT_MS))
  await flushPromises()
}

describe('calendar (PAGE_CALENDAR.md)', () => {
  it('CAL-01/CAL-02: the month of the query, gamedays by start date, merged, labelled like the legacy calendar, with the section as colour and text', async () => {
    const wrapper = await mountRoute('/calendar?month=2999-04')
    expect(api2).toHaveBeenCalledWith('/calendar?start=2999-04-01&end=2999-05-05')
    expect(wrapper.find('[data-testid="calendar-month"]').text()).toBe('avril 2999')
    const agenda = wrapper.find('[data-testid="calendar-weeks"]')
    // CAL-12: 2999-04-01 is a Monday; the three gamedays start that week, sorted by section then start.
    const weeks = agenda.findAll('[data-testid="calendar-week-block"]')
    expect(weeks).toHaveLength(1)
    expect(weeks[0]!.find('[data-testid="week-dates"]').text()).toMatch(/^1\s?–\s?2 avr\./)
    expect(weeks[0]!.find('[data-testid="week-kind"]').text()).toBe('Semaine')
    expect(agenda.findAll('[data-gameday]').map(row => row.attributes('data-gameday'))).toEqual(['9202', '9211', '9212'])
    // Dates differing from the block are written; none lasts three days.
    expect(agenda.find('[data-gameday="9211"] [data-testid="entry-dates"]').text()).toBe('1 avr.')
    expect(agenda.find('[data-gameday="9202"] [data-testid="entry-dates"]').exists()).toBe(false)
    expect(agenda.find('[data-testid="entry-length"]').exists()).toBe(false)
    const row = agenda.find('[data-gameday="9202"]')
    // « nom de la journée - lieu (département) » is the label of the item; the competition comes after.
    expect(row.find('[data-testid="calendar-label"]').text()).toBe('RCH J2 - Rivecity (64)')
    expect(row.find('[data-testid="calendar-competition"]').text()).toBe('Championnat Résultats')
    const section = row.find('[data-section]')
    expect(section.attributes('data-section')).toBe('2')
    expect(section.text()).toBe('National')
  })

  it('CAL-03/CAL-11: a gameday leads to its event; in a single-group event, the competition link stays in the event', async () => {
    const row = (await mountRoute('/calendar?month=2999-04')).find('[data-testid="calendar-weeks"] [data-gameday="9202"]')
    expect(row.find('[data-testid="calendar-competition"]').attributes('href')).toBe('/events/77/games?competition=RCH')
    expect(row.find('[data-testid="calendar-label"]').attributes('href')).toBe('/events/77/games?competition=RCH')
    expect(row.find('[data-testid="calendar-event"]').attributes('href')).toBe('/events/77')
  })

  it('CAL-04: month navigation and filters are links and a GET form keeping the filters', async () => {
    const wrapper = await mountRoute('/calendar?month=2999-04&section=2&group=TSTRES')
    expect(api2).toHaveBeenCalledWith('/calendar?start=2999-04-01&end=2999-05-05&section=2&group=TSTRES')
    expect(wrapper.find('[data-testid="previous-month"]').attributes('href')).toBe('/calendar?section=2&group=TSTRES&month=2999-03')
    expect(wrapper.find('[data-testid="next-month"]').attributes('href')).toBe('/calendar?section=2&group=TSTRES&month=2999-05')
    const form = wrapper.find('[data-testid="calendar-filters"]')
    expect(form.attributes('method')).toBe('get')
    expect(form.find('input[name="month"]').attributes('value')).toBe('2999-04')
    expect(form.find('select[name="section"] option[selected]').attributes('value')).toBe('2')
  })

  it('CAL-08: arrows go to the previous / next month and year, keeping the filters', async () => {
    const wrapper = await mountRoute('/calendar?month=2999-04&section=2')
    const nav = wrapper.find('[data-testid="calendar-nav"]')
    expect(nav.find('[data-testid="previous-year"]').attributes('href')).toBe('/calendar?section=2&month=2998-04')
    expect(nav.find('[data-testid="previous-month"]').attributes('href')).toBe('/calendar?section=2&month=2999-03')
    expect(nav.find('[data-testid="next-month"]').attributes('href')).toBe('/calendar?section=2&month=2999-05')
    expect(nav.find('[data-testid="next-year"]').attributes('href')).toBe('/calendar?section=2&month=3000-04')
    expect(nav.find('[data-testid="previous-year"]').text()).toContain('Année précédente')
    expect(nav.find('[data-testid="next-month"]').text()).toContain('Mois suivant')
    expect(nav.find('[data-testid="current-month"]').text()).toBe('Aujourd\'hui')
  })

  it('CAL-09: the section filter replaces the level; the groups offered are those of the chosen section, Divers included', async () => {
    const all = await mountRoute('/calendar?month=2999-04')
    expect(all.findAll('select[name="section"] option').map(option => option.text())).toEqual(
      ['Toutes les sections', 'International', 'National', 'Régional', 'Tournoi', 'Continental', 'Divers'])
    expect(all.findAll('select[name="group"] optgroup').map(group => group.attributes('label'))).toEqual(['International', 'National', 'Divers'])
    const national = await mountRoute('/calendar?month=2999-04&section=2')
    expect(national.findAll('select[name="group"] optgroup').map(group => group.attributes('label'))).toEqual(['National'])
    expect(national.findAll('select[name="group"] option').map(option => option.attributes('value'))).toEqual(['', 'TSTRES'])
  })

  it('CAL-09: a group of another section than the chosen one is not sent to api2', async () => {
    await mountRoute('/calendar?month=2999-04&section=2&group=TSTGRP')
    expect(api2).toHaveBeenCalledWith('/calendar?start=2999-04-01&end=2999-05-05&section=2')
  })

  it('CAL-02: the month grid shows each gameday as one coloured bar spread over all its days', async () => {
    const page = await mountRoute('/calendar?month=2999-04&view=month')
    expect(page.find('[data-testid="calendar-weeks"]').exists()).toBe(false)
    expect(page.findAll('[data-testid="calendar-legend"] [data-section]').map(item => item.text())).toEqual(['National'])
    const grid = page.find('[data-testid="calendar-grid"]')
    // The bar carries the label of the gameday (not the competition title) and the colours of its section.
    expect(grid.find('[data-gameday="9202"]').text()).toContain('RCH J2 - Rivecity (64)')
    expect(grid.find('[data-gameday="9202"]').classes().join(' ')).toContain('bg-kpi-blue-100')
    expect(grid.findAll('[data-testid="calendar-week"]')).toHaveLength(5)
    expect(grid.findAll('[data-date]')).toHaveLength(35)
    // 2999-04-01 is a Monday: the 1–2 April gameday is one two-day bar; the two cup phases take one day each.
    const bars = grid.findAll('[data-testid="calendar-bar"]')
    expect(bars.map(bar => [bar.attributes('data-gameday'), bar.attributes('style')!.match(/grid-column: ([^;]+)/)![1]])).toEqual([
      ['9202', '1 / span 2'], ['9211', '1 / span 1'], ['9212', '2 / span 1'],
    ])
    expect(bars.every(bar => bar.find('a[data-testid="calendar-label"]').exists())).toBe(true)
    // Overlapping bars never share a row; the second day of the cup reuses the free one.
    expect(bars.map(bar => bar.attributes('style')!.match(/grid-row: (\d+)/)![1])).toEqual(['2', '3', '3'])
    expect(grid.find('[data-testid="calendar-bar"]').attributes('data-section')).toBe('2')
  })

  it('CAL-13: the views are links keeping the month and the filters; the month view is kept by the navigation', async () => {
    const wrapper = await mountRoute('/calendar?month=2999-04&section=2')
    const views = wrapper.findAll('[data-testid="calendar-views"] a')
    expect(views.map(view => [view.text(), view.attributes('href'), view.attributes('aria-current')])).toEqual([
      ['Par semaine', '/calendar?section=2&month=2999-04', 'page'],
      ['Mois', '/calendar?section=2&month=2999-04&view=month', undefined],
    ])
    const month = await mountRoute('/calendar?month=2999-04&view=month')
    expect(month.find('[data-testid="next-month"]').attributes('href')).toBe('/calendar?month=2999-05&view=month')
    expect(month.find('[data-testid="calendar-filters"] input[name="view"]').attributes('value')).toBe('month')
  })

  it('CAL-05: an empty month says so and links to the next month having gamedays', async () => {
    const wrapper = await mountRoute('/calendar?month=2999-05&group=TSTRES')
    expect(wrapper.find('[data-testid="calendar-empty"]').text()).toContain('Aucune compétition publiée ce mois-ci.')
    expect(wrapper.find('[data-testid="calendar-next-with-entries"]').attributes('href')).toBe('/calendar?group=TSTRES&month=2999-06')
  })

  it('CAL-06: the info tab of a competition offers the subscription, the .ics file and one file per gameday', async () => {
    const wrapper = await mountRoute('/competitions/2999/RCH/info')
    expect(wrapper.find('[data-testid="ics-subscribe"]').attributes('href')).toBe('webcal://kpi.localhost/api2/competition/2999/RCH/calendar.ics')
    expect(wrapper.find('[data-testid="ics-download"]').attributes('href')).toBe('https://kpi.localhost/api2/competition/2999/RCH/calendar.ics')
    expect(wrapper.find('[data-testid="gameday-ics"]').attributes('href')).toBe('https://kpi.localhost/api2/gameday/9201.ics')
  })

  it('CAL-10: the subscription link can be copied, and a tutorial explains Google, Outlook and Apple calendars', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true })
    const wrapper = await mountRoute('/competitions/2999/RCH/info')
    const link = 'https://kpi.localhost/api2/competition/2999/RCH/calendar.ics'
    expect(wrapper.find('[data-testid="ics-link"]').element).toHaveProperty('value', link)
    await wrapper.find('[data-testid="ics-copy"]').trigger('click')
    await flushPromises()
    expect(writeText).toHaveBeenCalledWith(link)
    expect(wrapper.find('[data-testid="ics-copy"]').text()).toContain('Lien copié')
    const tutorial = wrapper.find('[data-testid="ics-tutorial"]')
    expect(tutorial.find('summary').text()).toBe('Comment s\'abonner ?')
    expect(tutorial.findAll('[data-calendar-app]').map(app => app.attributes('data-calendar-app'))).toEqual(['google', 'outlook', 'apple'])
    for (const app of tutorial.findAll('[data-calendar-app]')) {
      expect(app.findAll('li').length).toBeGreaterThanOrEqual(3)
    }
    expect(tutorial.text()).toContain('Google Agenda')
    expect(tutorial.text()).toContain('Outlook')
    expect(tutorial.text()).toContain('Apple')
  })
})

describe('events page (PAGE_EVENTS.md)', () => {
  it('EVL-01/EVL-02/EVL-04: lists the events of the requested year, the year chosen in a GET form', async () => {
    const wrapper = await mountRoute('/events?year=2999')
    expect(api2).toHaveBeenCalledWith('/events/all')
    expect(wrapper.find('h1').text()).toBe('Événements')
    const form = wrapper.find('[data-testid="events-tools"]')
    expect(form.attributes('method')).toBe('get')
    expect(form.find('select[name="year"] option[selected]').text()).toBe('2999')
    expect(wrapper.find('[data-testid="events-list"] a[href="/events/77"]').exists()).toBe(true)
  })

  it('EVL-03: by month, the upcoming ones first, the next one tagged', async () => {
    const wrapper = await mountRoute('/events?year=2999')
    expect(wrapper.findAll('[data-testid="events-month"] h2').map(title => title.text())).toEqual(['février', 'avril', 'juin'])
    const next = wrapper.find('[data-next]')
    expect(next.find('a').attributes('href')).toBe('/events/42')
    expect(next.text()).toContain('Prochain')
    expect(wrapper.findAll('[data-next]')).toHaveLength(1)
  })

  it('EVL-04: q filters by name or place; nothing found says so', async () => {
    const wrapper = await mountRoute('/events?year=2999&q=rivecity')
    expect(wrapper.findAll('[data-testid="events-list"] li a').map(link => link.attributes('href'))).toEqual(['/events/77', '/events/79'])
    const none = await mountRoute('/events?year=2999&q=nowhere')
    expect(none.find('[data-testid="events-empty"]').exists()).toBe(true)
  })
})

describe('history (PAGE_HISTORY.md § 2 bis)', () => {
  it('HIS-06: the title holder opens the page, with its streak and the 2nd and 3rd', async () => {
    const wrapper = await mountRoute('/history/TSTRES')
    expect(wrapper.find('h1').text()).toBe('Palmarès — Groupe Résultats')
    const holder = wrapper.find('[data-testid="title-holder"]')
    expect(holder.text()).toContain('Champion 2999')
    expect(holder.find('a[data-kind="team"]').text()).toBe('Equipe Echo')
    expect(holder.find('a[data-kind="team"]').attributes('href')).toBe('/teams/111?season=2999&competition=RCP')
    expect(holder.find('[data-testid="holder-titles"]').text()).toBe('1er titre')
    expect(holder.findAll('[data-testid="holder-others"] li').map(item => item.text())).toEqual(['2e Equipe Golf', '3e Equipe Foxtrot'])
  })

  it('HIS-07: the champions of the last seasons, newest first', async () => {
    const strip = (await mountRoute('/history/TSTRES')).findAll('[data-testid="champions-strip"] li')
    expect(strip.map(item => [item.find('[data-testid="strip-season"]').text(), item.find('a').text()])).toEqual([['2999', 'Equipe Echo'], ['2998', 'Equipe Alpha']])
  })

  it('HIS-02 / HIS-03 / HIS-04: every season and finished final competition, with its medals and a link to its ranking', async () => {
    const wrapper = await mountRoute('/history/TSTRES')
    const rows = wrapper.findAll('[data-testid="history-table"] tbody tr')
    expect(rows.map(row => row.attributes('data-competition'))).toEqual(['RCP', 'RMU', 'RCP'])
    expect(rows[0]!.find('[data-testid="history-season"]').text()).toBe('2999')
    expect(rows[0]!.find('[data-testid="history-season"]').attributes('rowspan')).toBe('2')
    expect(rows[0]!.find('[data-testid="history-ranking-link"]').attributes('href')).toBe('/competitions/2999/RCP/ranking')
    expect(rows[0]!.findAll('[data-medal]').map(medal => medal.attributes('data-medal'))).toEqual(['1', '2', '3'])
    expect(rows[0]!.find('[data-medal="1"]').text()).toContain('Médaille d\'or')
    expect(rows[0]!.text()).not.toContain('Equipe Hotel')
  })

  it('HIS-07: touching a team highlights its podiums, and can be undone', async () => {
    const wrapper = await mountRoute('/history/TSTRES')
    await wrapper.find('[data-testid="history-table"] button[data-team="111"]').trigger('click')
    expect(wrapper.findAll('[data-testid="history-table"] [data-highlighted]')).toHaveLength(1)
    expect(wrapper.find('[data-testid="champions-strip"] li[data-highlighted]').text()).toContain('2999')
    await wrapper.find('[data-testid="history-unfollow"]').trigger('click')
    expect(wrapper.findAll('[data-highlighted]')).toHaveLength(0)
  })

  it('HIS-01: the group selector is a GET form to /history; one anchor per decade', async () => {
    const wrapper = await mountRoute('/history/TSTRES')
    expect(wrapper.find('[data-testid="history-selector"]').attributes('action')).toBe('/history')
    expect(wrapper.findAll('[data-testid="decade-anchors"] a').map(link => [link.text(), link.attributes('href')])).toEqual([['Années 2990', '#saison-2999']])
  })

  it('HIS-05: an unknown group or a group without honours is the site 404', async () => {
    expect(await expectNotFound('/history/NOPE')).toBe(404)
  })
})

describe('teams (PAGE_TEAM.md)', () => {
  it('TEA-01: the search works without JavaScript (GET form) and lists the teams found', async () => {
    const wrapper = await mountRoute('/teams?q=alpha')
    expect(wrapper.find('form[role="search"]').attributes('action')).toBe('/teams')
    expect(wrapper.findAll('[data-testid="teams-results"] a').map(link => link.attributes('href'))).toEqual(['/teams/105', '/teams/101'])
    expect((await mountRoute('/teams?q=zzz')).find('[data-testid="teams-no-result"]').exists()).toBe(true)
  })

  it('TEA-01: less than 2 characters sends no request', async () => {
    const wrapper = await mountRoute('/teams?q=a')
    expect(wrapper.find('[data-testid="teams-min-length"]').text()).toBe('Saisissez au moins 2 caractères.')
    expect(api2.mock.calls.map(call => call[0]).filter(path => String(path).startsWith('/teams'))).toEqual([])
  })

  it('TEA-02 / TEA-08: the team page shows its club, its medals and honours with links to the rankings', async () => {
    const wrapper = await mountRoute('/teams/101')
    expect(wrapper.find('h1').text()).toBe('Equipe Alpha')
    expect(wrapper.find('[data-testid="team-club"]').attributes('href')).toBe('/clubs/C001')
    expect(wrapper.find('[data-testid="team-club-line"]').text()).toBe('Équipe du Club Alpha Lacville')
    expect(wrapper.findAll('[data-testid="medal-tally"] [data-tally]').map(item => [item.attributes('data-tally'), item.find('[data-testid="tally-count"]').text()])).toEqual([['1', '1'], ['2', '0'], ['3', '0']])
    const honours = wrapper.findAll('[data-testid="honour"]')
    expect(honours.map(row => row.find('a').attributes('href'))).toEqual(['/competitions/2998/RCP/ranking', '/competitions/2998/RQL/ranking'])
    expect(honours[0]!.find('[data-medal="1"]').exists()).toBe(true)
    expect(honours[1]!.find('[data-medal]').exists()).toBe(false)
  })

  it('TEA-07: medals with the rank written, intermediate rounds muted and named, a season shown once', async () => {
    const wrapper = await mountRoute('/teams/101')
    expect(wrapper.find('#honours-title').text()).toBe('Palmarès depuis 2998')
    const [medalled, intermediate] = wrapper.findAll('[data-testid="honour"]')
    expect(medalled!.find('[data-testid="honour-rank"]').text()).toBe('1er')
    expect(medalled!.find('[data-medal="1"]').text()).toContain('Médaille d\'or')
    expect(intermediate!.attributes('data-final')).toBe('false')
    expect(intermediate!.text()).toContain('phase de qualification')
    expect(intermediate!.find('[data-testid="honour-rank"]').text()).toBe('2e')
    expect(wrapper.findAll('[data-testid="honours"] [data-season]').map(item => item.attributes('data-season'))).toEqual(['2998'])
  })

  it('TEA-06: the club logo and the colours zoom on hover and keyboard focus', async () => {
    api2.mockImplementation(async (path: string) => (path === '/team/101'
      ? { ...(await fakeApi2(path) as object), logo: 'KIP/logo/C001-logo.png', colors: { image: 'KIP/colors/101-colors.png', season: null } }
      : fakeApi2(path)))
    const wrapper = await mountRoute('/teams/101')
    for (const testid of ['team-logo', 'team-colors']) {
      const image = wrapper.find(`[data-testid="${testid}"]`)
      expect(image.attributes('data-zoom')).toBeDefined()
      expect(image.attributes('tabindex')).toBe('0')
      expect(image.classes()).toEqual(expect.arrayContaining(['hover:scale-300', 'focus-visible:scale-300']))
    }
  })

  it('TEA-03: the roster of the most recent competition by default, without personal data', async () => {
    const wrapper = await mountRoute('/teams/101')
    expect(api2).toHaveBeenCalledWith('/team/101/roster/2999/RCH')
    const players = wrapper.findAll('[data-testid="roster-player"]')
    expect(players.map(row => row.find('th').text())).toEqual(['ALPHA Ann', 'ALPHA Bob', 'ALPHA Coach'])
    expect(players[0]!.text()).toContain('Capitaine')
    expect(players[2]!.text()).toContain('Entraîneur')
    expect(wrapper.find('[data-testid="roster"]').text()).not.toMatch(/9501|Matric/)
  })

  it('TEA-03: the roster of the requested competition, chosen with a GET form', async () => {
    const wrapper = await mountRoute('/teams/101?roster=2998|RCP')
    expect(api2).toHaveBeenCalledWith('/team/101/roster/2998/RCP')
    expect(wrapper.find('[data-testid="no-roster"]').exists()).toBe(true)
    expect(wrapper.find('[data-testid="roster-selector"] option[selected]').attributes('value')).toBe('2998|RCP')
  })

  it('TEA-05: an unknown team is the site 404', async () => {
    expect(await expectNotFound('/teams/9999')).toBe(404)
  })
})

describe('clubs (PAGE_CLUBS.md)', () => {
  it('CLB-01/CLB-04: the list shows every club with its department; the search is a GET form', async () => {
    const wrapper = await mountRoute('/clubs')
    expect(wrapper.find('[data-testid="club-search"]').attributes('method')).toBe('get')
    const cards = wrapper.findAll('[data-testid="club-list"] [data-club]')
    expect(cards).toHaveLength(12)
    expect(cards[0]!.attributes('href')).toBe('/clubs/C001')
    expect(cards[0]!.text()).toContain('Comité Départemental Test 33')
    expect((await mountRoute('/clubs?q=lacville')).findAll('[data-club]').map(card => card.attributes('data-club'))).toEqual(['C001'])
  })

  it('CLB-02: the map view loads nothing before the click (no tile, no Leaflet)', async () => {
    const wrapper = await mountRoute('/clubs?view=map')
    expect(wrapper.find('[data-testid="club-list"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="show-map"]').exists()).toBe(true)
    expect(wrapper.html()).not.toContain('tile.openstreetmap.org')
    expect(wrapper.find('.leaflet-container').exists()).toBe(false)
  })

  it('CLB-03: the club page shows committees, website, e-mail as text, address and teams', async () => {
    const wrapper = await mountRoute('/clubs/C001')
    const details = wrapper.find('[data-testid="club-details"]')
    expect(details.text()).toContain('Comité Régional Test')
    expect(details.find('[data-testid="club-website"]').attributes('href')).toBe('https://alpha.example.test')
    expect(details.find('[data-testid="club-website"]').attributes('target')).toBe('_blank')
    expect(details.find('[data-testid="club-email"]').text()).toBe('contact@alpha.example.test')
    expect(details.find('a[href^="mailto:"]').exists()).toBe(false)
    expect(wrapper.findAll('[data-testid="club-teams"] a').map(link => link.attributes('href'))).toEqual(['/teams/105', '/teams/101'])
    expect(wrapper.find('[data-testid="club-map"]').exists()).toBe(true)
  })

  it('CLB-03: an unknown club is the site 404', async () => {
    expect(await expectNotFound('/clubs/NOPE')).toBe(404)
  })
})

describe('global search (FEATURE_SEARCH.md)', () => {
  it('SRC-01: the header magnifier leads to /search without JavaScript; the search block is a GET form to /search', async () => {
    const header = await mountSuspended(SiteHeader)
    expect(header.find('[data-testid="header-search-icon"]').attributes('href')).toBe('/search')
    expect(header.find('form').exists()).toBe(false)
    const search = await mountSuspended(SiteSearch)
    const form = search.find('form')
    expect(form.attributes('action')).toBe('/search')
    expect(form.attributes('method')).toBe('get')
    expect(form.find('input[name="q"]').exists()).toBe(true)
  })

  it('SRC-06: on every screen the magnifier toggles a closable search block between the navigation and main', async () => {
    const layout = await mountSuspended(DefaultLayout, { slots: { default: () => 'page' } })
    const toggle = layout.find('[data-testid="header-search-icon"]')
    const block = () => layout.find('[data-testid="search-block"]')
    expect(toggle.attributes('aria-expanded')).toBe('false')
    expect(block().exists()).toBe(false)

    await toggle.trigger('click')
    expect(toggle.attributes('aria-expanded')).toBe('true')
    expect(block().attributes('id')).toBe(toggle.attributes('aria-controls'))
    expect(block().find('form[action="/search"] input[name="q"]').exists()).toBe(true)
    expect(block().element.previousElementSibling?.tagName).toBe('NAV')
    expect(block().element.nextElementSibling?.tagName).toBe('MAIN')
    expect(layout.text()).toContain('page')

    await block().find('[data-testid="search-block-close"]').trigger('click')
    expect(block().exists()).toBe(false)
    expect(toggle.attributes('aria-expanded')).toBe('false')

    await toggle.trigger('click')
    await toggle.trigger('click')
    expect(block().exists()).toBe(false)
  })

  it('SRC-02: suggestions after 2 characters, grouped, keyboard-accessible, Escape closes', async () => {
    const wrapper = await mountSuspended(SiteSearch)
    const input = wrapper.find('input')
    await input.setValue('a')
    await waitForSuggestions()
    expect(api2).not.toHaveBeenCalled()

    await input.setValue('alpha')
    await waitForSuggestions()
    expect(api2).toHaveBeenCalledWith('/search?q=alpha')
    expect(input.attributes('aria-expanded')).toBe('true')
    const options = wrapper.findAll('[role="option"]')
    expect(options.length).toBeGreaterThan(1)
    await input.trigger('keydown', { key: 'ArrowDown' })
    expect(input.attributes('aria-activedescendant')).toBe(options[0]!.attributes('id'))
    expect(options[0]!.attributes('aria-selected')).toBe('true')
    await wrapper.find('form').trigger('keydown', { key: 'Escape' })
    expect(input.attributes('aria-expanded')).toBe('false')
  })

  it('SRC-03/SRC-04: one section per non-empty category, linking to site pages, no person', async () => {
    const wrapper = await mountRoute('/search?q=resultats')
    expect(wrapper.find('h1').text()).toBe('Résultats pour « resultats »')
    expect(wrapper.findAll('[data-category]').map(section => section.attributes('data-category'))).toEqual(['competitions', 'events', 'news'])
    expect(wrapper.find('[data-category="competitions"] a').attributes('href')).toBe('/competitions/2999/RCH')
    expect(wrapper.find('[data-category="events"] a').attributes('href')).toBe('/events/77')
    // CMS-11: published news (phase 4).
    expect(wrapper.find('[data-category="news"] a').attributes('href')).toBe('/news/finale-resultats')
    expect((await mountRoute('/search?q=alpha')).text()).not.toContain('Ann')
  })

  it('SRC-07: filters by kind with their count; the first club is the best match, shown on the map', async () => {
    const wrapper = await mountRoute('/search?q=alpha')
    const kinds = wrapper.findAll('[data-testid="search-kinds"] a')
    expect(kinds.map(kind => [kind.text().replace(/\s+/g, ' '), kind.attributes('href'), kind.attributes('aria-current')])).toEqual([
      ['Tout 4', '/search?q=alpha', 'page'],
      ['Événements 1', '/search?q=alpha&kind=events', undefined],
      ['Équipes 2', '/search?q=alpha&kind=teams', undefined],
      ['Clubs 1', '/search?q=alpha&kind=clubs', undefined],
    ])
    const best = wrapper.find('[data-testid="search-best"]')
    expect(best.text()).toContain('Club Alpha Lacville')
    expect(best.find('a').attributes('href')).toBe('/clubs?club=C001')
    expect(wrapper.find('[data-category="clubs"]').exists()).toBe(false)
    const teams = await mountRoute('/search?q=alpha&kind=teams')
    expect(teams.findAll('[data-category]').map(section => section.attributes('data-category'))).toEqual(['teams'])
    expect(teams.find('[data-testid="search-best"]').exists()).toBe(false)
  })

  it('SRC-05: too short, no result and too many requests each have their message', async () => {
    expect((await mountRoute('/search?q=a')).find('[data-testid="search-min-length"]').exists()).toBe(true)
    expect((await mountRoute('/search?q=zzz')).find('[data-testid="search-no-result"]').text()).toContain('Aucun résultat pour « zzz »')
    api2.mockImplementation(async () => {
      throw Object.assign(new Error('api2 429'), { statusCode: 429 })
    })
    clearNuxtData()
    expect((await mountRoute('/search?q=alpha')).find('[data-testid="search-too-many"]').exists()).toBe(true)
  })
})
