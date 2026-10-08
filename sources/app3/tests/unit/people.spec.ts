import { describe, expect, it } from 'vitest'
import { personName } from '../../app/utils/people'

describe('personName', () => {
  it('drops the licence number or club stored between parentheses', () => {
    expect(personName('DUPONT Jean (123456)')).toBe('DUPONT Jean')
    expect(personName('DUPONT Jean')).toBe('DUPONT Jean')
    expect(personName('')).toBeNull()
    expect(personName(null)).toBeNull()
  })
})
