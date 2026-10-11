import type { MediaView, NewsSummary } from './news'

/** Editorial blocks of the home page, api2 `GET /home` (PAGE_CONTENT.md § 4). */

export type HomeBlock =
  | { kind: 'alert', level: 'info' | 'important', text: string }
  | { kind: 'featured' | 'latest_news', articles: NewsSummary[] }
  | { kind: 'partners', partners: { name: string, url: string, logo: MediaView | null }[] }

/** The partners block always closes the page: it comes after the events blocks. */
const BLOCKS_AFTER_EVENTS: readonly HomeBlock['kind'][] = ['partners']

/**
 * Blocks above and below the events blocks, each in the configured order (CNT-05). The alert comes first whatever
 * its position, above the page title.
 */
export function homeLayout(blocks: readonly HomeBlock[]): { alert: Extract<HomeBlock, { kind: 'alert' }> | null, beforeEvents: HomeBlock[], afterEvents: HomeBlock[] } {
  const alert = blocks.find((block): block is Extract<HomeBlock, { kind: 'alert' }> => block.kind === 'alert') ?? null
  const others = blocks.filter(block => block.kind !== 'alert')
  return {
    alert,
    beforeEvents: others.filter(block => !BLOCKS_AFTER_EVENTS.includes(block.kind)),
    afterEvents: others.filter(block => BLOCKS_AFTER_EVENTS.includes(block.kind)),
  }
}

/** ARIA role of the alert banner by level (CNT-06). */
export function alertRole(level: 'info' | 'important'): 'status' | 'alert' {
  return level === 'important' ? 'alert' : 'status'
}
