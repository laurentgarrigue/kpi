import { describe, expect, it } from 'vitest'
import { canGetAdminPdf, legacyAdminPdf, migratedLegacyPdf, publicPdfUrl } from '../../utils/pdf'

const LEGACY = 'https://kpi.localhost'

describe('canGetAdminPdf (PDF_GOTENBERG.md § 7, PDF-FRONT-04)', () => {
  it('follows the minimum role of each document', () => {
    expect(canGetAdminPdf(8, 'teams-groups')).toBe(true)
    expect(canGetAdminPdf(9, 'teams-groups')).toBe(false)
    expect(canGetAdminPdf(9, 'scoresheet')).toBe(true) // I4: the scorer prints the scoresheets
    expect(canGetAdminPdf(6, 'ranking')).toBe(true)
    expect(canGetAdminPdf(7, 'ranking')).toBe(false) // I5: computed ranking for profiles 1–6
    expect(canGetAdminPdf(3, 'referees-pool')).toBe(false)
  })

  it('gives the profile 10 the presence and control sheets only (I1)', () => {
    expect(canGetAdminPdf(10, 'presence')).toBe(true)
    expect(canGetAdminPdf(10, 'scrutineering-control')).toBe(true)
    expect(canGetAdminPdf(10, 'teams-groups')).toBe(false)
    expect(canGetAdminPdf(10, 'presence', 'visa')).toBe(false)
    expect(canGetAdminPdf(3, 'scrutineering-control')).toBe(false)
  })

  it('checks the options adding data (I2)', () => {
    expect(canGetAdminPdf(6, 'presence', 'visa')).toBe(true)
    expect(canGetAdminPdf(7, 'presence', 'visa')).toBe(false)
    expect(canGetAdminPdf(3, 'presence', 'birthdate')).toBe(false)
    expect(canGetAdminPdf(2, 'presence', 'birthdate')).toBe(true)
  })
})

describe('legacyAdminPdf (phase 0)', () => {
  it('recognises an admin PDF page with its query', () => {
    expect(legacyAdminPdf(`${LEGACY}/admin/FeuillePresence.php?team=12&compet=N1H`, LEGACY))
      .toEqual({ page: 'FeuillePresence.php', query: { team: '12', compet: 'N1H' } })
  })

  it('ignores public PDFs, online scoresheets and other sites', () => {
    expect(legacyAdminPdf(`${LEGACY}/PdfCltChpt.php?S=2026`, LEGACY)).toBeNull()
    expect(legacyAdminPdf(`${LEGACY}/admin/FeuilleMarque2.php?idMatch=1`, LEGACY)).toBeNull()
    expect(legacyAdminPdf('https://evil.example/admin/FeuillePresence.php', LEGACY)).toBeNull()
    expect(legacyAdminPdf('not a url', LEGACY)).toBeNull()
  })
})

describe('publicPdfUrl', () => {
  it('builds the api2 public route', () => {
    expect(publicPdfUrl('https://kpi.localhost/api2', 'ranking', { season: 2026, competition: 'N1H', lang: 'en' }))
      .toBe('https://kpi.localhost/api2/pdf/ranking?season=2026&competition=N1H&lang=en')
  })
})

describe('migratedLegacyPdf (legacy links opening the documents of api2)', () => {
  it('maps the legacy page and its parameters to the document', () => {
    expect(migratedLegacyPdf('FeuillePresenceVisa.php', { S: '2026', Compet: 'N1H', equipe: '12', tz: 'Europe/Paris', extra: 'x' }))
      .toEqual({ document: 'presence', query: { season: '2026', competition: 'N1H', team: '12', tz: 'Europe/Paris', visa: '1' } })
    expect(migratedLegacyPdf('FeuilleControleEN.php', { season: '2026', compet: 'N1H', equipe: '%' }))
      .toEqual({ document: 'scrutineering-control', query: { season: '2026', competition: 'N1H', lang: 'en' } })
    expect(migratedLegacyPdf('FeuillePresenceNaissance.php', { S: '2026', Compet: 'N1H' })?.query.view).toBe('birthyear')
  })

  it('leaves the pages not migrated yet to the signed legacy link', () => {
    expect(migratedLegacyPdf('FeuilleMatchMulti.php', { listMatch: '1,2' })).toBeNull()
  })
})
