import { computed, onScopeDispose, ref } from 'vue'

/**
 * Bandeau « préprod expérimentale » (Phase 7 du plan CI/CD).
 *
 * La préprod héberge normalement le dernier `develop`. Le workflow
 * `deploy-preprod-experimental.yml` permet d'y déployer TEMPORAIREMENT une
 * branche `feature/*` : l'état de la préprod n'est alors plus celui qu'on croit,
 * d'où ce bandeau — il doit être impossible de confondre les deux.
 *
 * Pourquoi un fichier JSON récupéré à l'exécution, et non une variable de build :
 * les apps sont générées en STATIQUE (`nuxt generate`) et servies par nginx. Le
 * déploiement expérimental et son expiration (retour auto à `develop`) doivent
 * pouvoir changer l'état du bandeau SANS rebuild — seul un fichier lu au runtime
 * le permet. Le wrapper de déploiement dépose/supprime donc
 * `experimental-flag.json` à la racine servie de chaque app.
 *
 * Absence du fichier = cas NORMAL (préprod standard, dev, prod) → 404 attendu,
 * silencieux, aucun bandeau. On ne loggue rien pour ne pas polluer la console.
 */

/** Contenu de `experimental-flag.json` déposé par le wrapper de déploiement. */
export interface ExperimentalFlag {
  branch: string
  sha: string
  deployed_at: string
  expires_at: string
}

/**
 * Re-vérification périodique : le retour automatique à `develop` est piloté par
 * un cron côté VPS, donc un onglet resté ouvert doit voir le bandeau disparaître
 * sans rechargement. 5 min = compromis entre fraîcheur et bruit réseau (le
 * fichier pèse quelques centaines d'octets).
 */
const POLL_INTERVAL_MS = 5 * 60 * 1000

export const useExperimentalFlag = () => {
  const flag = ref<ExperimentalFlag | null>(null)
  const config = useRuntimeConfig()

  /** Le bandeau n'a de sens qu'en préprod : jamais de requête ailleurs. */
  const enabled = config.public.appEnv === 'preprod'

  const fetchFlag = async () => {
    if (!enabled || !import.meta.client) return

    try {
      // `baseUrl` (ex. /admin2) préfixe la racine servie par nginx.
      // cache: 'no-store' : sans ça, le fichier resterait en cache après
      // l'expiration du déploiement expérimental et le bandeau survivrait.
      const base = (config.public.baseUrl as string) || ''
      const res = await fetch(`${base}/experimental-flag.json`, { cache: 'no-store' })

      if (!res.ok) {
        // 404 = pas de déploiement expérimental : c'est le cas normal.
        flag.value = null
        return
      }

      const data = (await res.json()) as ExperimentalFlag
      // Un flag expiré est ignoré côté client aussi : si le cron du VPS a du
      // retard, on n'affiche pas un bandeau qui mentirait sur l'échéance.
      flag.value = new Date(data.expires_at).getTime() > Date.now() ? data : null
    } catch {
      // Fichier absent, JSON invalide, réseau coupé → pas de bandeau.
      // Ne JAMAIS faire échouer l'app pour un bandeau d'information.
      flag.value = null
    }
  }

  if (enabled && import.meta.client) {
    void fetchFlag()
    const timer = window.setInterval(() => void fetchFlag(), POLL_INTERVAL_MS)
    onScopeDispose(() => window.clearInterval(timer))
  }

  /** Heures restantes avant retour automatique à `develop` (arrondi haut, ≥ 0). */
  const hoursLeft = computed(() => {
    if (!flag.value) return 0
    const ms = new Date(flag.value.expires_at).getTime() - Date.now()
    return Math.max(0, Math.ceil(ms / 3_600_000))
  })

  return {
    flag,
    isExperimental: computed(() => flag.value !== null),
    hoursLeft
  }
}
