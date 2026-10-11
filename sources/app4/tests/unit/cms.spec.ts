import { describe, expect, it } from 'vitest'
import {
  canAdministerContent, canEditContent, draftStorageKey, fieldKeyFromLabel, formatBytes, fromDateTimeLocal,
  isReservedPageSlug, isValidSlug, mediaSrc, moveItem, previewUrl, slugify, statusColor, toDateTimeLocal, validationErrors,
} from '../../utils/cms'

describe('editorial rights', () => {
  it('ADM-CMS-01: the Editor capability or profile 1–2 opens the editorial section', () => {
    expect(canEditContent(8, ['editor'])).toBe(true)
    expect(canEditContent(2, [])).toBe(true)
    expect(canEditContent(3, [])).toBe(false)
    expect(canEditContent(8, undefined)).toBe(false)
  })

  it('ADM-CMS-01: menu, home and redirections are for profiles 1–2 only', () => {
    expect(canAdministerContent(1)).toBe(true)
    expect(canAdministerContent(2)).toBe(true)
    expect(canAdministerContent(3)).toBe(false)
  })
})

describe('slugs', () => {
  it('ADM-CMS-02: slugs are generated from titles like api2 does', () => {
    expect(slugify('Sélection des Équipes de France 2026 !')).toBe('selection-des-equipes-de-france-2026')
    expect(slugify('  Cœur  ')).toBe('coeur')
    expect(isValidSlug('finale-n1')).toBe(true)
    expect(isValidSlug('Pas Valide')).toBe(false)
    expect(isValidSlug('fin-')).toBe(false)
  })

  it('CMS-06: route names of the public site are reserved for pages', () => {
    expect(isReservedPageSlug('calendar')).toBe(true)
    expect(isReservedPageSlug('reglements')).toBe(false)
  })
})

describe('lists and forms', () => {
  it('moves an item up or down, and leaves the ends unchanged', () => {
    expect(moveItem(['a', 'b', 'c'], 1, -1)).toEqual(['b', 'a', 'c'])
    expect(moveItem(['a', 'b', 'c'], 1, 1)).toEqual(['a', 'c', 'b'])
    expect(moveItem(['a', 'b', 'c'], 0, -1)).toEqual(['a', 'b', 'c'])
    expect(moveItem(['a', 'b', 'c'], 2, 1)).toEqual(['a', 'b', 'c'])
  })

  it('derives unique form field keys from their labels', () => {
    expect(fieldKeyFromLabel('Nom de l’équipe', [])).toBe('nom_de_l_equipe')
    expect(fieldKeyFromLabel('E-mail', ['e_mail'])).toBe('e_mail_2')
    expect(fieldKeyFromLabel('!!!', [])).toBe('field')
  })

  it('formats file sizes in the language of the screen', () => {
    expect(formatBytes(512, 'fr-FR')).toBe('512 o')
    expect(formatBytes(1_258_291, 'fr-FR')).toBe('1,2 Mo')
    expect(formatBytes(2048, 'en-GB')).toBe('2 KB')
  })

  it('converts dates between api2 and datetime-local inputs', () => {
    expect(toDateTimeLocal('2026-10-11 14:30:00')).toBe('2026-10-11T14:30')
    expect(toDateTimeLocal(null)).toBe('')
    expect(fromDateTimeLocal('2026-10-11T14:30')).toBe('2026-10-11 14:30:00')
    expect(fromDateTimeLocal('')).toBeNull()
  })

  it('reads the field errors of a 400 validation response', () => {
    expect(validationErrors({ error: 'validation_failed', errors: { slug: 'taken' } })).toEqual({ slug: 'taken' })
    expect(validationErrors(new Error('network'))).toEqual({})
  })
})

describe('preview and drafts', () => {
  it('ADM-CMS-04: the preview opens the public site with the signed token', () => {
    expect(previewUrl('https://beta.kpi.localhost/', '/news/finale', 'a.1.2.sig')).toBe('https://beta.kpi.localhost/news/finale?preview=a.1.2.sig')
    expect(previewUrl('https://beta.kpi.localhost', '/news/finale')).toBe('https://beta.kpi.localhost/news/finale')
  })

  it('CMS-04: media addresses point to the legacy host that serves /media/content', () => {
    expect(mediaSrc('/media/content/2026/10/a.jpg?v=1', 'https://kpi.localhost/')).toBe('https://kpi.localhost/media/content/2026/10/a.jpg?v=1')
    expect(mediaSrc('https://cdn.example/a.jpg', 'https://kpi.localhost')).toBe('https://cdn.example/a.jpg')
  })

  it('badges and local draft keys', () => {
    expect(statusColor('scheduled')).toBe('info')
    expect(draftStorageKey('article', 'new')).toBe('kpi-cms-draft-article-new')
  })
})
