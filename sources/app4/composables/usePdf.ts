import { legacyAdminPdf, migratedLegacyPdf, publicPdfUrl, type AdminPdfDocument } from '~/utils/pdf'

interface SignedLink { url: string }
interface LegacySignedLink { path: string, query: Record<string, string> }

type PdfQuery = Record<string, string | number>

/**
 * Opens the PDF documents (DOC/specs/PDF_GOTENBERG.md § 6.3) without any PHP session:
 * - admin documents of api2: short-lived signed link (rights checked again when the PDF is downloaded);
 * - legacy admin pages (admin/Feuille*.php, phase 0): signed link issued by api2;
 * - public documents: plain link to the public route of api2.
 *
 * The tab is opened synchronously in the click, before the API call, so that no pop-up blocker (Safari iOS
 * included) stops it (PDF-FRONT-03).
 */
export const usePdf = () => {
  const api = useApi()
  const config = useRuntimeConfig()
  const toast = useToast()
  const { t } = useI18n()

  const legacyBaseUrl = config.public.legacyBaseUrl as string
  const api2BaseUrl = config.public.api2BaseUrl as string

  const openWith = async (signedUrl: () => Promise<string>) => {
    const tab = window.open('', '_blank')
    try {
      const url = await signedUrl()
      if (tab) {
        tab.opener = null
        tab.location.href = url
      }
      else {
        window.location.href = url
      }
    }
    catch {
      tab?.close()
      toast.add({ title: t('common.error'), description: t('common.pdf_open_error'), color: 'error' })
    }
  }

  const stringify = (query: PdfQuery): Record<string, string> =>
    Object.fromEntries(Object.entries(query).map(([key, value]) => [key, String(value)]))

  /** Admin document of api2 (`teams-groups`…), in a new tab. */
  const openDocument = (document: AdminPdfDocument, query: PdfQuery) => openWith(async () => {
    const link = await api.post<SignedLink>(`/admin/pdf/${document}/link`, stringify(query))
    return link.url
  })

  /**
   * Legacy admin PDF page (`FeuillePresence.php`…) through a signed link, in a new tab; the document of api2
   * replacing it once migrated.
   */
  const openLegacy = (page: string, query: PdfQuery) => {
    const migrated = migratedLegacyPdf(page, stringify(query))
    return migrated ? openDocument(migrated.document, migrated.query) : openSignedLegacy(page, query)
  }

  const openSignedLegacy = (page: string, query: PdfQuery) => openWith(async () => {
    const link = await api.post<LegacySignedLink>('/admin/pdf/legacy-link', { page, query: stringify(query) })
    return `${legacyBaseUrl}${link.path}?${new URLSearchParams(link.query).toString()}`
  })

  /** Same, from the URL of a legacy link; false when the URL is not a legacy admin PDF page. */
  const openLegacyUrl = (href: string): boolean => {
    const target = legacyAdminPdf(href, legacyBaseUrl)
    if (!target) {
      return false
    }
    void openLegacy(target.page, target.query)
    return true
  }

  const publicUrl = (document: string, query: PdfQuery) => publicPdfUrl(api2BaseUrl, document, query)

  return { openDocument, openLegacy, openLegacyUrl, publicUrl }
}
