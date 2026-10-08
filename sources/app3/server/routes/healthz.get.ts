// Liveness probe: answers without calling api2 (SITE_PLATFORM.md § 4.3, PLT-01).
export default defineEventHandler(() => ({ status: 'ok' }))
