import { describe, expect, it } from 'vitest'
import { createClickGuard } from '../../utils/clickGuard'

/**
 * Anti-rebond des boutons de la console de scoring : un clic trop long ou trop appuyé
 * (souris fatiguée, double contact, écran tactile) ne doit compter qu'une fois.
 */
describe('createClickGuard', () => {
  const at = (...times: number[]) => {
    let i = 0
    return () => times[i++]!
  }

  it('accepte le premier clic', () => {
    const accept = createClickGuard(250, at(1000))
    expect(accept('clock+1')).toBe(true)
  })

  it('ignore un second clic du même bouton trop rapproché', () => {
    const accept = createClickGuard(250, at(1000, 1080))
    accept('clock+1')
    expect(accept('clock+1')).toBe(false)
  })

  it('accepte les clics volontaires espacés', () => {
    const accept = createClickGuard(250, at(1000, 1300, 1600))
    accept('clock+1')
    expect(accept('clock+1')).toBe(true)
    expect(accept('clock+1')).toBe(true)
  })

  it('compte depuis le dernier clic accepté, pas depuis le rebond ignoré', () => {
    const accept = createClickGuard(250, at(1000, 1200, 1260))
    accept('clock+1')
    accept('clock+1') // rebond ignoré à +200 ms
    expect(accept('clock+1')).toBe(true) // +260 ms après le clic accepté
  })

  it('traite chaque bouton indépendamment', () => {
    const accept = createClickGuard(250, at(1000, 1050))
    accept('clock+1')
    expect(accept('clock-1')).toBe(true)
  })
})
