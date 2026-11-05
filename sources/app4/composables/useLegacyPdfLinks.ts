/**
 * Phase 0 of DOC/specs/PDF_GOTENBERG.md: the legacy admin PDF pages (admin/Feuille*.php) only answer to a signed
 * link. While mounted (admin layout), every click on a link to one of them goes through api2
 * (usePdf().openLegacyUrl), which checks the rights and signs the link, whatever the page holding the link.
 * Disappears with the last legacy page (phase 6).
 */
export const useLegacyPdfLinks = () => {
  const { openLegacyUrl } = usePdf()

  // Left and middle clicks, capture phase (some cells stop propagation). Only the navigation is cancelled: the
  // link's own click handler (closing a menu…) still runs.
  const intercept = (event: MouseEvent) => {
    if (event.button > 1) return
    const link = (event.target as HTMLElement | null)?.closest?.('a[href]') as HTMLAnchorElement | null
    if (link && openLegacyUrl(link.href)) {
      event.preventDefault()
    }
  }

  onMounted(() => {
    document.addEventListener('click', intercept, true)
    document.addEventListener('auxclick', intercept, true)
  })
  onUnmounted(() => {
    document.removeEventListener('click', intercept, true)
    document.removeEventListener('auxclick', intercept, true)
  })
}
