// Rapports officiels (FEATURE_DESIGNATIONS.md RAP-01) : formulaires rendus depuis le catalogue de api2.
import { describe, expect, it } from 'vitest'
import { answersToSend, computedValue, localized, missingRequired, typesForScope, type ReportTypeDefinition, type ReportTypes } from '../../utils/reports'

const definition: ReportTypeDefinition = {
  code: 'EVAL', version: 2, label: { fr: 'Évaluation', en: 'Evaluation' }, scopes: ['MATCH'], recipients: [], deadlineDays: null, restricted: true,
  sections: [{ key: 'notes', title: { fr: 'Notes', en: 'Marks' }, fields: [
    { key: 'a', type: 'score', label: { fr: 'A', en: 'A' }, max: 10, required: true },
    { key: 'b', type: 'score', label: { fr: 'B', en: 'B' }, max: 10 },
    { key: 'total', type: 'sum', label: { fr: 'Total', en: 'Total' }, max: 20, sumOf: ['a', 'b'] },
  ] }],
}
const types: ReportTypes = {
  EVAL: { current: 2, versions: { 1: { ...definition, version: 1, scopes: ['JOURNEE'] }, 2: definition } },
  INCIDENT: { current: 1, versions: { 1: { ...definition, code: 'INCIDENT', version: 1, scopes: ['MATCH', 'JOURNEE'] } } },
}

describe('rapports officiels', () => {
  it('propose les types rédigeables sur le périmètre, en version courante', () => {
    expect(typesForScope(types, 'MATCH').map(type => `${type.code} v${type.version}`)).toEqual(['EVAL v2', 'INCIDENT v1'])
    expect(typesForScope(types, 'JOURNEE').map(type => type.code)).toEqual(['INCIDENT'])
  })

  it('RAP-08 : recalcule une somme sans la saisir', () => {
    expect(computedValue(definition.sections[0].fields[2], { a: 7, b: 6 })).toBe(13)
    expect(computedValue(definition.sections[0].fields[2], { a: 7 })).toBe(7)
  })

  it('n\'envoie ni les champs calculés ni les valeurs vides', () => {
    expect(answersToSend(definition, { a: 7, b: '', total: 99, other: 1 })).toEqual({ a: 7 })
  })

  it('signale les champs obligatoires vides avant soumission', () => {
    expect(missingRequired(definition, {})).toEqual(['a'])
    expect(missingRequired(definition, { a: 0 })).toEqual([])
  })

  it('traduit les libellés', () => {
    expect(localized({ fr: 'Faits', en: 'Facts' }, 'en')).toBe('Facts')
  })
})
