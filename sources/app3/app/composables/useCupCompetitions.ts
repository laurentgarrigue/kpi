import type { InjectionKey } from 'vue'

/**
 * Codes of the cup competitions (`CP`) of the page's scope (competition, event or group), provided by the page to
 * the pitch grid, whose games do not carry their competition type (CMP-17).
 */
const CUP_COMPETITIONS: InjectionKey<ComputedRef<ReadonlySet<string>>> = Symbol('cup-competitions')
const CUP_TYPE = 'CP'

export function provideCupCompetitions(competitions: () => readonly { code: string, type: string }[]): void {
  provide(CUP_COMPETITIONS, computed(() => new Set(competitions().filter(item => item.type === CUP_TYPE).map(item => item.code))))
}

export function useCupCompetitions(): ComputedRef<ReadonlySet<string>> {
  return inject(CUP_COMPETITIONS, computed(() => new Set<string>()))
}
