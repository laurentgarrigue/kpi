import { describe, expect, it } from 'vitest'
import { keyboardState, keyboardFrameShown, keyboardKeysActive } from '../../utils/scoringKeyboard'

/**
 * Le clavier répond-il ? (PAGE_SCORING.md §0.14) — état calculé pour le liseré, les touches
 * affichées sur les boutons et l'icône de l'entête.
 */
const ready = { windowFocused: true, fieldFocused: false, enabled: true }

describe('keyboardState — pourquoi les raccourcis répondent ou non', () => {
  it('est prêt quand la fenêtre a le focus, hors champ, raccourcis actifs', () => {
    expect(keyboardState(ready)).toBe('ready')
  })

  it('signale la fenêtre sans focus avant tout le reste (rien ne répond au clavier)', () => {
    expect(keyboardState({ windowFocused: false, fieldFocused: true, enabled: false })).toBe('window')
  })

  it('signale des raccourcis coupés (modale, liste des motifs) avant le focus dans un champ', () => {
    expect(keyboardState({ ...ready, fieldFocused: true, enabled: false })).toBe('off')
  })

  it('signale le focus dans un champ de saisie', () => {
    expect(keyboardState({ ...ready, fieldFocused: true })).toBe('field')
  })
})

describe('keyboardFrameShown — liseré et pastille', () => {
  it('s’affiche quand la fenêtre a perdu le focus et qu’un clavier est probable', () => {
    expect(keyboardFrameShown('window', true)).toBe(true)
  })

  it('ne s’affiche pas sans clavier probable (tablette)', () => {
    expect(keyboardFrameShown('window', false)).toBe(false)
  })

  it('ne s’affiche ni pour un champ, ni pour des raccourcis coupés, ni quand tout répond', () => {
    for (const state of ['field', 'off', 'ready'] as const) expect(keyboardFrameShown(state, true)).toBe(false)
  })
})

describe('keyboardKeysActive — touches affichées sur les boutons', () => {
  it('ne sont actives que dans l’état prêt', () => {
    expect(keyboardKeysActive('ready')).toBe(true)
    for (const state of ['window', 'off', 'field'] as const) expect(keyboardKeysActive(state)).toBe(false)
  })
})
