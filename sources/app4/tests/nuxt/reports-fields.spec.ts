// Component tests (project `nuxt`): champs et tableaux des rapports officiels, rendus depuis le catalogue (RAP-01).
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import Field from '../../components/reports/Field.vue'
import TableField from '../../components/reports/TableField.vue'
import type { ReportFieldDefinition } from '../../utils/reports'

const field = (type: ReportFieldDefinition['type'], extra: Partial<ReportFieldDefinition> = {}): ReportFieldDefinition => ({ key: 'k', type, label: { fr: 'Libellé', en: 'Label' }, ...extra })
const mountField = (definition: ReportFieldDefinition, answers: Record<string, unknown> = {}, extra: { disabled?: boolean, error?: string } = {}) =>
  mountSuspended(Field, { props: { field: definition, answers, disabled: false, ...extra } })

describe('Field', () => {
  it('renders a text input with its label, required mark and current value', async () => {
    const wrapper = await mountField(field('text', { required: true }), { k: 'abc' })
    expect(wrapper.text()).toContain('Label')
    expect(wrapper.text()).toContain('*')
    expect((wrapper.find('input').element as HTMLInputElement).value).toBe('abc')
  })

  it('emits the typed value of a text input and of a textarea', async () => {
    const text = await mountField(field('date'))
    await text.find('input').setValue('2026-11-25')
    expect(text.emitted('update')?.[0]).toEqual(['k', '2026-11-25'])
    const area = await mountField(field('textarea'))
    await area.find('textarea').setValue('long texte')
    expect(area.emitted('update')?.[0]).toEqual(['k', 'long texte'])
  })

  it('emits the chosen option of a select', async () => {
    const wrapper = await mountField(field('select', { options: ['a', 'b'] }))
    await wrapper.find('select').setValue('b')
    expect(wrapper.emitted('update')?.[0]).toEqual(['k', 'b'])
  })

  it('emits a boolean for a checkbox, and shows its label next to it', async () => {
    const wrapper = await mountField(field('checkbox'), { k: false })
    expect(wrapper.text()).toContain('Label')
    await wrapper.find('input').setValue(true)
    expect(wrapper.emitted('update')?.[0]).toEqual(['k', true])
  })

  it('emits a number, or null when the number input is emptied', async () => {
    const wrapper = await mountField(field('score', { max: 5 }), { k: 3 })
    expect(wrapper.text()).toContain('/ 5')
    await wrapper.find('input').setValue('4')
    expect(wrapper.emitted('update')?.[0]).toEqual(['k', 4])
    await wrapper.find('input').setValue('')
    expect(wrapper.emitted('update')?.[1]).toEqual(['k', null])
  })

  it('shows the computed total of a sum field', async () => {
    const wrapper = await mountField(field('sum', { max: 10, sumOf: ['a', 'b'] }), { a: 2, b: 3 })
    expect(wrapper.text()).toContain('5 / 10')
  })

  it('disables the input and shows its error', async () => {
    const wrapper = await mountField(field('text'), {}, { disabled: true, error: 'required' })
    expect(wrapper.find('input').attributes('disabled')).toBeDefined()
    expect(wrapper.find('input').attributes('aria-invalid')).toBe('true')
    expect(wrapper.find('#report-field-k-error').exists()).toBe(true)
  })
})

describe('TableField', () => {
  const table = (extra: Partial<ReportFieldDefinition> = {}) => field('table', {
    columns: [
      { key: 'name', type: 'text', label: { fr: 'Nom', en: 'Name' }, readonly: true },
      { key: 'note', type: 'text', label: { fr: 'Note', en: 'Note' } },
      { key: 'kind', type: 'select', label: { fr: 'Type', en: 'Kind' }, options: ['x', 'y'] },
      { key: 'ok', type: 'checkbox', label: { fr: 'OK', en: 'OK' } },
    ],
    ...extra,
  })
  const mountTable = (definition: ReportFieldDefinition, rows: Record<string, unknown>[], disabled = false) =>
    mountSuspended(TableField, { props: { field: definition, rows, disabled } })

  it('shows the empty message and allows adding a row to a free table', async () => {
    const wrapper = await mountTable(table(), [])
    expect(wrapper.text()).toContain('Name')
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('update')?.[0]).toEqual([[{}]])
  })

  it('edits one cell without touching the other rows', async () => {
    const wrapper = await mountTable(table(), [{ name: 'A' }, { name: 'B' }])
    await wrapper.findAll('tbody tr')[1].find('input[type="text"]').setValue('vu')
    expect(wrapper.emitted('update')?.[0]).toEqual([[{ name: 'A' }, { name: 'B', note: 'vu' }]])
  })

  it('edits a select cell (empty = null) and a checkbox cell', async () => {
    const wrapper = await mountTable(table(), [{ name: 'A' }])
    await wrapper.find('select').setValue('y')
    expect(wrapper.emitted('update')?.[0]).toEqual([[{ name: 'A', kind: 'y' }]])
    await wrapper.find('input[type="checkbox"]').setValue(true)
    expect(wrapper.emitted('update')?.[1]).toEqual([[{ name: 'A', ok: true }]])
  })

  it('removes a row of a free table', async () => {
    const wrapper = await mountTable(table(), [{ name: 'A' }, { name: 'B' }])
    await wrapper.findAll('tbody tr')[0].find('button').trigger('click')
    expect(wrapper.emitted('update')?.[0]).toEqual([[{ name: 'B' }]])
  })

  it('offers neither adding nor removing in a pre-filled table, nor when disabled', async () => {
    const prefilled = await mountTable(table({ rowsFrom: 'officials' }), [{ name: 'A' }])
    expect(prefilled.find('button').exists()).toBe(false)
    const disabled = await mountTable(table(), [{ name: 'A' }], true)
    expect(disabled.find('button').exists()).toBe(false)
  })

  it('shows its error', async () => {
    const wrapper = await mountSuspended(TableField, { props: { field: table(), rows: [], disabled: false, error: 'required' } })
    expect(wrapper.find('p.text-red-700, p[class*="text-red"]').exists()).toBe(true)
  })
})
