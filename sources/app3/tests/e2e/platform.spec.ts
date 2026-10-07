import { createServer } from 'node:http'
import type { AddressInfo } from 'node:net'
import { fileURLToPath } from 'node:url'
import { afterAll, describe, expect, it } from 'vitest'
import { $fetch, fetch, setup } from '@nuxt/test-utils/e2e'

// Fake api2: serves /events/all as JSON but with a wrong Content-Type, as a misconfigured proxy could.
const EVENTS = [{ id: 7, libelle: 'Coupe de France', place: 'Saint-Omer', logo: null, year: 2026 }]
const fakeApi2 = createServer((request, response) => {
  if (request.url === '/events/all') {
    response.writeHead(200, { 'Content-Type': 'application/octet-stream' })
    response.end(JSON.stringify(EVENTS))
    return
  }
  response.writeHead(404).end()
})
await new Promise<void>(resolve => fakeApi2.listen(0, '127.0.0.1', resolve))
const api2Url = `http://127.0.0.1:${(fakeApi2.address() as AddressInfo).port}`

// Built and served once for the whole file.
describe('platform (SITE_PLATFORM.md)', async () => {
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
})
