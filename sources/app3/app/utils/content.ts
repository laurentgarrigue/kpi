import { mediaUrl } from './news'

/**
 * Display of the editorial HTML of articles and pages (PAGE_NEWS.md § 2.2, PAGE_CONTENT.md § 2). The HTML comes
 * from api2, already filtered by its whitelist (FEATURE_CMS.md § 3.2): these transformations only adapt it to the
 * site (media host, anchors, videos loaded on click, images opening the viewer). Pure string functions, so that
 * the server renders the same HTML as the browser.
 */

export interface PageView {
  slug: string
  path: string
  title: string
  body: string
  parents: { title: string, path: string }[]
  children: { title: string, path: string }[]
  seo: { title: string, description: string }
  preview: boolean
}

export interface MenuEntry {
  label: string
  path: string
  external: boolean
  children: MenuEntry[]
}

export interface TocEntry {
  id: string
  label: string
}

export interface PreparedBody {
  html: string
  toc: TocEntry[]
}

export interface BodyOptions {
  legacyBaseUrl: string
  /** Label of a video placeholder, receives the provider name. */
  videoLabel: (provider: string) => string
}

/** Minimum number of h2 for the table of contents (CNT-03). */
export const TOC_MIN_HEADINGS = 3

const ATTRIBUTE = /([a-z-]+)=(?:"([^"]*)"|'([^']*)')/g
const ENTITIES: Record<string, string> = { '&amp;': '&', '&lt;': '<', '&gt;': '>', '&quot;': '"', '&#039;': '\'', '&#39;': '\'', '&nbsp;': ' ' }

const VIDEO_PROVIDERS = [
  { name: 'YouTube', embed: /^https:\/\/www\.youtube-nocookie\.com\/embed\/([\w-]+)$/, watch: (id: string) => `https://www.youtube.com/watch?v=${id}` },
  { name: 'Vimeo', embed: /^https:\/\/player\.vimeo\.com\/video\/(\d+)$/, watch: (id: string) => `https://vimeo.com/${id}` },
]

function attributes(tag: string): Record<string, string> {
  return Object.fromEntries([...tag.matchAll(ATTRIBUTE)].map(match => [match[1], match[2] ?? match[3] ?? '']))
}

function escapeAttribute(value: string): string {
  return value.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;')
}

function textOf(html: string): string {
  return html.replace(/<[^>]*>/g, '').replace(/&[#\w]+;/g, entity => ENTITIES[entity] ?? entity).replace(/\s+/g, ' ').trim()
}

/** Anchor of a heading: « Règles & jeu » → `regles-jeu`. */
export function headingId(label: string): string {
  return label.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'section'
}

/** Gives every h2 a unique anchor and lists them (table of contents, CNT-03). */
function anchorHeadings(html: string): PreparedBody {
  const toc: TocEntry[] = []
  const used = new Set<string>()
  const result = html.replace(/<h2>([\s\S]*?)<\/h2>/g, (_match, inner: string) => {
    const label = textOf(inner)
    const base = headingId(label)
    let id = base
    for (let suffix = 2; used.has(id); suffix++) {
      id = `${base}-${suffix}`
    }
    used.add(id)
    toc.push({ id, label })
    return `<h2 id="${id}">${inner}</h2>`
  })
  return { html: result, toc }
}

/** Media of the library are served by the legacy host. */
function absoluteMedia(html: string, legacyBaseUrl: string): string {
  return html.replace(/(<(?:img|a)\b[^>]*?\s(?:src|href)=")(\/media\/[^"]*)"/g, (_match, start: string, url: string) => `${start}${mediaUrl(url, legacyBaseUrl)}"`)
}

/**
 * Videos are loaded on click (no request to the provider before, SITE_LAYOUT.md § 6): the iframe becomes a link
 * to the video, which the article replaces by the player when JavaScript is available.
 */
function videoPlaceholders(html: string, videoLabel: BodyOptions['videoLabel']): string {
  return html.replace(/<iframe\b([^>]*)><\/iframe>/g, (match, rawAttributes: string) => {
    const { src = '', title = '' } = attributes(rawAttributes)
    for (const provider of VIDEO_PROVIDERS) {
      const id = provider.embed.exec(src)?.[1]
      if (id) {
        const label = escapeAttribute(title || videoLabel(provider.name))
        return `<a class="kpi-video" href="${provider.watch(id)}" data-video-src="${src}" data-video-title="${label}" target="_blank" rel="noopener">`
          + `<span class="kpi-video-play">${escapeAttribute(videoLabel(provider.name))}</span></a>`
      }
    }
    return ''
  })
}

/**
 * Images not already inside a link open the viewer (NEWS-07): wrapped in a link to the image (usable without
 * JavaScript) carrying their position in the gallery.
 */
function zoomableImages(html: string): string {
  let index = 0
  return html.replace(/(<a\b[^>]*>\s*)?(<img\b[^>]*>)/g, (match, link: string | undefined, image: string) => {
    if (link) {
      return match
    }
    const { src = '' } = attributes(image)
    return `<a class="kpi-zoom" href="${src}" data-gallery-index="${index++}">${image}</a>`
  })
}

export function prepareBody(html: string, options: BodyOptions): PreparedBody {
  const withMedia = absoluteMedia(html, options.legacyBaseUrl)
  const { html: anchored, toc } = anchorHeadings(withMedia)
  return { html: zoomableImages(videoPlaceholders(anchored, options.videoLabel)), toc }
}

/** Images of a prepared body, in gallery order. */
export function galleryImages(html: string): { src: string, alt: string }[] {
  return [...html.matchAll(/<a class="kpi-zoom"[^>]*>(<img\b[^>]*>)<\/a>/g)].map((match) => {
    const { src = '', alt = '' } = attributes(match[1]!)
    return { src, alt }
  })
}

/** api2 page slug of a site path: its last segment (PAGE_CONTENT.md § 2.1). */
export function pageSlug(segments: string | string[]): string | null {
  const parts = (Array.isArray(segments) ? segments : [segments]).filter(Boolean)
  const slug = parts.at(-1)
  return slug && /^[a-z0-9-]{1,120}$/.test(slug) ? slug : null
}

/** The page is only served under its parents' path; another path redirects to it (CNT-02). */
export function isCanonicalPagePath(segments: string | string[], page: Pick<PageView, 'path'>): boolean {
  const parts = (Array.isArray(segments) ? segments : [segments]).filter(Boolean)
  return `/${parts.join('/')}` === page.path
}
