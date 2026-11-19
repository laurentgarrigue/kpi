import { useNuxtApp } from '#imports'

/** Translate with the app's own i18n, so that tests follow the wording instead of copying it. */
export const t = (key: string, params: Record<string, unknown> = {}): string =>
  useNuxtApp().$i18n.t(key, params) as string
