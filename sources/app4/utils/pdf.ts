/**
 * PDF documents (DOC/specs/PDF_GOTENBERG.md). Pure helpers, no Nuxt import: unit-tested with Vitest.
 *
 * The rights are checked by api2 (§ 6.4); these helpers only decide which links app4 shows (PDF-FRONT-04) and
 * build the URLs. Keep ADMIN_PDF_MAX_PROFILE in step with sources/api2/src/Pdf/PdfCatalog.php.
 */
import { isScrutineerProfile } from './scrutineering'

export type AdminPdfDocument =
  | 'teams-groups' | 'presence' | 'scrutineering-control' | 'referees-pool' | 'instances'
  | 'games-list' | 'scoresheet' | 'ranking' | 'ranking-team-detail' | 'ranking-gameday-detail'
  | 'ranking-phases' | 'ranking-tiebreak' | 'stats' | 'cards' | 'schema'

export type PdfOption = 'visa' | 'birthdate' | 'u21'

/** Highest profile allowed for each admin document (§ 7: ROLE_ADMIN 2, ROLE_ORGANIZER 6, ROLE_VIEWER 8, ROLE_SCORER 9). */
const ADMIN_PDF_MAX_PROFILE: Record<AdminPdfDocument, number> = {
  'teams-groups': 8,
  'presence': 8,
  'scrutineering-control': 2,
  'referees-pool': 2,
  'instances': 8,
  'games-list': 9,
  'scoresheet': 9,
  'ranking': 6,
  'ranking-team-detail': 6,
  'ranking-gameday-detail': 6,
  'ranking-phases': 6,
  'ranking-tiebreak': 6,
  'stats': 8,
  'cards': 6,
  'schema': 8,
}

/** Options adding data to the presence sheet, with their own highest profile (§ 7.2, I2). */
const OPTION_MAX_PROFILE: Record<PdfOption, number> = { visa: 6, u21: 6, birthdate: 2 }

/** Documents the profile 10 gets on top of the linear hierarchy (presence and control of every team, I1). */
const SCRUTINEER_DOCUMENTS: readonly AdminPdfDocument[] = ['presence', 'scrutineering-control']

export function canGetAdminPdf(profile: number, document: AdminPdfDocument, option?: PdfOption): boolean {
  if (option && profile > OPTION_MAX_PROFILE[option]) {
    return false
  }
  if (isScrutineerProfile(profile)) {
    return SCRUTINEER_DOCUMENTS.includes(document) && !option
  }

  return profile <= ADMIN_PDF_MAX_PROFILE[document]
}

const LEGACY_ADMIN_PDF = /\/admin\/(Feuille[A-Za-z0-9]+\.php)$/
/** Admin PDF pages only: the online scoresheets FeuilleMarque* are HTML pages, not PDFs. */
const LEGACY_HTML_PAGE = /^FeuilleMarque/

/**
 * Legacy admin PDF page targeted by a URL (phase 0: it needs a signed link), or null.
 *
 * @param href absolute URL of a link
 */
export function legacyAdminPdf(href: string, legacyBaseUrl: string): { page: string, query: Record<string, string> } | null {
  let url: URL
  try {
    url = new URL(href)
  }
  catch {
    return null
  }
  const base = new URL(legacyBaseUrl)
  const match = LEGACY_ADMIN_PDF.exec(url.pathname)
  if (url.origin !== base.origin || !match || LEGACY_HTML_PAGE.test(match[1]!)) {
    return null
  }

  return { page: match[1]!, query: Object.fromEntries(url.searchParams.entries()) }
}

type LegacyQuery = Record<string, string>

/** Names of the parameters across the legacy pages, mapped to the parameters of api2 (§ 6.1). */
const LEGACY_PARAMETERS: Record<string, string> = {
  season: 'season', S: 'season',
  compet: 'competition', Compet: 'competition', codeCompet: 'competition',
  team: 'team', equipe: 'team',
  idJournee: 'gameday', idSelJournee: 'gameday',
  idEvenement: 'event', Evt: 'event',
  listMatch: 'games',
  filtreJour: 'date', filtreTerrain: 'pitch', filtreTour: 'round',
  lang: 'lang', tz: 'tz',
}

/** Legacy admin pages already replaced by a document of api2: document and options (§ 7). */
const MIGRATED_LEGACY_PAGES: Record<string, { document: AdminPdfDocument, options: LegacyQuery }> = {
  'FeuilleGroups.php': { document: 'teams-groups', options: {} },
  'FeuillePresence.php': { document: 'presence', options: {} },
  'FeuillePresenceEN.php': { document: 'presence', options: { lang: 'en' } },
  'FeuillePresencePhoto.php': { document: 'presence', options: { view: 'photos' } },
  'FeuillePresencePhoto2.php': { document: 'presence', options: { view: 'photos' } },
  'FeuillePresencePhotoRef.php': { document: 'presence', options: { view: 'photos' } },
  'FeuillePresenceCat.php': { document: 'presence', options: { view: 'category' } },
  'FeuillePresenceU21.php': { document: 'presence', options: { view: 'category', category: 'U21' } },
  'FeuillePresenceNaissance.php': { document: 'presence', options: { view: 'birthyear' } },
  'FeuillePresenceVisa.php': { document: 'presence', options: { visa: '1' } },
  'FeuilleControle.php': { document: 'scrutineering-control', options: {} },
  'FeuilleControleEN.php': { document: 'scrutineering-control', options: { lang: 'en' } },
  'FeuilleInstances.php': { document: 'instances', options: {} },
  'FeuilleCltChpt.php': { document: 'ranking', options: {} },
  'FeuilleCltNiveau.php': { document: 'ranking', options: {} },
  'FeuilleCltMulti.php': { document: 'ranking', options: {} },
  'FeuilleCltChptDetail.php': { document: 'ranking-team-detail', options: {} },
  'FeuilleCltNiveauDetail.php': { document: 'ranking-team-detail', options: {} },
  'FeuilleCltNiveauJournee.php': { document: 'ranking-gameday-detail', options: {} },
  'FeuilleCltNiveauPhase.php': { document: 'ranking-phases', options: {} },
  'FeuilleListeMatchs.php': { document: 'games-list', options: {} },
  'FeuilleListeMatchsEN.php': { document: 'games-list', options: { lang: 'en' } },
  'FeuilleMatchMulti.php': { document: 'scoresheet', options: {} },
  'FeuilleCards.php': { document: 'cards', options: {} },
}

/** Public legacy pages (PdfXxx.php at the root) already replaced by a public document of api2. */
const MIGRATED_PUBLIC_PAGES: Record<string, { document: string, options: LegacyQuery }> = {
  'PdfCltChpt.php': { document: 'ranking', options: {} },
  'PdfCltNiveau.php': { document: 'ranking', options: {} },
  'PdfCltMulti.php': { document: 'ranking', options: {} },
  'PdfCltChptDetail.php': { document: 'ranking-team-detail', options: {} },
  'PdfCltNiveauDetail.php': { document: 'ranking-team-detail', options: {} },
  'PdfCltNiveauJournee.php': { document: 'ranking-gameday-detail', options: {} },
  'PdfCltNiveauPhase.php': { document: 'ranking-phases', options: {} },
  'PdfListeMatchs.php': { document: 'games-list', options: {} },
  'PdfListeMatchsEN.php': { document: 'games-list', options: { lang: 'en' } },
  'PdfListeMatchs4TerrainsEn.php': { document: 'games-grid', options: { view: 'teams' } },
  'PdfListeMatchs4TerrainsEn3.php': { document: 'games-grid', options: { view: 'teams' } },
  'PdfListeMatchs5TerrainsEnTeams.php': { document: 'games-grid', options: { view: 'teams' } },
  'PdfListeMatchs4Terrains.php': { document: 'games-grid', options: { view: 'phases' } },
  'PdfListeMatchs4TerrainsEn2.php': { document: 'games-grid', options: { view: 'phases' } },
  'PdfListeMatchs4TerrainsEn4.php': { document: 'games-grid', options: { view: 'phases' } },
  'PdfListeMatchs5TerrainsEn.php': { document: 'games-grid', options: { view: 'phases' } },
  'PdfQrCodes.php': { document: 'qrcodes', options: {} },
  'PdfQrCodeApp.php': { document: 'qrcode-app', options: {} },
}

const LEGACY_PUBLIC_PDF = /^\/(Pdf[A-Za-z0-9]+\.php)$/

function apiQuery(query: LegacyQuery, options: LegacyQuery): LegacyQuery {
  const parameters: LegacyQuery = {}
  for (const [name, value] of Object.entries(query)) {
    const target = LEGACY_PARAMETERS[name]
    if (target && value !== '' && value !== '%') {
      parameters[target] = value
    }
  }

  return { ...parameters, ...options }
}

/**
 * Document of api2 replacing a legacy admin page, with its query, or null while the page is not migrated. Links
 * still written as legacy URLs open the new document; unknown legacy parameters are dropped.
 */
export function migratedLegacyPdf(page: string, query: LegacyQuery): { document: AdminPdfDocument, query: LegacyQuery } | null {
  const migrated = MIGRATED_LEGACY_PAGES[page]

  return migrated ? { document: migrated.document, query: apiQuery(query, migrated.options) } : null
}

/**
 * URL of a public PDF: the public document of api2 replacing a legacy `PdfXxx.php` page once migrated, else the
 * legacy URL unchanged.
 *
 * @param href absolute URL of the legacy public PDF
 * @param lang language of the user, when the legacy URL does not give one
 */
export function publicPdfHref(href: string, legacyBaseUrl: string, api2BaseUrl: string, lang?: 'fr' | 'en'): string {
  let url: URL
  try {
    url = new URL(href)
  }
  catch {
    return href
  }
  const base = new URL(legacyBaseUrl)
  const prefix = base.pathname.replace(/\/$/, '')
  const page = url.origin === base.origin && url.pathname.startsWith(prefix)
    ? LEGACY_PUBLIC_PDF.exec(url.pathname.slice(prefix.length))?.[1]
    : undefined
  const migrated = page ? MIGRATED_PUBLIC_PAGES[page] : undefined
  if (!migrated) {
    return href
  }

  const query = apiQuery(Object.fromEntries(url.searchParams.entries()), migrated.options)

  return publicPdfUrl(api2BaseUrl, migrated.document, lang && !query.lang ? { ...query, lang } : query)
}

/** Public PDF of api2 (published data, no authentication): plain link, cacheable (§ 6.1). */
export function publicPdfUrl(api2BaseUrl: string, document: string, query: Record<string, string | number>): string {
  const params = new URLSearchParams(Object.entries(query).map(([key, value]) => [key, String(value)]))

  return `${api2BaseUrl}/pdf/${document}?${params.toString()}`
}
