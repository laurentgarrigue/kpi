import type {
  CmsAlbum, CmsArticle, CmsArticleSummary, CmsCategory, CmsEditor, CmsHomeBlock, CmsMedia, CmsMenuItem, CmsPage,
  CmsPageSummary, CmsRedirect, EntryStatus, FormDefinition, FormEntries, FormMail, FormSummary, Paginated,
} from '~/types/cms'

/** api2 /admin/cms endpoints of the editorial section (DOC/specs/PAGE_CMS.md, FEATURE_CMS.md § 5.2). */
export const useCmsApi = () => {
  const api = useApi()
  const base = '/admin/cms'

  return {
    categories: () => api.get<CmsCategory[]>(`${base}/categories`),

    articles: (params: Record<string, string | number>) => api.get<Paginated<CmsArticleSummary>>(`${base}/articles`, params),
    article: (id: number) => api.get<CmsArticle>(`${base}/articles/${id}`),
    saveArticle: (article: CmsArticle) => article.id
      ? api.put<CmsArticle>(`${base}/articles/${article.id}`, article)
      : api.post<CmsArticle>(`${base}/articles`, article),
    deleteArticle: (id: number) => api.del(`${base}/articles/${id}`),
    articlePreviewToken: (id: number) => api.get<{ token: string }>(`${base}/articles/${id}/preview-token`),

    pages: () => api.get<CmsPageSummary[]>(`${base}/pages`),
    page: (id: number) => api.get<CmsPage>(`${base}/pages/${id}`),
    savePage: (page: CmsPage) => page.id
      ? api.put<CmsPage>(`${base}/pages/${page.id}`, page)
      : api.post<CmsPage>(`${base}/pages`, page),
    deletePage: (id: number) => api.del(`${base}/pages/${id}`),
    pagePreviewToken: (id: number) => api.get<{ token: string }>(`${base}/pages/${id}/preview-token`),

    media: (params: Record<string, string | number>) => api.get<Paginated<CmsMedia>>(`${base}/media`, params),
    uploadMedia: (file: File, meta: Record<string, string>) => {
      const data = new FormData()
      data.append('file', file)
      Object.entries(meta).forEach(([key, value]) => data.append(key, value))
      return api.upload<CmsMedia>(`${base}/media`, data)
    },
    replaceMedia: (id: number, file: File) => {
      const data = new FormData()
      data.append('file', file)
      return api.upload<CmsMedia>(`${base}/media/${id}/file`, data)
    },
    updateMedia: (id: number, meta: Partial<CmsMedia>) => api.put<CmsMedia>(`${base}/media/${id}`, meta),
    deleteMedia: (id: number) => api.del(`${base}/media/${id}`),
    albums: () => api.get<CmsAlbum[]>(`${base}/albums`),
    saveAlbum: (album: Partial<CmsAlbum>) => album.id
      ? api.put<{ id: number }>(`${base}/albums/${album.id}`, album)
      : api.post<{ id: number }>(`${base}/albums`, album),
    deleteAlbum: (id: number) => api.del(`${base}/albums/${id}`),

    menu: () => api.get<CmsMenuItem[]>(`${base}/menu`),
    saveMenu: (items: CmsMenuItem[]) => api.put<CmsMenuItem[]>(`${base}/menu`, { items }),
    homeBlocks: () => api.get<CmsHomeBlock[]>(`${base}/home-blocks`),
    saveHomeBlocks: (blocks: CmsHomeBlock[]) => api.put<CmsHomeBlock[]>(`${base}/home-blocks`, { blocks }),
    redirects: () => api.get<CmsRedirect[]>(`${base}/redirects`),
    saveRedirect: (redirect: { from_path: string, to_path: string }) => api.post<{ id: number }>(`${base}/redirects`, redirect),
    deleteRedirect: (id: number) => api.del(`${base}/redirects/${id}`),

    editors: () => api.get<CmsEditor[]>(`${base}/editors`),
    setEditor: (code: string, editor: boolean) => api.put(`${base}/editors/${encodeURIComponent(code)}`, { editor }),

    forms: () => api.get<FormSummary[]>(`${base}/forms`),
    form: (id: number) => api.get<FormDefinition>(`${base}/forms/${id}`),
    saveForm: (form: FormDefinition) => form.id
      ? api.put<FormDefinition>(`${base}/forms/${form.id}`, form)
      : api.post<FormDefinition>(`${base}/forms`, form),
    deleteForm: (id: number) => api.del(`${base}/forms/${id}`),
    formEntries: (id: number) => api.get<FormEntries>(`${base}/forms/${id}/entries`),
    setEntryStatus: (id: number, entryId: number, status: EntryStatus) => api.put<FormEntries>(`${base}/forms/${id}/entries/${entryId}`, { status }),
    exportForm: (id: number, format: 'csv' | 'xlsx') => api.getBlob(`${base}/forms/${id}/export?format=${format}`),
    formMails: (id: number) => api.get<FormMail[]>(`${base}/forms/${id}/mails`),
    resendMail: (id: number, mailId: number) => api.post<{ status: string }>(`${base}/forms/${id}/mails/${mailId}/resend`),
  }
}
