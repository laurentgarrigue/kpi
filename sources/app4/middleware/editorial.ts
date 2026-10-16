/**
 * Editorial section guard (PAGE_CMS.md, ADM-CMS-01): Editor capability or profile 1–2; pages declaring
 * `meta.editorialAdmin` (menu, home, redirections) need profile 1–2.
 */
export default defineNuxtRouteMiddleware((to) => {
  const authStore = useAuthStore()
  const allowed = to.meta.editorialAdmin ? authStore.canAdministerContent : authStore.canEditContent
  if (!allowed) {
    return navigateTo('/')
  }
})
