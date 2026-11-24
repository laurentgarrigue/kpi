import { describe, expect, it } from 'vitest'
import { formatNom, formatPrenom } from '../../utils/nameFormat'

describe('name formatting', () => {
  it('upper-cases the family name', () => {
    expect(formatNom('Dupont')).toBe('DUPONT')
  })

  it('capitalises each word of the first name, hyphens and accents kept', () => {
    expect(formatPrenom('jean MARC')).toBe('Jean Marc')
    expect(formatPrenom('éloïse')).toBe('Éloïse')
    expect(formatPrenom('')).toBe('')
  })
})
