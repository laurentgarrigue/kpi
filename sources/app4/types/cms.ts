// Editorial section (DOC/specs/PAGE_CMS.md): shapes of the api2 /admin/cms endpoints.
import type { ContentStatus, DisplayStatus } from '~/utils/cms'

export interface Paginated<T> {
  items: T[]
  page: number
  pages: number
  total: number
}

export interface CmsCategory {
  id: number
  slug: string
  label_fr: string
  label_en: string
}

export interface CmsMedia {
  id: number
  url: string
  thumbnail: string
  name: string
  mime: string
  width: number | null
  height: number | null
  size: number
  alt_fr: string
  alt_en: string
  credit: string
  album_id: number | null
  created_at: string
  updated_at: string
}

export interface CmsAlbum {
  id: number
  slug: string
  title_fr: string
  title_en: string
  cover_media_id: number | null
  media_count: number
}

export interface CmsArticleSummary {
  id: number
  slug: string
  title: string
  status: DisplayStatus
  published_at: string | null
  updated_at: string
  updated_by: string
  imported: boolean
  categories: number[]
}

export type CmsLinkKind = 'competition' | 'event' | 'club'

export interface CmsArticleLink {
  kind: CmsLinkKind
  ref: string
}

export interface CmsSeoFields {
  seo_title_fr: string
  seo_title_en: string
  seo_description_fr: string
  seo_description_en: string
}

export interface CmsArticle extends CmsSeoFields {
  id?: number
  slug: string
  title_fr: string
  title_en: string
  lead_fr: string
  lead_en: string
  body_fr: string
  body_en: string
  cover_media_id: number | null
  cover?: CmsMedia | null
  status: ContentStatus
  display_status?: DisplayStatus
  published_at: string | null
  signature: string
  categories: number[]
  links: CmsArticleLink[]
  updated_at?: string
  updated_by?: string
  imported?: boolean
}

export interface CmsPageSummary {
  id: number
  slug: string
  title: string
  status: ContentStatus
  parent_id: number | null
  path: string
  depth: number
  public: boolean
  updated_at: string
  imported: boolean
}

export interface CmsPage extends CmsSeoFields {
  id?: number
  slug: string
  parent_id: number | null
  path?: string
  title_fr: string
  title_en: string
  body_fr: string
  body_en: string
  status: ContentStatus
  updated_at?: string
  updated_by?: string
  imported?: boolean
}

export type CmsMenuTarget = 'page' | 'article' | 'url'

export interface CmsMenuItem {
  label_fr: string
  label_en: string
  target_kind: CmsMenuTarget
  target_ref: string
  children?: CmsMenuItem[]
}

export type CmsHomeBlockKind = 'alert' | 'featured' | 'latest_news' | 'partners'

export interface CmsPartner {
  name: string
  url: string
  media_id: number | null
}

export interface CmsHomeBlock {
  id: number
  kind: CmsHomeBlockKind
  position: number
  enabled: boolean
  config: {
    level?: 'info' | 'important'
    text_fr?: string
    text_en?: string
    from?: string | null
    to?: string | null
    articles?: number[]
    count?: number
    partners?: CmsPartner[]
  }
}

export interface CmsRedirect {
  id: number
  from_path: string
  to_path: string
  code: number
  hits: number
}

export interface CmsEditor {
  code: string
  name: string
}

// ------------------------------------------------------------------- forms (FEATURE_FORMS.md)

export const FORM_FIELD_TYPES = ['text', 'textarea', 'email', 'phone', 'number', 'date', 'select', 'radio', 'checkbox', 'club', 'competition'] as const
export type FormFieldType = typeof FORM_FIELD_TYPES[number]
export const FORM_TYPES_WITH_OPTIONS: readonly FormFieldType[] = ['select', 'radio', 'checkbox']
export const FORM_PERSONAL_TYPES: readonly FormFieldType[] = ['email', 'phone', 'textarea', 'date']

export interface FormFieldOption {
  value: string
  label_fr: string
  label_en: string
}

export interface FormField {
  key: string
  type: FormFieldType
  label_fr: string
  label_en: string
  required: boolean
  help_fr: string
  help_en: string
  options: FormFieldOption[]
  min: number | null
  max: number | null
}

export type EntryStatus = 'confirmed' | 'waiting' | 'cancelled'

export interface FormCounts {
  confirmed: number
  waiting: number
  cancelled: number
}

export interface FormSummary {
  id: number
  slug: string
  title: string
  status: 'draft' | 'published'
  state: 'draft' | 'upcoming' | 'open' | 'closed'
  opens_at: string | null
  closes_at: string | null
  capacity: number | null
  counts: FormCounts
  archived_entries: number
  imported: boolean
}

export interface FormDefinition {
  id?: number
  slug: string
  title_fr: string
  title_en: string
  intro_fr: string
  intro_en: string
  status: 'draft' | 'published'
  opens_at: string | null
  closes_at: string | null
  capacity: number | null
  waiting_list: boolean
  confirmation_mode: 'auto' | 'manual'
  payment_url: string
  notify_emails: string
  confirmation_text_fr: string
  confirmation_text_en: string
  public_list: boolean
  public_columns: string[]
  purpose_fr: string
  purpose_en: string
  retention_days: number
  event_id: number | null
  competition: string | null
  fields: FormField[]
  counts?: FormCounts
  archived_entries?: number
  imported?: boolean
}

export interface FormEntry {
  id: number
  status: EntryStatus
  reason: 'full' | 'manual' | null
  email: string
  created_at: string
  answers: Record<string, string>
}

export interface FormEntries {
  columns: { key: string, label: string }[]
  entries: FormEntry[]
  counts: FormCounts
}

export interface FormMail {
  id: number
  recipient: string
  subject: string
  status: 'sent' | 'failed'
  error: string
  sent_at: string
  resendable: boolean
}
