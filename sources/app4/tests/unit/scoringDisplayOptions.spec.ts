import { describe, expect, it } from 'vitest'
import { displayOptionsFromQuery, displayQuery, mergeStoredDisplayOptions, DISPLAY_DEFAULTS } from '../../utils/scoringDisplayOptions'

/**
 * Options des affichages plein écran (PAGE_SCORING.md §0.15) : choisies dans une fenêtre
 * avant l'ouverture, mémorisées sur le poste, transmises dans l'URL de l'affichage.
 */
describe('DISPLAY_DEFAULTS — sans option', () => {
  it('tableau de score : sombre, avec le chrono de tir', () => {
    expect(DISPLAY_DEFAULTS.scoreboard).toEqual({ theme: 'dark', shotclock: true })
  })

  it('chrono de tir : sombre, avec le chrono principal', () => {
    expect(DISPLAY_DEFAULTS.shotclock).toEqual({ theme: 'dark', clock: true })
  })
})

describe('displayQuery — options → paramètres d’URL', () => {
  it('n’écrit rien pour les valeurs par défaut', () => {
    expect(displayQuery('scoreboard', DISPLAY_DEFAULTS.scoreboard)).toEqual({})
  })

  it('écrit theme=light et clock=0 pour le chrono de tir', () => {
    expect(displayQuery('shotclock', { theme: 'light', clock: false })).toEqual({ theme: 'light', clock: '0' })
  })

  it('écrit shotclock=0 pour le tableau de score', () => {
    expect(displayQuery('scoreboard', { theme: 'dark', shotclock: false })).toEqual({ shotclock: '0' })
  })
})

describe('displayOptionsFromQuery — paramètres d’URL → options (pages d’affichage)', () => {
  it('relit ce que displayQuery a écrit', () => {
    const options = { theme: 'light', clock: false } as const
    expect(displayOptionsFromQuery('shotclock', displayQuery('shotclock', options))).toEqual(options)
  })

  it('garde les défauts pour une URL nue ou des valeurs inconnues', () => {
    expect(displayOptionsFromQuery('scoreboard', { theme: 'pink', shotclock: 'x' })).toEqual(DISPLAY_DEFAULTS.scoreboard)
  })
})

describe('mergeStoredDisplayOptions — préférence du poste', () => {
  it('reprend les valeurs valides et complète avec les défauts', () => {
    expect(mergeStoredDisplayOptions('scoreboard', { theme: 'light' })).toEqual({ theme: 'light', shotclock: true })
  })

  it('ignore les valeurs invalides et les clés inconnues', () => {
    expect(mergeStoredDisplayOptions('shotclock', { theme: 'pink', clock: 'no', extra: 1 })).toEqual(DISPLAY_DEFAULTS.shotclock)
  })
})
