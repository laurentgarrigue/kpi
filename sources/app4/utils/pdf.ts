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
  | 'ranking-phases' | 'ranking-tiebreak' | 'stats' | 'cards'

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
  idJournee: 'gameday',
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
}

/**
 * Document of api2 replacing a legacy admin page, with its query, or null while the page is not migrated. Links
 * still written as legacy URLs open the new document; unknown legacy parameters are dropped.
 */
export function migratedLegacyPdf(page: string, query: LegacyQuery): { document: AdminPdfDocument, query: LegacyQuery } | null {
  const migrated = MIGRATED_LEGACY_PAGES[page]
  if (!migrated) {
    return null
  }
  const parameters: LegacyQuery = {}
  for (const [name, value] of Object.entries(query)) {
    const target = LEGACY_PARAMETERS[name]
    if (target && value !== '' && value !== '%') {
      parameters[target] = value
    }
  }

  return { document: migrated.document, query: { ...parameters, ...migrated.options } }
}

/** Public PDF of api2 (published data, no authentication): plain link, cacheable (§ 6.1). */
export function publicPdfUrl(api2BaseUrl: string, document: string, query: Record<string, string | number>): string {
  const params = new URLSearchParams(Object.entries(query).map(([key, value]) => [key, String(value)]))

  return `${api2BaseUrl}/pdf/${document}?${params.toString()}`
}
