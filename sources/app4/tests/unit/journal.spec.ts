import { describe, expect, it } from 'vitest'
import { actionGroups, categoriesParam, defaultCategoryKeys, type JournalCategory } from '../../utils/journal'

const categories: JournalCategory[] = [
  { key: 'connexion', defaultSelected: true, actions: ['Connexion', 'Connexion Admin'] },
  { key: 'operation', defaultSelected: true, actions: ['Ajout match'] },
  { key: 'scoring', defaultSelected: false, actions: ['Scoring score'] },
  { key: 'pdf', defaultSelected: true, actions: [] },
]

describe('journal categories', () => {
  it('JRN-CAT-03: every category but scoring is checked by default', () => {
    expect(defaultCategoryKeys(categories)).toEqual(['connexion', 'operation', 'pdf'])
  })

  it('JRN-CAT-05: the checked categories are sent in display order', () => {
    expect(categoriesParam(['pdf', 'connexion'], categories)).toBe('connexion,pdf')
  })

  it('JRN-CAT-05: no filter is sent when none or every category is checked', () => {
    expect(categoriesParam([], categories)).toBeUndefined()
    expect(categoriesParam(['connexion', 'operation', 'scoring', 'pdf'], categories)).toBeUndefined()
  })

  it('JRN-CAT-07: the action select groups the actions of the checked categories, skipping empty ones', () => {
    expect(actionGroups(categories, ['connexion', 'pdf']).map(c => c.key)).toEqual(['connexion'])
    expect(actionGroups(categories, []).map(c => c.key)).toEqual(['connexion', 'operation', 'scoring'])
  })
})
