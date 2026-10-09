import { joinURL, withQuery } from 'ufo'
import type { CompetitionType } from '#kpi-layer/utils/results/types'

/**
 * Links from results pages to other pages (PAGE_COMPETITION.md § 2.1), with the same fallback mechanism as the
 * menu: a page not delivered by app3 yet links to the legacy site; once delivered, its function returns the app3
 * route (`kind: 'internal'`, path without language prefix).
 */

export interface PageLinkContext {
  locale: string
  legacyBaseUrl: string
  app2BaseUrl: string
}

export interface PageLink {
  kind: 'internal' | 'legacy' | 'app2'
  href: string
}

/** Team page /teams/{number}, opened on the roster of the competition (PAGE_TEAM.md, TEA-04). */
export function teamLink(team: { number: number, competition?: string, season?: string }): PageLink {
  const query = team.competition && team.season ? { season: team.season, competition: team.competition } : {}
  return { kind: 'internal', href: withQuery(`/teams/${team.number}`, query) }
}

/** iCalendar files are served by api2 (PAGE_CALENDAR.md § 2.7, CAL-06). */
export function competitionIcsUrl(api2BaseUrl: string, season: string, competition: string): string {
  return joinURL(api2BaseUrl, 'competition', season, competition, 'calendar.ics')
}

export function gamedayIcsUrl(api2BaseUrl: string, gameday: number): string {
  return joinURL(api2BaseUrl, 'gameday', `${gameday}.ics`)
}

/** Subscription link: the same URL with the `webcal:` scheme, opened by calendar applications. */
export function webcalUrl(url: string): string {
  return url.replace(/^https?:/, 'webcal:')
}

/** Image stored under the legacy `/img/` tree (logos, flags, team colours and photos), or `null`. */
export function legacyImageUrl(path: string | null, legacyBaseUrl: string): string | null {
  return path ? joinURL(legacyBaseUrl, 'img', path) : null
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
