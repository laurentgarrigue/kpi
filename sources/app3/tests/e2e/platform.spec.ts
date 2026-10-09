import { createServer } from 'node:http'
import type { AddressInfo } from 'node:net'
import { fileURLToPath } from 'node:url'
import { afterAll, describe, expect, it } from 'vitest'
import { $fetch, fetch, setup } from '@nuxt/test-utils/e2e'
import { api2Fixture } from '../fixtures/api2'

// Fake api2. /events/all is JSON with a wrong Content-Type, as a misconfigured proxy could send it; every other
// request is answered with the real api2 response captured on SQL/fixtures (tests/fixtures/api2).
const EVENTS = [{ id: 7, libelle: 'Coupe de France', place: 'Saint-Omer', logo: null, start: '2026-05-01', end: '2026-05-03' }]
// X-Forwarded-For received by api2 on /search: the server rendering relays the visitor address (API3-08).
const searchForwardedFor: (string | undefined)[] = []
const fakeApi2 = createServer((request, response) => {
  if (request.url?.startsWith('/search')) {
    searchForwardedFor.push(request.headers['x-forwarded-for'] as string | undefined)
  }
  if (request.url === '/events/all') {
    response.writeHead(200, { 'Content-Type': 'application/octet-stream' })
    response.end(JSON.stringify(EVENTS))
    return
  }
  try {
    const fixture = api2Fixture(request.url ?? '')
    response.writeHead(fixture.status, { 'Content-Type': 'application/json' })
    response.end(JSON.stringify(fixture.body))
  } catch {
    response.writeHead(404).end()
  }
})
await new Promise<void>(resolve => fakeApi2.listen(0, '127.0.0.1', resolve))
const api2Url = `http://127.0.0.1:${(fakeApi2.address() as AddressInfo).port}`

// Built and served once for the whole file.
describe('platform and results pages (SITE_PLATFORM.md, phase 2 specs)', async () => {
  await setup({
    rootDir: fileURLToPath(new URL('../..', import.meta.url)),
    server: true,
    env: {
      NUXT_API2_INTERNAL_URL: api2Url,
      NUXT_PUBLIC_API2_BASE_URL: api2Url,
    },
  })

  afterAll(() => {
    fakeApi2.close()
  })

  it('PLT-01: /healthz answers 200 with {"status":"ok"}', async () => {
    const response = await fetch('/healthz')
    expect(response.status).toBe(200)
    expect(await response.json()).toEqual({ status: 'ok' })
  })

  it('PLT-02: /robots.txt disallows everything in beta', async () => {
    const response = await fetch('/robots.txt')
    expect(response.headers.get('content-type')).toContain('text/plain')
    expect(await response.text()).toBe('User-agent: *\nDisallow: /\n')
  })

  it('PLT-03: HTML pages carry the X-Robots-Tag header and the robots meta tag in beta', async () => {
    const response = await fetch('/')
    expect(response.headers.get('x-robots-tag')).toBe('noindex, nofollow')
    expect(await response.text()).toMatch(/<meta name="robots" content="noindex, nofollow">/)
  })

  it('LAY-07: an unknown URL returns a 404 inside the site layout', async () => {
    // A browser navigation asks for HTML (otherwise Nitro answers API-style JSON errors).
    const response = await fetch('/this-page-does-not-exist', { headers: { Accept: 'text/html' } })
    expect(response.status).toBe(404)
    const html = await response.text()
    expect(html).toContain('id="content"')
    expect(html).toContain('Page introuvable')
  })

  it('HOME-03/PLT-04: the home page renders the api2 events server-side, whatever their Content-Type', async () => {
    const response = await fetch('/')
    expect(response.status).toBe(200)
    expect(await response.text()).toContain('Coupe de France')
  })

  it('the client payload of the cached home page is serialisable', async () => {
    const response = await fetch('/_payload.json')
    expect(response.status).toBe(200)
  })

  it('LAY-06: /en is served in English with hreflang alternates and a canonical URL', async () => {
    const html = await $fetch<string>('/en')
    expect(html).toMatch(/<html[^>]*lang="en-GB"/)
    expect(html).toMatch(/<link[^>]*rel="canonical"[^>]*href="https:\/\/beta\.kpi\.localhost\/en"/)
    expect(html).toMatch(/hreflang="fr/)
    expect(html).toMatch(/hreflang="x-default"/)
  })

  // ------------------------------------------------------------- phase 2: redirections, 404, server rendering

  const location = async (path: string) => {
    const response = await fetch(path, { redirect: 'manual', headers: { Accept: 'text/html' } })
    return [response.status, response.headers.get('location')]
  }

  it('CPL-01: /competitions redirects (302) to the active season, keeping the language', async () => {
    expect(await location('/competitions')).toEqual([302, '/competitions/2999'])
    expect(await location('/en/competitions')).toEqual([302, '/en/competitions/2999'])
  })

  it('CPL-08: the selection form without JavaScript lands on the chosen season and group', async () => {
    expect(await location('/competitions?season=2998&group=TSTRES')).toEqual([302, '/competitions/2998?group=TSTRES'])
  })

  it('CMP-01: a competition opens on its ranking when finished, on its games otherwise, keeping ?event=', async () => {
    expect(await location('/competitions/2999/RCP')).toEqual([302, '/competitions/2999/RCP/ranking'])
    expect(await location('/competitions/2999/RCH?event=77')).toEqual([302, '/competitions/2999/RCH/games?event=77'])
  })

  it('EVT-01/GRP-01: event and group views open on their games', async () => {
    expect(await location('/events/77')).toEqual([302, '/events/77/games'])
    expect(await location('/en/groups/2999/TSTRES')).toEqual([302, '/en/groups/2999/TSTRES/games'])
  })

  it('CMP-14/EVT-05/GRP-04: unknown or unpublished results are a 404 page', async () => {
    for (const path of ['/competitions/2999/NOPE/games', '/events/78/games', '/groups/2999/NOPE/games']) {
      const response = await fetch(path, { headers: { Accept: 'text/html' } })
      expect(response.status, path).toBe(404)
      expect(await response.text(), path).toContain('Page introuvable')
    }
  })

  it('CMP-04/CMP-10: results are rendered by the server (usable without JavaScript)', async () => {
    const html = await $fetch<string>('/competitions/2999/RCP/ranking')
    expect(html).toContain('Classement final')
    expect(html).toContain('Equipe Echo')
    expect(html).toMatch(/<a[^>]*aria-current="page"[^>]*>Classement<\/a>/)
  })

  it('CPL-05: the compact ranking and its « see all » work without JavaScript', async () => {
    const html = await $fetch<string>('/competitions/2999?group=TSTRES')
    expect(html).toContain('Equipe Alpha')
    expect(html).toContain('<form')
  })

  // ------------------------------------------------------------- phase 3: calendar, history, teams, clubs, search

  it('HIS-01: /history redirects (302) to the default group, or to the group chosen without JavaScript', async () => {
    expect(await location('/history')).toEqual([302, '/history/TSTRES'])
    expect(await location('/en/history?group=TSTRES')).toEqual([302, '/en/history/TSTRES'])
  })

  it('CAL-07/HIS-02/TEA-02/CLB-01: the phase 3 pages are rendered by the server', async () => {
    expect(await $fetch<string>('/calendar?month=2999-04')).toContain('Championnat Résultats')
    expect(await $fetch<string>('/history/TSTRES')).toContain('Equipe Echo')
    expect(await $fetch<string>('/teams/101')).toContain('ALPHA Ann')
    expect(await $fetch<string>('/clubs')).toContain('Club Alpha Lacville')
  })

  it('HIS-05/TEA-05/CLB-03: unknown history group, team or club is a 404 page', async () => {
    for (const path of ['/history/NOPE', '/teams/9999', '/clubs/NOPE']) {
      const response = await fetch(path, { headers: { Accept: 'text/html' } })
      expect(response.status, path).toBe(404)
    }
  })

  it('SRC-05: the search page is never indexed and relays the visitor address to api2', async () => {
    const response = await fetch('/search?q=resultats', { headers: { 'Accept': 'text/html', 'X-Forwarded-For': '203.0.113.9' } })
    expect(response.status).toBe(200)
    const html = await response.text()
    expect(html).toContain('Résultats pour « resultats »')
    expect(html).toMatch(/<meta name="robots" content="noindex, nofollow">/)
    expect(searchForwardedFor.at(-1)).toContain('203.0.113.9')
  })

  // NuxtLink prefetches `{page}/_payload.json` of cached pages; a route rule that does not cover that path makes
  // every prefetch a 404 (and the cached payload useless).
  it('PLT-07: the payload of every cached page is served (link prefetch does not end in 404)', async () => {
    const pages = [
      '/', '/calendar', '/history/TSTRES', '/teams', '/teams/101', '/clubs', '/clubs/C001',
      '/competitions/2999', '/competitions/2999/RCH/games', '/events/77/games', '/events/77/ranking', '/groups/2999/TSTRES/games',
    ]
    for (const page of pages) {
      const path = `${page === '/' ? '' : page}/_payload.json`
      const response = await fetch(path, { headers: { Accept: '*/*' } })
      expect(response.status, path).toBe(200)
      expect(response.headers.get('content-type'), path).toContain('json')
    }
  })
})
