// Composable test (project `nuxt`): ouverture du PDF d'un rapport avec le jeton de session (D17, D29).
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'

const getBlob = vi.fn()
mockNuxtImport('useApi', () => () => ({ getBlob }))
mockNuxtImport('useI18n', () => () => ({ locale: ref('fr') }))

describe('useReportPdf', () => {
  afterEach(() => {
    vi.restoreAllMocks()
    getBlob.mockReset()
  })

  it('opens a report PDF in the tab opened synchronously, numbered for a given submission', async () => {
    const tab = { location: { href: '' }, close: vi.fn() }
    vi.spyOn(window, 'open').mockReturnValue(tab as unknown as Window)
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:pdf')
    getBlob.mockResolvedValue(new ArrayBuffer(4))
    await useReportPdf().openReport(12, 2)
    expect(getBlob).toHaveBeenCalledWith('/admin/reports/12/pdf?lang=fr&number=2')
    expect(tab.location.href).toBe('blob:pdf')
  })

  it('falls back to a new window when the tab was blocked, and opens a blank PDF of a type', async () => {
    const open = vi.spyOn(window, 'open').mockReturnValue(null)
    vi.spyOn(URL, 'createObjectURL').mockReturnValue('blob:blank')
    getBlob.mockResolvedValue(new ArrayBuffer(4))
    await useReportPdf().openBlank('INCIDENT', '2026')
    expect(getBlob).toHaveBeenCalledWith('/admin/reports/blank/INCIDENT?lang=fr&season=2026')
    expect(open).toHaveBeenLastCalledWith('blob:blank', '_blank')
  })

  it('closes the tab when the PDF cannot be fetched', async () => {
    const tab = { location: { href: '' }, close: vi.fn() }
    vi.spyOn(window, 'open').mockReturnValue(tab as unknown as Window)
    getBlob.mockRejectedValue(new Error('boom'))
    await useReportPdf().openReport(1)
    expect(tab.close).toHaveBeenCalled()
  })
})
