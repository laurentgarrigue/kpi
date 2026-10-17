export interface Api2UrlOptions {
  /** api2 URL on the Docker network, used by the Nitro server (empty when not configured). */
  internalUrl: string
  /** api2 URL as seen by the browser. */
  publicUrl: string
  isServer: boolean
}

/** Base URL for api2 calls: internal on the server when available, public otherwise (SITE_PLATFORM.md PLT-04). */
export function resolveApi2BaseUrl({ internalUrl, publicUrl, isServer }: Api2UrlOptions): string {
  const url = isServer && internalUrl ? internalUrl : publicUrl
  return url.replace(/\/+$/, '')
}
