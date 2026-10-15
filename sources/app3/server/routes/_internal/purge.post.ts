// Cache purge called by api2 after a publication (FEATURE_CMS.md § 5.4, CMS-07), protected by the shared secret
// `NUXT_APP3_PURGE_TOKEN` (= `APP3_PURGE_TOKEN` of api2). Body: `{ paths: ["/news", "/news/{slug}", …] }`.
const MAX_PATHS = 200

export default defineEventHandler(async (event) => {
  if (!isPurgeAuthorized(getRequestHeader(event, 'authorization'), useRuntimeConfig(event).app3PurgeToken)) {
    throw createError({ statusCode: 401, statusMessage: 'Unauthorized' })
  }
  const body = await readBody<{ paths?: unknown }>(event).catch(() => null)
  const paths = Array.isArray(body?.paths) ? body.paths.filter((path): path is string => typeof path === 'string' && path.startsWith('/')) : []
  if (paths.length === 0 || paths.length > MAX_PATHS) {
    throw createError({ statusCode: 400, statusMessage: 'Invalid paths' })
  }
  const storage = useStorage()
  const keys = purgedKeys(paths, await storage.getKeys(ROUTE_CACHE_BASE))
  await Promise.all(keys.map(key => storage.removeItem(key)))
  return { purged: keys.length }
})
