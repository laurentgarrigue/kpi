/** Filters of the athletes list (PAGE_ATHLETES.md § 2.6). */
export interface AthletesListFilters {
  q: string
  unknownBirthDate: boolean
  international: boolean
  engagedThisSeason: boolean
}

export interface AthletesListItem {
  matric: number
  nom: string
  prenom: string
  sexe: string
  /** null = birth date unknown (conventional dates included) */
  naissance: string | null
  international: boolean
  club: string
  codeClub: string
}

/** Response of GET /admin/athletes */
export interface AthletesListResponse {
  items: AthletesListItem[]
  total: number
  page: number
  limit: number
}
