import type { IneligibleSummary } from '~/types/presence'

/**
 * Messages for the player eligibility rules ("joueur en règle").
 * The rules themselves live server-side only: api2 src/Eligibility/PlayerEligibilityRules.php.
 */
export function useEligibilityMessages() {
  const { t } = useI18n()
  const toast = useToast()

  const errorLabel = (code: string, minPagaie?: string | null): string =>
    t(`presence.error_${code}`, { min: minPagaie ? t(`presence.paddle_colors.${minPagaie}`) : '' })

  const errorLabels = (codes: string[] = [], minPagaie?: string | null): string[] =>
    codes.map(code => errorLabel(code, minPagaie))

  // Non-blocking rule set (regional): the action succeeded but the player is not compliant
  const notifyWarnings = (codes: string[], minPagaie?: string | null) => {
    if (!codes.length) return
    toast.add({
      title: t('presence.eligibility_warning_title'),
      description: errorLabels(codes, minPagaie).join(' · '),
      icon: 'i-heroicons-exclamation-triangle',
      color: 'warning',
      duration: 8000
    })
  }

  // Roster copy: non-compliant players were made inactive (national) or only reported (regional)
  const notifyIneligible = (summary?: IneligibleSummary | null) => {
    if (!summary?.count) return
    toast.add({
      title: t('presence.eligibility_warning_title'),
      description: t(
        summary.enforcement === 'block' ? 'presence.copy_ineligible_inactivated' : 'presence.copy_ineligible_warned',
        { count: summary.count }
      ),
      icon: 'i-heroicons-exclamation-triangle',
      color: 'warning',
      duration: 8000
    })
  }

  return { errorLabel, errorLabels, notifyWarnings, notifyIneligible }
}
