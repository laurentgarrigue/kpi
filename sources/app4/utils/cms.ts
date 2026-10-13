/**
 * Pure helpers of the editorial section (DOC/specs/PAGE_CMS.md). No Nuxt import: unit-tested with Vitest.
 */

export const EDITOR_CAPABILITY = 'editor'
const ADMIN_MAX_PROFILE = 2

export const CONTENT_STATUSES = ['draft', 'published', 'archived'] as const
export type ContentStatus = typeof CONTENT_STATUSES[number]
export type DisplayStatus = ContentStatus | 'scheduled'

/** Slugs that are routes of the public site: a page cannot use them (FEATURE_CMS.md § 6). Same list as api2. */
export const RESERVED_PAGE_SLUGS = [
  'news', 'competitions', 'calendar', 'events', 'groups', 'history', 'teams', 'clubs', 'search', 'forms',
  'media', 'img', 'api', 'api2', 'admin', 'admin2', 'en', 'fr', 'healthz', 'robots.txt', 'sitemap.xml',
  'rss.xml', 'games', '_nuxt', '_payload.json', '_internal',
] as const

const SLUG_PATTERN = /^[a-z0-9]+(?:-[a-z0-9]+)*$/
export const SLUG_MAX_LENGTH = 150
const FIELD_KEY_MAX_LENGTH = 36

/** Editorial screens: the Editor capability or profile 1–2 (ADM-CMS-01). */
export function canEditContent(profile: number, capabilities: readonly string[] | undefined): boolean {
  return canAdministerContent(profile) || (capabilities ?? []).includes(EDITOR_CAPABILITY)
}

/** Menu, home blocks, redirections, deletion, Editor capability: profile 1–2 only (ADM-CMS-01). */
export function canAdministerContent(profile: number): boolean {
  return profile <= ADMIN_MAX_PROFILE
}

/** Lower-case ASCII slug from a title (same rule as api2 CmsRules::slugify). */
export function slugify(text: string): string {
  return text
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[œŒ]/g, 'oe')
    .replace(/[æÆ]/g, 'ae')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, SLUG_MAX_LENGTH)
}

export function isValidSlug(slug: string): boolean {
  return slug.length <= SLUG_MAX_LENGTH && SLUG_PATTERN.test(slug)
}

export function isReservedPageSlug(slug: string): boolean {
  return (RESERVED_PAGE_SLUGS as readonly string[]).includes(slug)
}

/** Badge colour of a content status. */
export function statusColor(status: DisplayStatus): 'neutral' | 'success' | 'info' | 'warning' {
  return ({ draft: 'neutral', published: 'success', scheduled: 'info', archived: 'warning' } as const)[status]
}

/** Levels of the editorial menu: entry, sub-entry, sub-sub-entry (PAGE_CMS.md § 5, api2 CmsRules::MENU_MAX_DEPTH). */
export const MENU_MAX_DEPTH = 3

/** An entry at this depth (1 = top level) can receive sub-entries. */
export function canHaveMenuChildren(depth: number): boolean {
  return depth < MENU_MAX_DEPTH
}

/** Copy of a list with one item moved up (-1) or down (+1); unchanged at the ends. */
export function moveItem<T>(items: readonly T[], index: number, delta: -1 | 1): T[] {
  const target = index + delta
  if (index < 0 || index >= items.length || target < 0 || target >= items.length) {
    return [...items]
  }
  const moved = [...items]
  const [item] = moved.splice(index, 1)
  moved.splice(target, 0, item as T)
  return moved
}

/** « 1,2 Mo » style file size. */
export function formatBytes(bytes: number, locale: string): string {
  const units = locale.startsWith('fr') ? ['o', 'Ko', 'Mo', 'Go'] : ['B', 'KB', 'MB', 'GB']
  let value = bytes
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }
  const digits = unit === 0 ? 0 : 1
  return `${new Intl.NumberFormat(locale, { maximumFractionDigits: digits }).format(value)} ${units[unit]}`
}

/** Unique form field key (`[a-z0-9_]`) derived from its label. */
export function fieldKeyFromLabel(label: string, existingKeys: readonly string[]): string {
  const base = slugify(label).replace(/-/g, '_').slice(0, FIELD_KEY_MAX_LENGTH) || 'field'
  let key = base
  for (let suffix = 2; existingKeys.includes(key); suffix++) {
    key = `${base}_${suffix}`
  }
  return key
}

/** Preview address on the public site: the content with its signed token (ADM-CMS-04). */
export function previewUrl(publicSiteUrl: string, path: string, token?: string): string {
  const url = `${publicSiteUrl.replace(/\/$/, '')}${path}`
  return token ? `${url}?preview=${encodeURIComponent(token)}` : url
}

/** Absolute address of a media URL of api2 (`/media/content/…`, served by the legacy host). */
export function mediaSrc(url: string, legacyBaseUrl: string): string {
  return url.startsWith('/') ? `${legacyBaseUrl.replace(/\/$/, '')}${url}` : url
}

/** SQL date-time (`YYYY-MM-DD HH:MM:SS`) → value of an `<input type="datetime-local">`. */
export function toDateTimeLocal(sql: string | null | undefined): string {
  return sql ? sql.replace(' ', 'T').slice(0, 16) : ''
}

/** `<input type="datetime-local">` value → SQL date-time, or null when empty. */
export function fromDateTimeLocal(value: string): string | null {
  return value ? `${value.replace('T', ' ')}:00`.slice(0, 19) : null
}

/** Field errors returned by api2 (`{ error: 'validation_failed', errors: { field: code } }`), or {}. */
export function validationErrors(error: unknown): Record<string, string> {
  const body = error as { error?: string, errors?: Record<string, string> } | null
  return body?.error === 'validation_failed' && body.errors ? body.errors : {}
}

/** Browser storage key of the local draft of a content being edited. */
export function draftStorageKey(kind: 'article' | 'page', id: number | 'new'): string {
  return `kpi-cms-draft-${kind}-${id}`
}
