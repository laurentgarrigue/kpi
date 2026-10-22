import { describe, expect, it } from 'vitest'
import { homeLayout, type HomeBlock } from '../../app/utils/home'
import type { NewsSummary } from '../../app/utils/news'

const article = (slug: string): NewsSummary => ({ slug, title: slug, lead: null, cover: null, published_at: '2026-10-01T10:00:00+02:00', categories: [] })

describe('homeLayout (PAGE_HOME.md § 2 bis)', () => {
  it('HOME-13: the first featured article leads, then the latest news without duplicates, three at most', () => {
    const blocks: HomeBlock[] = [
      { kind: 'latest_news', articles: [article('a'), article('b'), article('c'), article('d')] },
      { kind: 'featured', articles: [article('c'), article('e')] },
    ]
    expect(homeLayout(blocks).news.map(item => item.slug)).toEqual(['c', 'a', 'b'])
  })

  it('CNT-05 / CNT-06: the alert and the partners are picked whatever their position', () => {
    const partners: HomeBlock = { kind: 'partners', partners: [] }
    const alert: HomeBlock = { kind: 'alert', level: 'info', text: 'Bassin fermé' }
    expect(homeLayout([partners, alert])).toEqual({ alert, news: [], partners })
    expect(homeLayout([])).toEqual({ alert: null, news: [], partners: null })
  })
})
