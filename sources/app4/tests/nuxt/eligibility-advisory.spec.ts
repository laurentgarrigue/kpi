// Règle 2027 des compétitions nationales : la pagaie est signalée (ambre) sans bloquer (rouge).
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import EligibilityBadge from '../../components/admin/EligibilityBadge.vue'
import PresenceComplianceCell from '../../components/admin/PresenceComplianceCell.vue'
import type { Player } from '../../types/presence'

const PAGAIE = 'Pagaie_couleur'

describe('EligibilityBadge: advisory errors', () => {
  const iconClasses = async (props: Record<string, unknown>) =>
    (await mountSuspended(EligibilityBadge, { props: { enforcement: 'block', errors: [PAGAIE], ...props } })).html()

  it('is red when a blocking error remains', async () => {
    expect(await iconClasses({})).toContain('text-danger-600')
  })

  it('is amber when every error is advisory', async () => {
    const html = await iconClasses({ advisoryErrors: [PAGAIE] })
    expect(html).toContain('text-amber-700')
    expect(html).not.toContain('text-danger-600')
  })
})

describe('PresenceComplianceCell: advisory paddle', () => {
  const player = { matric: 1, eligibilityErrors: [PAGAIE], pagaieLabel: 'Jaune' } as unknown as Player
  const html = async (advisoryErrors?: string[]) =>
    (await mountSuspended(PresenceComplianceCell, {
      props: { player, field: 'pagaie', season: '2027', enforcement: 'block', advisoryErrors },
    })).html()

  it('shows a red pill for a blocking paddle', async () => {
    expect(await html()).toContain('bg-danger-600')
  })

  it('shows an amber pill for an advisory paddle', async () => {
    const out = await html([PAGAIE])
    expect(out).toContain('bg-amber-500')
    expect(out).not.toContain('bg-danger-600')
  })
})
