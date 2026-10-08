import { describe, expect, it } from 'vitest'
import { gameLink, pdfUrl, teamLink } from '../../app/utils/page-links'

const context = { locale: 'fr', legacyBaseUrl: 'https://www.kayak-polo.info', app2BaseUrl: 'https://app.kayak-polo.info' }

describe('PAGE_LINKS fallbacks', () => {
  it('CMP-12: a team links to the legacy team page until /teams is delivered', () => {
    expect(teamLink({ number: 104, competition: 'N1H' }, context))
      .toEqual({ kind: 'legacy', href: 'https://www.kayak-polo.info/kpequipes.php?Equipe=104&Compet=N1H&lang=fr' })
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
