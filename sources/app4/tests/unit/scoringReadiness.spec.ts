import { describe, expect, it } from 'vitest'
import type { ScoringPlayer } from '../../types/scoring'
import { matchReadiness, ROSTER_LIMITS } from '../../utils/scoringReadiness'

/**
 * Contrôles avant coup d'envoi (PAGE_SCORING.md §7.2) : ce que la table de marque doit
 * vérifier sur la feuille de match. Ce sont des **avertissements** — la console ne bloque
 * jamais le coup d'envoi (la feuille papier et l'arbitre restent l'autorité).
 */
let nextMatric = 1
const player = (numero: number, capitaine: '-' | 'C' | 'E' = '-'): ScoringPlayer =>
  ({ matric: nextMatric++, numero, capitaine, team: 'A', nom: 'N', prenom: 'P' })

/** n joueurs numérotés 1..n, le premier capitaine. */
const roster = (n: number): ScoringPlayer[] =>
  Array.from({ length: n }, (_, i) => player(i + 1, i === 0 ? 'C' : '-'))

const OFFICIALS = { arbitrePrincipal: 'A1', arbitreSecondaire: 'A2', secretaire: 'S', chronometre: 'C' }

const codes = (issues: ReturnType<typeof matchReadiness>) => issues.map(i => `${i.code}${i.team ? ':' + i.team : ''}`)

describe('matchReadiness — feuille prête', () => {
  it('ne signale rien pour deux équipes complètes et des officiels renseignés', () => {
    expect(matchReadiness({ playersA: roster(6), playersB: roster(5), officials: OFFICIALS })).toEqual([])
  })
})

describe('matchReadiness — effectifs', () => {
  it(`signale moins de ${ROSTER_LIMITS.minPlayers} joueurs`, () => {
    expect(codes(matchReadiness({ playersA: roster(4), playersB: roster(5), officials: OFFICIALS })))
      .toEqual(['too_few_players:A'])
  })

  it(`signale plus de ${ROSTER_LIMITS.maxPlayers} joueurs`, () => {
    expect(codes(matchReadiness({ playersA: roster(5), playersB: roster(9), officials: OFFICIALS })))
      .toEqual(['too_many_players:B'])
  })

  it(`accepte exactement ${ROSTER_LIMITS.maxPlayers} joueurs`, () => {
    expect(matchReadiness({ playersA: roster(8), playersB: roster(5), officials: OFFICIALS })).toEqual([])
  })

  it('ne compte pas les entraîneurs comme joueurs', () => {
    const a = [...roster(4), player(0, 'E')]
    expect(codes(matchReadiness({ playersA: a, playersB: roster(5), officials: OFFICIALS })))
      .toEqual(['too_few_players:A'])
  })

  it(`signale plus de ${ROSTER_LIMITS.maxStaff} membres d’encadrement`, () => {
    const a = [...roster(5), player(0, 'E'), player(0, 'E'), player(0, 'E'), player(0, 'E')]
    const issues = matchReadiness({ playersA: a, playersB: roster(5), officials: OFFICIALS })
    expect(codes(issues)).toEqual(['too_many_staff:A'])
    expect(issues[0]?.count).toBe(4)
  })

  it('signale une équipe vide comme effectif insuffisant', () => {
    expect(codes(matchReadiness({ playersA: [], playersB: roster(5), officials: OFFICIALS })))
      .toContain('too_few_players:A')
  })
})

describe('matchReadiness — numéros et capitaine', () => {
  it('signale les numéros de maillot en double, sans répétition', () => {
    const b = [...roster(5), player(3), player(3), player(4)]
    const issues = matchReadiness({ playersA: roster(5), playersB: b, officials: OFFICIALS })
    expect(codes(issues)).toEqual(['duplicate_numbers:B'])
    expect(issues[0]?.numbers).toEqual([3, 4])
  })

  it('ignore le numéro des entraîneurs pour les doublons', () => {
    const a = [...roster(5), player(0, 'E'), player(0, 'E')]
    expect(matchReadiness({ playersA: a, playersB: roster(5), officials: OFFICIALS })).toEqual([])
  })

  it('signale une équipe sans capitaine', () => {
    const a = roster(5).map(p => ({ ...p, capitaine: '-' as const }))
    expect(codes(matchReadiness({ playersA: a, playersB: roster(5), officials: OFFICIALS })))
      .toEqual(['no_captain:A'])
  })
})

describe('matchReadiness — officiels', () => {
  it('liste les officiels obligatoires manquants (arbitres, secrétaire, chronométreur)', () => {
    const issues = matchReadiness({
      playersA: roster(5),
      playersB: roster(5),
      officials: { ...OFFICIALS, arbitreSecondaire: '', chronometre: null }
    })
    expect(codes(issues)).toEqual(['missing_officials'])
    expect(issues[0]?.roles).toEqual(['arbitreSecondaire', 'chronometre'])
  })

  it('considère un nom fait d’espaces comme manquant', () => {
    const issues = matchReadiness({ playersA: roster(5), playersB: roster(5), officials: { ...OFFICIALS, secretaire: '  ' } })
    expect(issues[0]?.roles).toEqual(['secretaire'])
  })
})
