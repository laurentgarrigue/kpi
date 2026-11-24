import { useNuxtApp } from '#imports'

/**
 * Translate with the app's own i18n, so that tests follow the wording instead of copying it.
 * A missing key comes back as the key itself: it would make any « text contains t(key) » assertion
 * pass for the wrong reason, so it fails loudly instead.
 */
export const t = (key: string, params: Record<string, unknown> = {}): string => {
  const text = useNuxtApp().$i18n.t(key, params) as string
  if (text === key) throw new Error(`Missing i18n key in the test locale: ${key}`)
  return text
}
