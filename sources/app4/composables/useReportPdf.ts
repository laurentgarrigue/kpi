/** Ouvre le PDF d'un rapport ou un PDF vierge (FEATURE_DESIGNATIONS.md D17, D29), avec le jeton de session. */
export const useReportPdf = () => {
  const api = useApi()
  const { locale } = useI18n()
  const lang = () => (locale.value === 'en' ? 'en' : 'fr')

  const open = async (endpoint: string) => {
    const tab = window.open('', '_blank')
    try {
      const buffer = await api.getBlob(endpoint)
      const url = URL.createObjectURL(new Blob([buffer], { type: 'application/pdf' }))
      if (tab) tab.location.href = url
      else window.open(url, '_blank')
    } catch {
      tab?.close()
    }
  }

  return {
    openReport: (id: number, number?: number) => open(`/admin/reports/${id}/pdf?lang=${lang()}${number ? `&number=${number}` : ''}`),
    openBlank: (type: string, season: string) => open(`/admin/reports/blank/${type}?lang=${lang()}&season=${season}`),
  }
}
