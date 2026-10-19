import { canManageScrutineering } from '~/utils/scrutineering'

/** Scrutineering screens (PAGE_SCRUTINEERING.md, ADM-SCR-02): profiles 1–2 and the profile 10. */
export default defineNuxtRouteMiddleware(() => {
  if (!canManageScrutineering(useAuthStore().profile)) {
    return navigateTo('/')
  }
})
