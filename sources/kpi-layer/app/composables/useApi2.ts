/**
 * Single api2 client (CLEAN_CODE.md § 2): `$fetch` bound to the right base URL for the current side.
 * Usage: `const api2 = useApi2(); const events = await api2<Event[]>('/events/all')`.
 */
export function useApi2() {
  const config = useRuntimeConfig()
  const baseURL = resolveApi2BaseUrl({
    internalUrl: import.meta.server ? config.api2InternalUrl : '',
    publicUrl: config.public.api2BaseUrl,
    isServer: import.meta.server,
  })
  // responseType 'json': parse JSON even if a proxy or server sends the wrong Content-Type
  // (a Blob would not survive SSR payload serialisation).
  return $fetch.create({ baseURL, responseType: 'json', headers: { Accept: 'application/json' } })
}
