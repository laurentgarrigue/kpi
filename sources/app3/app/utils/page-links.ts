import { joinURL, withQuery } from 'ufo'
import type { CompetitionType } from '#kpi-layer/utils/results/types'

/**
 * Links from results pages to pages not delivered by app3 yet (PAGE_COMPETITION.md § 2.1), with the same
 * fallback mechanism as the menu: when a target page is delivered, its function returns the app3 route.
 */

export interface PageLinkContext {
  locale: string
  legacyBaseUrl: string
  app2BaseUrl: string
}

export interface PageLink {
  kind: 'legacy' | 'app2'
  href: string
}

/** Team page: legacy kpequipes.php until /teams/{id} (phase 3). */
export function teamLink(team: { number: number | null, competition: string }, context: PageLinkContext): PageLink {
  return {
    kind: 'legacy',
    href: withQuery(joinURL(context.legacyBaseUrl, 'kpequipes.php'), { Equipe: team.number ?? '', Compet: team.competition, lang: context.locale }),
  }
}

/** Game sheet: stays in app2. */
export function gameLink(gameId: number, context: PageLinkContext): PageLink {
  return { kind: 'app2', href: joinURL(context.app2BaseUrl, 'game', String(gameId)) }
}

const RANKING_PDF: Record<CompetitionType, string> = {
  CHPT: 'PdfCltChpt.php',
  CP: 'PdfCltNiveauPhase.php',
  MULTI: 'PdfCltMulti.php',
}

export type PdfTarget =
  | { kind: 'ranking', type: CompetitionType, season: string, competition: string }
  | { kind: 'games', season: string, competitions: string[] }
  | { kind: 'games', event: number }
  | { kind: 'gameSheet', game: number }

/** Legacy public PDF, always called with explicit GET parameters (API_PUBLIC_RESULTS.md D-P2-2). */
export function pdfUrl(target: PdfTarget, context: PageLinkContext): string {
  const url = (file: string, query: Record<string, string | number>) =>
    // Commas are kept readable: the legacy PDF splits `Compet` on them.
    withQuery(joinURL(context.legacyBaseUrl, file), query).replaceAll('%2C', ',')

  switch (target.kind) {
    case 'ranking':
      return url(RANKING_PDF[target.type], { S: target.season, Compet: target.competition, lang: context.locale })
    case 'gameSheet':
      return url('PdfMatchMulti.php', { listMatch: target.game, lang: context.locale })
    case 'games': {
      const file = context.locale === 'en' ? 'PdfListeMatchsEN.php' : 'PdfListeMatchs.php'
      return 'event' in target
        ? url(file, { idEvenement: target.event })
        : url(file, { S: target.season, Compet: target.competitions.join(',') })
    }
  }
}
