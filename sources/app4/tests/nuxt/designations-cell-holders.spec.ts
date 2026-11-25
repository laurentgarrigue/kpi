// Component test (project `nuxt`): titulaires d'une case du suivi des Désignations (SUI-03, SUI-04).
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import CellHolders from '../../components/designations/CellHolders.vue'
import type { CoverageCell, CoverageHolder } from '../../utils/designations'
import { t } from './helpers'

const holder = (origin: CoverageHolder['origin'], warnings: string[] = []): CoverageHolder => ({ designationId: 1, matric: 5, name: 'DURAND Anne', club: 'C001', origin, warnings })
const cell = (status: CoverageCell['status'], holders: CoverageHolder[] = []): CoverageCell => ({ requirement: 'required', status, holders })

describe('CellHolders', () => {
  it('shows « not applicable » for a role that does not apply', async () => {
    const wrapper = await mountSuspended(CellHolders, { props: { cell: cell('not_applicable'), kind: 'gameday', canAdd: false } })
    expect(wrapper.text()).toContain(t('designations.not_applicable'))
  })

  it('shows an own holder with a remove button that emits the holder', async () => {
    const own = holder('JOURNEE')
    const wrapper = await mountSuspended(CellHolders, { props: { cell: cell('green', [own]), kind: 'gameday', canAdd: false } })
    expect(wrapper.text()).toContain('DURAND Anne (C001)')
    await wrapper.find('button').trigger('click')
    expect(wrapper.emitted('remove')?.[0]).toEqual([own])
  })

  it('marks an inherited holder with its origin and offers no remove button', async () => {
    const wrapper = await mountSuspended(CellHolders, { props: { cell: cell('green', [holder('COMPETITION')]), kind: 'gameday', canAdd: false } })
    expect(wrapper.text()).toContain(t('designations.origin.COMPETITION'))
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('hides the remove button when read-only', async () => {
    const wrapper = await mountSuspended(CellHolders, { props: { cell: cell('green', [holder('JOURNEE')]), kind: 'gameday', canAdd: false, readonly: true } })
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('flags a holder with warnings', async () => {
    const wrapper = await mountSuspended(CellHolders, { props: { cell: cell('orange', [holder('JOURNEE', ['no_email'])]), kind: 'gameday', canAdd: false } })
    expect(wrapper.html()).toContain(t('designations.warning.no_email'))
  })

  it('offers to add a holder, with a distinct label for a missing required role', async () => {
    const wrapper = await mountSuspended(CellHolders, { props: { cell: cell('red'), kind: 'gameday', canAdd: true } })
    const add = wrapper.find('button')
    expect(add.attributes('aria-label')).toBe(t('designations.missing_add'))
    await add.trigger('click')
    expect(wrapper.emitted('add')).toHaveLength(1)
    const optional = await mountSuspended(CellHolders, { props: { cell: cell('empty'), kind: 'gameday', canAdd: true } })
    expect(optional.find('button').attributes('aria-label')).toBe(t('designations.add.button'))
  })
})
