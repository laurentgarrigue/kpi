import { useNuxtApp } from '#imports'

/**
 * Translate with the app's own i18n, so that tests follow the wording instead of copying it (same helper as app4).
 * A missing key comes back as the key itself: it fails loudly instead of letting a « contains » assertion pass.
 */
export const t = (key: string, params: Record<string, unknown> = {}): string => {
  const text = useNuxtApp().$i18n.t(key, params) as string
  if (text === key) throw new Error(`Missing i18n key in the test locale: ${key}`)
  return text
}
