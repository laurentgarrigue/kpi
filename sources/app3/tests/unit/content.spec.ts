import { describe, expect, it } from 'vitest'
import { galleryImages, headingId, isCanonicalPagePath, pageSlug, prepareBody } from '../../app/utils/content'

const options = { legacyBaseUrl: 'https://kpi.localhost', videoLabel: (provider: string) => `Lire la vidéo (${provider})` }

describe('editorial body (PAGE_NEWS.md § 2.2, PAGE_CONTENT.md § 2)', () => {
  it('CNT-03: gives every h2 a unique anchor and lists them', () => {
    const { html, toc } = prepareBody('<h2>Règles &amp; jeu</h2><p>a</p><h2>Histoire</h2><h2>Histoire</h2><h3>Sous</h3>', options)
    expect(toc).toEqual([{ id: 'regles-jeu', label: 'Règles & jeu' }, { id: 'histoire', label: 'Histoire' }, { id: 'histoire-2', label: 'Histoire' }])
    expect(html).toContain('<h2 id="regles-jeu">Règles &amp; jeu</h2>')
    expect(html).toContain('<h2 id="histoire-2">Histoire</h2>')
    expect(headingId('!!!')).toBe('section')
  })

  it('NEWS-03: library media are served by the legacy host', () => {
    const { html } = prepareBody('<p><a href="/media/content/r.pdf">Règlement</a> <a href="/news/x">x</a></p>', options)
    expect(html).toBe('<p><a href="https://kpi.localhost/media/content/r.pdf">Règlement</a> <a href="/news/x">x</a></p>')
  })

  it('NEWS-03: videos become links loading the player on click, nothing is requested from the provider before', () => {
    const { html } = prepareBody(
      '<iframe src="https://www.youtube-nocookie.com/embed/abcdefgh" title="Finale" allowfullscreen=""></iframe>'
      + '<iframe src="https://player.vimeo.com/video/123456" allowfullscreen=""></iframe>'
      + '<iframe src="https://evil.example/embed"></iframe>',
      options,
    )
    expect(html).not.toContain('<iframe')
    expect(html).toContain('<a class="kpi-video" href="https://www.youtube.com/watch?v=abcdefgh" data-video-src="https://www.youtube-nocookie.com/embed/abcdefgh" data-video-title="Finale"')
    expect(html).toContain('href="https://vimeo.com/123456" data-video-src="https://player.vimeo.com/video/123456" data-video-title="Lire la vidéo (Vimeo)"')
    expect(html).not.toContain('evil')
  })

  it('NEWS-07: images outside a link open the viewer, in order', () => {
    const { html } = prepareBody('<p><a href="/x"><img src="/media/content/a.jpg" alt="A"></a><img src="/media/content/b.jpg" alt=\'B "q\'><img src="/media/content/c.jpg" alt="C"></p>', options)
    expect(html).toContain('<a href="/x"><img src="https://kpi.localhost/media/content/a.jpg" alt="A"></a>')
    expect(galleryImages(html)).toEqual([
      { src: 'https://kpi.localhost/media/content/b.jpg', alt: 'B "q' },
      { src: 'https://kpi.localhost/media/content/c.jpg', alt: 'C' },
    ])
    expect(html).toContain('data-gallery-index="1"')
  })
})

describe('editorial pages (PAGE_CONTENT.md § 2)', () => {
  it('CNT-01: the api2 slug is the last segment of the path', () => {
    expect(pageSlug(['le-kayak-polo', 'arbitrage'])).toBe('arbitrage')
    expect(pageSlug('le-kayak-polo')).toBe('le-kayak-polo')
    expect(pageSlug(['Bad_Slug'])).toBeNull()
    expect(pageSlug([])).toBeNull()
  })

  it('CNT-02: a child page is only served under its parent path', () => {
    const page = { path: '/le-kayak-polo/arbitrage' }
    expect(isCanonicalPagePath(['le-kayak-polo', 'arbitrage'], page)).toBe(true)
    expect(isCanonicalPagePath(['arbitrage'], page)).toBe(false)
  })
})
