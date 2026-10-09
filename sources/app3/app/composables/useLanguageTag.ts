/** BCP 47 tag of the current language (`fr-FR`, `en-GB`), for `Intl` date and number formats. */
export function useLanguageTag(): ComputedRef<string> {
  const { locale, locales } = useI18n()
  return computed(() => locales.value.find(item => item.code === locale.value)?.language ?? locale.value)
}
