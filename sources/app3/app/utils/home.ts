import type { MediaView, NewsSummary } from './news'

/** Editorial blocks of the home page, api2 `GET /home` (PAGE_CONTENT.md § 4). */

export type HomeBlock =
  | { kind: 'alert', level: 'info' | 'important', text: string }
  | { kind: 'featured' | 'latest_news', articles: NewsSummary[] }
  | { kind: 'partners', partners: { name: string, url: string, logo: MediaView | null }[] }

export type AlertBlock = Extract<HomeBlock, { kind: 'alert' }>

export type PartnersBlock = Extract<HomeBlock, { kind: 'partners' }>

/** News of the home page: one put forward, then two (HOME-13). */
export const HOME_NEWS_COUNT = 3

/**
 * Blocks of the home page (CNT-05, HOME-13): the alert first, whatever its position; the news column (the first
 * featured article, then the latest news, without duplicates); the partners closing the page.
 */
export function homeLayout(blocks: readonly HomeBlock[]): { alert: AlertBlock | null, news: NewsSummary[], partners: PartnersBlock | null } {
  const articles = (kind: 'featured' | 'latest_news') => blocks.flatMap(block => (block.kind === kind ? block.articles : []))
  const unique = [...articles('featured').slice(0, 1), ...articles('latest_news')]
    .filter((article, index, list) => list.findIndex(other => other.slug === article.slug) === index)
  return {
    alert: blocks.find((block): block is AlertBlock => block.kind === 'alert') ?? null,
    news: unique.slice(0, HOME_NEWS_COUNT),
    partners: blocks.find((block): block is PartnersBlock => block.kind === 'partners') ?? null,
  }
}

/** ARIA role of the alert banner by level (CNT-06). */
export function alertRole(level: 'info' | 'important'): 'status' | 'alert' {
  return level === 'important' ? 'alert' : 'status'
}
