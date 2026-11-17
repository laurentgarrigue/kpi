export default defineNuxtRouteMiddleware(async (to) => {
  const authStore = useAuthStore()

  // Initialize auth from localStorage on first load. An expired token with a known user is brought back from the
  // refresh cookie before any redirection to /login (AUTH_HARDENING.md § 3ter, AUTH-SC-06).
  if (!authStore.isAuthenticated && authStore.initAuth() === 'restorable') {
    const outcome = await useAuth().restoreSession()
    // Refused: the session is really over. Unreachable: keep the local data, a later reload will retry.
    if (outcome === 'denied') authStore.clearAuth()
  }

  // Public routes that don't require authentication
  const publicRoutes = ['/login', '/reset-password', '/forgot-password', '/access-request', '/auth/google/link', '/auth/oauth-complete']

  if (publicRoutes.includes(to.path)) {
    // If already authenticated and on login, redirect to home
    if (authStore.isAuthenticated && to.path === '/login') {
      return navigateTo('/')
    }
    return
  }

  // Protected routes - require authentication
  if (!authStore.isAuthenticated) {
    return navigateTo('/login')
  }

  // select-mandate requires auth but no further checks
  if (to.path === '/select-mandate') {
    return
  }
})
