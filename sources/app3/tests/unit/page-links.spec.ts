import { describe, expect, it } from 'vitest'
import { competitionIcsUrl, eventIcsUrl, gameLink, gamedayIcsUrl, legacyImageUrl, pdfUrl, teamLink, webcalUrl } from '../../app/utils/page-links'

const context = { locale: 'fr', legacyBaseUrl: 'https://www.kayak-polo.info', app2BaseUrl: 'https://app.kayak-polo.info', api2BaseUrl: 'https://www.kayak-polo.info/api2' }

describe('PAGE_LINKS fallbacks', () => {
  it('TEA-04: a team links to its app3 page, on the roster of the competition when known', () => {
    expect(teamLink({ number: 104, competition: 'N1H', season: '2026' })).toEqual({ kind: 'internal', href: '/teams/104?season=2026&competition=N1H' })
    expect(teamLink({ number: 104 })).toEqual({ kind: 'internal', href: '/teams/104' })
  })

  it('CMP-12: a game links to its app2 sheet', () => {
    expect(gameLink(9401, context)).toEqual({ kind: 'app2', href: 'https://app.kayak-polo.info/game/9401' })
  })
})

describe('pdfUrl (D-P2-2)', () => {
  it('CMP-12 / PDF-FRONT-02: general ranking PDF from the public route of api2 (PDF_GOTENBERG.md § 7.4)', () => {
    expect(pdfUrl({ kind: 'ranking', season: '2026', competition: 'N1H' }, context))
      .toBe('https://www.kayak-polo.info/api2/pdf/ranking?season=2026&competition=N1H&lang=fr')
    expect(pdfUrl({ kind: 'ranking', season: '2026', competition: 'CMU' }, { ...context, locale: 'en' }))
      .toBe('https://www.kayak-polo.info/api2/pdf/ranking?season=2026&competition=CMU&lang=en')
  })

  it('CMP-12: the PDF of a cup is its final ranking too, from api2', () => {
    expect(pdfUrl({ kind: 'ranking', season: '2026', competition: 'CF' }, { ...context, locale: 'en' }))
      .toBe('https://www.kayak-polo.info/api2/pdf/ranking?season=2026&competition=CF&lang=en')
  })

  it('AGG-04: game list PDF of api2 for competitions or for an event, in the language of the page', () => {
    expect(pdfUrl({ kind: 'games', season: '2026', competitions: ['N1H', 'N2H'] }, context))
      .toBe('https://www.kayak-polo.info/api2/pdf/games-list?season=2026&competition=N1H,N2H&lang=fr')
    expect(pdfUrl({ kind: 'games', season: '2026', event: 77 }, { ...context, locale: 'en' }))
      .toBe('https://www.kayak-polo.info/api2/pdf/games-list?season=2026&event=77&lang=en')
  })
})

describe('calendar files and images', () => {
  it('CAL-06: competition subscription (webcal) and gameday file are served by api2', () => {
    const ics = competitionIcsUrl('https://kayak-polo.info/api2', '2026', 'N1H')
    expect(ics).toBe('https://kayak-polo.info/api2/competition/2026/N1H/calendar.ics')
    expect(webcalUrl(ics)).toBe('webcal://kayak-polo.info/api2/competition/2026/N1H/calendar.ics')
    expect(gamedayIcsUrl('https://kayak-polo.info/api2/', 9201)).toBe('https://kayak-polo.info/api2/gameday/9201.ics')
  })

  it('EVT-10: the subscription of an event is served by api2', () => {
    expect(eventIcsUrl('https://kayak-polo.info/api2', 77)).toBe('https://kayak-polo.info/api2/event/77/calendar.ics')
  })

  it('images of the legacy tree', () => {
    expect(legacyImageUrl('KIP/logo/C001-logo.png', 'https://www.kayak-polo.info')).toBe('https://www.kayak-polo.info/img/KIP/logo/C001-logo.png')
    expect(legacyImageUrl(null, 'https://www.kayak-polo.info')).toBeNull()
  })
})
