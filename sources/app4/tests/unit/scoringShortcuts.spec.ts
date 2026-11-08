import { describe, expect, it } from 'vitest'
import { SHORTCUT_ACTIONS, mergeStoredBindings, shortcutDefaults, factCodeOfAction } from '../../utils/scoringShortcuts'

/**
 * Raccourcis clavier de la console (PAGE_SCORING.md §6.5, §0.12) : les faits de jeu ont
 * une touche par défaut qui dépend de la langue de l'interface, toujours réassignable.
 */
describe('shortcutDefaults — touches par défaut selon la langue', () => {
  it('propose B V J R N en français (But, Vert, Jaune, Rouge, Noir)', () => {
    const d = shortcutDefaults('fr')
    expect([d.factGoal, d.factGreen, d.factYellow, d.factRed, d.factBlack]).toEqual(['b', 'v', 'j', 'r', 'n'])
  })

  it('propose G E Y R K en anglais (Goal, grEen, Yellow, Red, blacK)', () => {
    const d = shortcutDefaults('en')
    expect([d.factGoal, d.factGreen, d.factYellow, d.factRed, d.factBlack]).toEqual(['g', 'e', 'y', 'r', 'k'])
  })

  it('retombe sur le français pour une langue inconnue', () => {
    expect(shortcutDefaults('de')).toEqual(shortcutDefaults('fr'))
  })

  it('garde les touches du chrono identiques dans toutes les langues', () => {
    const fr = shortcutDefaults('fr')
    const en = shortcutDefaults('en')
    expect(en.gameClockToggle).toBe(fr.gameClockToggle)
    expect(en.shotclockStop).toBe(fr.shotclockStop)
  })

  it('pilote le chrono de tir par + (60 s), - (40 s) et * (arrêt)', () => {
    const d = shortcutDefaults('fr')
    expect([d.shotclockStart60, d.shotclockStart40, d.shotclockStop]).toEqual(['+', '-', '*'])
  })

  it('ne lie jamais Entrée, un chiffre ou le point au chrono de tir (saisie des faits et du n° du joueur)', () => {
    for (const locale of ['fr', 'en']) {
      const d = shortcutDefaults(locale)
      for (const k of [d.gameClockToggle, d.shotclockStart60, d.shotclockStart40, d.shotclockStop]) {
        expect(k).not.toMatch(/^(Enter|\d|\.)$/)
      }
    }
  })

  it('n’a plus de raccourci de réglage ±1 s du chrono de tir (réglage au clic sur le décompte)', () => {
    expect(SHORTCUT_ACTIONS).not.toContain('shotclockPlus')
    expect(SHORTCUT_ACTIONS).not.toContain('shotclockMinus')
  })

  it('n’attribue jamais la même touche à deux actions', () => {
    for (const locale of ['fr', 'en']) {
      const keys = Object.values(shortcutDefaults(locale)).filter(k => k !== '')
      expect(new Set(keys).size).toBe(keys.length)
    }
  })

  it('n’utilise aucun chiffre pour un fait (les chiffres servent au n° du joueur)', () => {
    for (const locale of ['fr', 'en']) {
      const d = shortcutDefaults(locale)
      for (const k of [d.factGoal, d.factGreen, d.factYellow, d.factRed, d.factBlack]) expect(k).not.toMatch(/\d/)
    }
  })
})

describe('mergeStoredBindings — préférences enregistrées sur le poste', () => {
  it('complète les actions manquantes avec les défauts de la langue', () => {
    const merged = mergeStoredBindings({ gameClockToggle: 'p' }, 'en')
    expect(merged.gameClockToggle).toBe('p')
    expect(merged.factGoal).toBe('g')
  })

  it('ignore les actions qui n’existent plus (« joueur par numéro », chrono de tir ±1 s)', () => {
    const merged = mergeStoredBindings({ playerByNumber: 'n', shotclockPlus: '+', shotclockMinus: '-' }, 'fr')
    expect(Object.keys(merged).sort()).toEqual([...SHORTCUT_ACTIONS].sort())
  })

  it('ignore une valeur qui n’est pas une chaîne', () => {
    expect(mergeStoredBindings({ factRed: 42 }, 'fr').factRed).toBe('r')
  })
})

describe('factCodeOfAction — action clavier → code du fait', () => {
  it('associe chaque action de fait à son code', () => {
    expect(['factGoal', 'factGreen', 'factYellow', 'factRed', 'factBlack'].map(a => factCodeOfAction(a)))
      .toEqual(['B', 'V', 'J', 'R', 'D'])
  })

  it('renvoie null pour une action qui n’est pas un fait', () => {
    expect(factCodeOfAction('gameClockToggle')).toBeNull()
  })
})
