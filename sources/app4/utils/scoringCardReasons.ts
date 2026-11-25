/**
 * Card reasons (motifs) offered by the scoring console, in display order (reused from
 * FMV3, spec §0.9). `unknown` (« Autre / Non précisé ») is the default and sits last.
 * `icon` is the inner markup of a 24×24 stroke pictogram (currentColor), the same style as
 * the medals' — static content, never user input.
 */
export const CARD_REASONS = [
  { code: 'r_pad', icon: '<path d="M6.5 17.5 17.5 6.5"/><ellipse cx="5" cy="19" rx="4" ry="2" transform="rotate(-45 5 19)"/><ellipse cx="19" cy="5" rx="4" ry="2" transform="rotate(-45 19 5)"/>' },
  { code: 'r_kt', icon: '<path d="M1.5 12c4-3.5 9-3.5 14 0-5 3.5-10 3.5-14 0z"/><path d="M19 3c-2 3.5-2 14.5 0 18 2-3.5 2-14.5 0-18z"/><path d="M15 6.5l1.5 2M15 17.5l1.5-2"/>' },
  { code: 'r_ht', icon: '<path d="M18 11V6a2 2 0 0 0-4 0M14 10V4a2 2 0 0 0-4 0v2M10 10.5V6a2 2 0 0 0-4 0v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.9-5.7-2.4L3.4 16a2 2 0 0 1 3.2-2.4L8 15"/>' },
  { code: 'r_p', icon: '<circle cx="12" cy="13.5" r="8"/><path d="M9.5 2h5M12 2v3.5M18.5 6l1.5-1.5"/><text x="12" y="17.5" text-anchor="middle" font-size="10" font-weight="700" fill="currentColor" stroke="none">5</text>' },
  { code: 'r_o', icon: '<path d="M3 5h18v4H3zM3 13h18v4H3zM6 9v4M18 9v4M6 17v4M18 17v4"/>' },
  { code: 'r_rep', icon: '<path d="m17 2 4 4-4 4"/><path d="M3 11V9a3 3 0 0 1 3-3h15"/><path d="m7 22-4-4 4-4"/><path d="M21 13v2a3 3 0 0 1-3 3H3"/>' },
  { code: 'r_un', icon: '<circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>' },
  { code: 'r_ct', icon: '<path d="M21 12a8 8 0 0 1-11.5 7.2L3 21l1.8-6A8 8 0 1 1 21 12z"/><path d="M12 8v4M12 16h.01"/>' },
  { code: 'unknown', icon: '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/>' }
] as const

export type CardReasonCode = typeof CARD_REASONS[number]['code']
