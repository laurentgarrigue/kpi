import { describe, expect, it } from 'vitest'
import { canShowSchemaView, gamedaysViewQuery, parseGamedaysView } from '../../utils/gamedaysView'

describe('gamedays view', () => {
  it('GDY-SCH-05: the view comes from the URL, any unknown value shows the list', () => {
    expect(parseGamedaysView('schema')).toBe('schema')
    expect(parseGamedaysView('list')).toBe('list')
    expect(parseGamedaysView(undefined)).toBe('list')
    expect(parseGamedaysView('pitches')).toBe('list')
    expect(parseGamedaysView(['schema'])).toBe('list')
  })

  it('GDY-SCH-05: the list view leaves no `view` in the URL, other query params are kept', () => {
    expect(gamedaysViewQuery({ foo: 'bar' }, 'schema')).toEqual({ foo: 'bar', view: 'schema' })
    expect(gamedaysViewQuery({ foo: 'bar', view: 'schema' }, 'list')).toEqual({ foo: 'bar' })
  })

  it('GDY-SCH-01: the schema view needs one competition selected', () => {
    expect(canShowSchemaView('N1H')).toBe(true)
    expect(canShowSchemaView('')).toBe(false)
  })
})
