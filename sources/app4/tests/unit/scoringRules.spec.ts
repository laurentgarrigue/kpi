import { describe, expect, it } from 'vitest'
import {
  breakDurationBefore,
  cardCreatesPenaltyClock,
  freePenaltySlot,
  goalEndsMatch,
  isOvertime,
  nextPeriodFor,
  normalizeGameTime,
  overtimeIndex,
  penaltyLiftableOnGoal,
  penaltySlotToLift,
  periodDurationOf,
  playerReturnsAfterPenalty,
  shotclockTransition,
  validateCardProgression
} from '../../utils/scoringRules'
import type { BreakDurations, PeriodDurations } from '../../types/scoring'

/**
 * Miroir TypeScript de `sources/api2/tests/Unit/Scoring/ScoringRulesTest.php`.
 *
 * Les règles de jeu existent **deux fois** (api2 = autorité, app4 = retour immédiat dans
 * la console, y compris hors ligne). Cette duplication est assumée et tracée au plan
 * (§4.13) : ces deux suites sont ce qui garantit qu'elles ne divergent pas. **Tout cas
 * ajouté d'un côté doit l'être de l'autre**, avec le même libellé métier.
 *
 * Référence fonctionnelle : `DOC/specs/PAGE_SCORING.md` §0.6, §0.9, §6.5, §7.4, §7.5.
 */

const DURATIONS: PeriodDurations = { M1: 600, M2: 600, P: 300, TB: 180 }
const BREAKS: BreakDurations = { halftime: 180, beforeOvertime: 180, betweenOvertimes: 60 }

describe('périodes et prolongations non bornées (§0.6)', () => {
  it.each([
    ['P1', true, 1],
    ['P12', true, 12],
    ['M1', false, null],
    ['TB', false, null]
  ])('%s : prolongation = %s, index = %s', (period, expected, index) => {
    expect(isOvertime(period)).toBe(expected)
    expect(overtimeIndex(period)).toBe(index)
  })

  it.each([
    ['C', 'M1', true, 'M2'],
    ['C', 'M2', true, null],
    ['E', 'M1', false, 'M2'],
    ['E', 'M2', false, null],
    ['E', 'M2', true, 'P1'],
    ['E', 'P1', true, 'P2'],
    ['E', 'P9', true, 'P10'],
    ['E', 'P1', false, null],
    ['E', 'TB', true, null]
  ] as const)('type %s, %s, égalité=%s → %s', (type, period, scoreLevel, expected) => {
    expect(nextPeriodFor(type, period, scoreLevel)).toBe(expected)
  })

  it('ne propose jamais les tirs au but : TB est un choix manuel, pas un enchaînement', () => {
    // §7.5 : le but en or est la règle ; TB reste une sortie alternative que l'opérateur
    // choisit explicitement (bouton dédié dans PeriodSelector), jamais la suite calculée.
    expect(nextPeriodFor('E', 'M2', true)).toBe('P1')
    expect(nextPeriodFor('E', 'P3', true)).toBe('P4')
  })

  it.each([
    ['E', 'P3', true],
    ['E', 'M2', false],
    ['C', 'P1', false]
  ] as const)('but en or : type %s en %s → fin de match = %s', (type, period, expected) => {
    expect(goalEndsMatch(type, period)).toBe(expected)
  })

  it('toutes les prolongations partagent la même durée de 5 min (ICF et FFCK, §0.9)', () => {
    expect(periodDurationOf('M1', DURATIONS)).toBe(600)
    expect(periodDurationOf('M2', DURATIONS)).toBe(600)
    expect(periodDurationOf('P1', DURATIONS)).toBe(300)
    expect(periodDurationOf('P7', DURATIONS)).toBe(300)
    expect(periodDurationOf('TB', DURATIONS)).toBe(180)
  })
})

describe('pauses inter-périodes (§7.5 — 3\'/3\'/1\')', () => {
  it.each([
    ['M2', 180],
    ['P1', 180],
    ['P2', 60],
    ['P5', 60],
    ['M1', null],
    ['TB', null]
  ] as const)('avant %s → %s s', (nextPeriod, expected) => {
    expect(breakDurationBefore(nextPeriod, BREAKS)).toBe(expected)
  })

  it('les durées de pause sont surchargeables par la configuration de match', () => {
    const custom: BreakDurations = { halftime: 300, beforeOvertime: 120, betweenOvertimes: 30 }
    expect(breakDurationBefore('M2', custom)).toBe(300)
    expect(breakDurationBefore('P1', custom)).toBe(120)
    expect(breakDurationBefore('P4', custom)).toBe(30)
  })
})

describe('progression des cartons (§7.4, règlement 2027)', () => {
  it.each([
    [[], 'V', true],
    [[], 'J', true],
    [[], 'R', true],
    [['J'], 'J', 'card_not_higher'],
    [['J'], 'V', 'card_not_higher'],
    [['V'], 'J', true],
    [['V'], 'R', true],
    [['J', 'R'], 'D', 'player_already_out'],
    [['D'], 'V', 'player_already_out'],
    [['V', 'J'], 'D', true],
    [[], 'X', 'unknown_card']
  ] as const)('antécédents %j + %s → %s', (previous, newCard, expected) => {
    expect(validateCardProgression([...previous], newCard)).toBe(expected)
  })
})

describe('conséquences d\'un carton (§7.4, correction 2026-07-29)', () => {
  // [code, chrono de pénalité, levable sur but encaissé, le joueur lui-même revient]
  it.each([
    ['V', true, true, true],
    ['J', true, true, true],
    ['R', true, false, false],
    ['D', false, false, false]
  ] as const)('%s : chrono=%s, levable=%s, retour du joueur=%s', (code, clock, liftable, returns) => {
    expect(cardCreatesPenaltyClock(code)).toBe(clock)
    expect(penaltyLiftableOnGoal(code)).toBe(liftable)
    expect(playerReturnsAfterPenalty(code)).toBe(returns)
  })

  it('le carton noir est une exclusion sèche : aucun chrono, aucun remplacement', () => {
    // Verrouille la correction réglementaire du 2026-07-29 : pas de 2 minutes, l'équipe
    // termine le match en infériorité.
    expect(cardCreatesPenaltyClock('D')).toBe(false)
    expect(playerReturnsAfterPenalty('D')).toBe(false)
  })

  it('le carton rouge purge ses 2 minutes pleines, buts encaissés ou non', () => {
    expect(penaltyLiftableOnGoal('R')).toBe(false)
    expect(playerReturnsAfterPenalty('R')).toBe(false)
  })
})

describe('emplacements de pénalité (§7.4 — 2 au maximum par équipe)', () => {
  it.each([
    [[], 1],
    [[1], 2],
    [[2], 1],
    [[1, 2], null]
  ] as const)('occupés %j → libre %s', (busy, expected) => {
    expect(freePenaltySlot([...busy])).toBe(expected)
  })

  it('sur but encaissé, c\'est la plus ancienne des pénalités LEVABLES qui saute', () => {
    expect(penaltySlotToLift([
      { slot: 1, startedAt: '2026-07-27 15:04:10.000', cardCode: 'V' },
      { slot: 2, startedAt: '2026-07-27 15:02:00.000', cardCode: 'J' }
    ])).toBe(2)

    expect(penaltySlotToLift([
      { slot: 1, startedAt: '2026-07-27 15:04:10.000', cardCode: 'V' }
    ])).toBe(1)

    // Un rouge n'est jamais levé par anticipation.
    expect(penaltySlotToLift([
      { slot: 1, startedAt: '2026-07-27 15:02:00.000', cardCode: 'R' }
    ])).toBeNull()

    // Rouge plus ancien : c'est le jaune, plus récent mais levable, qui saute.
    expect(penaltySlotToLift([
      { slot: 1, startedAt: '2026-07-27 15:02:00.000', cardCode: 'R' },
      { slot: 2, startedAt: '2026-07-27 15:04:10.000', cardCode: 'J' }
    ])).toBe(2)

    expect(penaltySlotToLift([])).toBeNull()
  })
})

describe('chronomètre de tir (§6.5) — trois commandes, trois états', () => {
  it.each([
    ['IDLE', 'start60', 'RUNNING'],
    ['IDLE', 'start40', 'RUNNING'],
    ['RUNNING', 'start60', 'RUNNING'],
    ['RUNNING', 'stop', 'IDLE'],
    ['RUNNING', 'gameClockStopped', 'SUSPENDED'],
    ['IDLE', 'gameClockStopped', 'IDLE'],
    ['SUSPENDED', 'gameClockStarted', 'RUNNING'],
    ['IDLE', 'gameClockStarted', 'IDLE'],
    ['SUSPENDED', 'stop', 'IDLE'],
    ['SUSPENDED', 'start40', 'RUNNING']
  ] as const)('%s + %s → %s', (state, command, expected) => {
    expect(shotclockTransition(state, command)).toBe(expected)
  })

  it('il n\'existe pas de commande « pause »', () => {
    // Modèle envisagé le 2026-07-23, remplacé le 2026-07-27 : le départ EST un reset et
    // l'arrêt ramène à « -- ». Ce test verrouille la décision des deux côtés (le pendant
    // PHP lève la même exception), y compris sur une commande venue du réseau.
    expect(() => shotclockTransition('IDLE', 'pause' as never)).toThrow(/pause/)
  })
})

describe('saisie de l\'heure de jeu (MM:SS)', () => {
  it.each([
    ['750', '07:50'],
    ['0800', '08:00'],
    ['139', '01:39'],
    ['12', '00:12'],
    ['', '00:00'],
    ['9:9', '01:39'],
    ['09:09', '09:09'],
    ['0170', '02:10']
  ] as const)('« %s » → %s', (input, expected) => {
    expect(normalizeGameTime(input)).toBe(expected)
  })
})
