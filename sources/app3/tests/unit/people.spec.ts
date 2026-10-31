import { describe, expect, it } from 'vitest'
import { personName } from '../../app/utils/people'

describe('personName', () => {
  it('drops the licence number or club stored between parentheses', () => {
    expect(personName('DUPONT Jean (123456)')).toBe('DUPONT Jean')
    expect(personName('DUPONT Jean')).toBe('DUPONT Jean')
    expect(personName('')).toBeNull()
    expect(personName(null)).toBeNull()
  })

  it('treats the « -1 » placeholder (no referee) as nobody', () => {
    expect(personName('-1')).toBeNull()
    expect(personName(' -1 ')).toBeNull()
    expect(personName('Acigné II')).toBe('Acigné II')
  })

  it('decodes the HTML entities stored by the legacy (« FLOC&#039;H »)', () => {
    expect(personName('FLOC&#039;H Titouan (Acigné) INT')).toBe("FLOC'H Titouan")
    expect(personName('A &amp; B')).toBe('A & B')
  })
})
