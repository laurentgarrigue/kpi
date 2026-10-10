/** « Back to top » appears once the page is scrolled by this much (px), as in app4. */
export const SCROLL_UP_THRESHOLD = 300
/** « Go to bottom » disappears this close to the bottom of the page (px). */
export const SCROLL_BOTTOM_MARGIN = 10

/** Which scroll arrows to show for the current position (SITE_LAYOUT.md § 2, LAY-11). */
export function scrollArrows(scrollY: number, pageHeight: number, viewportHeight: number): { up: boolean, down: boolean } {
  return {
    up: scrollY > SCROLL_UP_THRESHOLD,
    down: scrollY < pageHeight - viewportHeight - SCROLL_BOTTOM_MARGIN,
  }
}
