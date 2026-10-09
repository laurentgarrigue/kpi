import { describe, expect, it } from 'vitest'
import { competitionIcsUrl, gameLink, gamedayIcsUrl, legacyImageUrl, pdfUrl, teamLink, webcalUrl } from '../../app/utils/page-links'

const context = { locale: 'fr', legacyBaseUrl: 'https://www.kayak-polo.info', app2BaseUrl: 'https://app.kayak-polo.info' }

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
  it('CMP-12: ranking PDF by competition type, with S, Compet and lang', () => {
    expect(pdfUrl({ kind: 'ranking', type: 'CHPT', season: '2026', competition: 'N1H' }, context))
      .toBe('https://www.kayak-polo.info/PdfCltChpt.php?S=2026&Compet=N1H&lang=fr')
    expect(pdfUrl({ kind: 'ranking', type: 'CP', season: '2026', competition: 'CF' }, { ...context, locale: 'en' }))
      .toBe('https://www.kayak-polo.info/PdfCltNiveauPhase.php?S=2026&Compet=CF&lang=en')
    expect(pdfUrl({ kind: 'ranking', type: 'MULTI', season: '2026', competition: 'CMU' }, context))
      .toBe('https://www.kayak-polo.info/PdfCltMulti.php?S=2026&Compet=CMU&lang=fr')
  })

  it('AGG-04: game list PDF for competitions or for an event, English file under /en', () => {
    expect(pdfUrl({ kind: 'games', season: '2026', competitions: ['N1H', 'N2H'] }, context))
      .toBe('https://www.kayak-polo.info/PdfListeMatchs.php?S=2026&Compet=N1H,N2H')
    expect(pdfUrl({ kind: 'games', event: 77 }, { ...context, locale: 'en' }))
      .toBe('https://www.kayak-polo.info/PdfListeMatchsEN.php?idEvenement=77')
  })

  it('CMP-12: score sheet of a game', () => {
    expect(pdfUrl({ kind: 'gameSheet', game: 9401 }, context))
      .toBe('https://www.kayak-polo.info/PdfMatchMulti.php?listMatch=9401&lang=fr')
  })
})

describe('calendar files and images', () => {
  it('CAL-06: competition subscription (webcal) and gameday file are served by api2', () => {
    const ics = competitionIcsUrl('https://kayak-polo.info/api2', '2026', 'N1H')
    expect(ics).toBe('https://kayak-polo.info/api2/competition/2026/N1H/calendar.ics')
    expect(webcalUrl(ics)).toBe('webcal://kayak-polo.info/api2/competition/2026/N1H/calendar.ics')
    expect(gamedayIcsUrl('https://kayak-polo.info/api2/', 9201)).toBe('https://kayak-polo.info/api2/gameday/9201.ics')
  })

  it('images of the legacy tree', () => {
    expect(legacyImageUrl('KIP/logo/C001-logo.png', 'https://www.kayak-polo.info')).toBe('https://www.kayak-polo.info/img/KIP/logo/C001-logo.png')
    expect(legacyImageUrl(null, 'https://www.kayak-polo.info')).toBeNull()
  })
})
