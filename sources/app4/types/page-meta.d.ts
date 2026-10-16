// Page meta of the editorial section (middleware/editorial.ts).
declare module '#app' {
  interface PageMeta {
    /** Menu, home blocks, redirections: profile 1–2 only (PAGE_CMS.md § 5). */
    editorialAdmin?: boolean
  }
}

export {}
