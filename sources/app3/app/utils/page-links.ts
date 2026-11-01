import { joinURL, withQuery } from 'ufo'

/**
 * Links from results pages to other pages (PAGE_COMPETITION.md § 2.1), with the same fallback mechanism as the
 * menu: a page not delivered by app3 yet links to the legacy site; once delivered, its function returns the app3
 * route (`kind: 'internal'`, path without language prefix).
 */

export interface PageLinkContext {
  locale: string
  legacyBaseUrl: string
  app2BaseUrl: string
  api2BaseUrl: string
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

/** Subscription of a single-group event (PAGE_EVENT_GROUP.md § 2.0, EVT-10). */
export function eventIcsUrl(api2BaseUrl: string, event: number): string {
  return joinURL(api2BaseUrl, 'event', String(event), 'calendar.ics')
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

/** Placeholder logo of a team without one: shown as nothing. */
const EMPTY_TEAM_LOGO = /(^|\/)empty-logo\.\w+$/

/** Logo of a team (club logo), or `null` when it has none (CMP-19). */
export function teamLogoUrl(path: string | null, legacyBaseUrl: string): string | null {
  return path && !EMPTY_TEAM_LOGO.test(path) ? legacyImageUrl(path, legacyBaseUrl) : null
}

/** Game sheet: stays in app2. */
export function gameLink(gameId: number, context: PageLinkContext): PageLink {
  return { kind: 'app2', href: joinURL(context.app2BaseUrl, 'game', String(gameId)) }
}

/** Every type, a cup included (its final ranking only), links to the ranking PDF (PDF_GOTENBERG.md § 7.4). */
const RANKING_PDF = 'ranking'
const GAMES_PDF = 'games-list'

export type PdfTarget =
  | { kind: 'ranking', season: string, competition: string }
  | { kind: 'games', season: string, competitions: string[] }
  | { kind: 'games', season: string, event: number }

/**
 * Public PDF of api2 (PDF_GOTENBERG.md § 7), always called with explicit GET parameters (API_PUBLIC_RESULTS.md
 * D-P2-2). There is no public scoresheet PDF: a game links to its sheet in app2 (PDF_GOTENBERG.md § 7.3.2).
 */
export function pdfUrl(target: PdfTarget, context: PageLinkContext): string {
  const api2Pdf = (document: string, query: Record<string, string | number>) =>
    // Commas are kept readable: `competition` lists several codes.
    withQuery(joinURL(context.api2BaseUrl, 'pdf', document), { ...query, lang: context.locale }).replaceAll('%2C', ',')

  switch (target.kind) {
    case 'ranking':
      return api2Pdf(RANKING_PDF, { season: target.season, competition: target.competition })
    case 'games':
      return 'event' in target
        ? api2Pdf(GAMES_PDF, { season: target.season, event: target.event })
        : api2Pdf(GAMES_PDF, { season: target.season, competition: target.competitions.join(',') })
  }
}
