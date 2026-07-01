/**
 * Moteur du tutoriel guidé (onboarding admin2).
 * Voir DOC/specs/TUTORIEL_ADMIN2.md.
 *
 * - Charge driver.js à la demande, côté client uniquement (SSR-safe).
 * - Lit les définitions déclaratives de `utils/tourSteps.ts`.
 * - Gère la navigation inter-pages entre les étapes.
 * - Versionne la progression via localStorage (`kpi_admin_tour_<id>_version`).
 */
import type { Tour, TourStep } from '~/utils/tourSteps'
import { TOURS } from '~/utils/tourSteps'

const storageKey = (tourId: string) => `kpi_admin_tour_${tourId}_version`

/** Version du tour déjà vue par l'utilisateur (0 si jamais). */
function getSeenVersion(tourId: string): number {
  if (!import.meta.client) return 0
  const raw = localStorage.getItem(storageKey(tourId))
  const parsed = raw ? parseInt(raw, 10) : 0
  return Number.isNaN(parsed) ? 0 : parsed
}

function setSeenVersion(tourId: string, version: number): void {
  if (!import.meta.client) return
  localStorage.setItem(storageKey(tourId), String(version))
}

export const useTour = (tourId: string = 'welcome') => {
  const { t } = useI18n()
  const router = useRouter()

  const tour: Tour | undefined = TOURS[tourId]

  /** True si de nouvelles étapes (isNew) existent depuis la dernière visite. */
  const hasNewSteps = computed(() => {
    if (!tour) return false
    const seen = getSeenVersion(tour.id)
    return seen > 0 && seen < tour.version && tour.steps.some(s => s.isNew)
  })

  /** True si l'utilisateur n'a jamais vu ce tour. */
  const isFirstVisit = computed(() => !!tour && getSeenVersion(tour.id) === 0)

  function selectorFor(step: TourStep): string | undefined {
    return step.anchor ? `[data-tour="${step.anchor}"]` : undefined
  }

  /** Attend qu'un élément apparaisse dans le DOM (après navigation), avec timeout. */
  function waitForElement(selector: string, timeout = 2000): Promise<void> {
    return new Promise((resolve) => {
      if (document.querySelector(selector)) return resolve()
      const start = Date.now()
      const iv = setInterval(() => {
        if (document.querySelector(selector) || Date.now() - start > timeout) {
          clearInterval(iv)
          resolve()
        }
      }, 100)
    })
  }

  /**
   * Lance le tour. Par défaut toutes les étapes ; si `onlyNew`, uniquement
   * celles marquées `isNew` (re-déclenchement pour utilisateurs déjà venus).
   */
  async function startTour(onlyNew = false): Promise<void> {
    if (!import.meta.client || !tour) return

    const { driver } = await import('driver.js')
    await import('driver.js/dist/driver.css')

    const steps = onlyNew ? tour.steps.filter(s => s.isNew) : tour.steps
    if (steps.length === 0) return

    const driverObj = driver({
      showProgress: true,
      allowClose: true,
      nextBtnText: t('tour.next'),
      prevBtnText: t('tour.prev'),
      doneBtnText: t('tour.done'),
      steps: steps.map((step) => {
        const element = selectorFor(step)
        return {
          element,
          popover: {
            title: t(step.titleKey),
            description: t(step.bodyKey),
            // Navigation inter-pages : si l'étape suivante vit sur une autre route,
            // on y va avant de continuer le tour.
            onNextClick: async (_el, _s, opts) => {
              const nextIndex = opts.state.activeIndex! + 1
              const next = steps[nextIndex]
              if (next?.route && router.currentRoute.value.path !== next.route) {
                await router.push(next.route)
                await nextTick()
                const sel = selectorFor(next)
                if (sel) await waitForElement(sel)
              }
              driverObj.moveNext()
            },
          },
        }
      }),
      onDestroyed: () => {
        setSeenVersion(tour.id, tour.version)
      },
    })

    // S'assurer d'être sur la bonne route pour la première étape.
    const first = steps[0]
    if (first?.route && router.currentRoute.value.path !== first.route) {
      await router.push(first.route)
      await nextTick()
      const sel = selectorFor(first)
      if (sel) await waitForElement(sel)
    }

    driverObj.drive()
  }

  /**
   * À appeler depuis la page d'accueil : lance le tour complet à la première visite,
   * ou seulement les nouveautés si l'utilisateur revient après une mise à jour.
   */
  async function maybeAutoStart(): Promise<void> {
    if (!import.meta.client || !tour) return
    if (isFirstVisit.value) {
      await startTour(false)
    } else if (hasNewSteps.value) {
      await startTour(true)
    }
  }

  return {
    hasNewSteps,
    isFirstVisit,
    startTour,
    maybeAutoStart,
  }
}
