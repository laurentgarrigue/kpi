// PAGE_SCORING.md §7.1 — the status badge gives the kick-off (ATT → ON) in one click, nothing else.
import { describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import StatusBadge from '../../components/scoring/StatusBadge.vue'
import { t } from './helpers'


describe('ScoringStatusBadge (§7.1)', () => {
  it('shows the translated status', async () => {
    const wrapper = await mountSuspended(StatusBadge, { props: { status: 'ON' } })
    expect(wrapper.text()).toBe(t('scoring.status.ON'))
  })

  it('kicks off with a click, a button, only from ATT and with the right', async () => {
    const wrapper = await mountSuspended(StatusBadge, { props: { status: 'ATT', canKickoff: true } })
    expect(wrapper.attributes('role')).toBe('button')
    await wrapper.trigger('click')
    expect(wrapper.emitted('kickoff')).toHaveLength(1)
  })

  it('kicks off with Enter and Space as well', async () => {
    const wrapper = await mountSuspended(StatusBadge, { props: { status: 'ATT', canKickoff: true } })
    await wrapper.trigger('keydown.enter')
    await wrapper.trigger('keydown.space')
    expect(wrapper.emitted('kickoff')).toHaveLength(2)
  })

  it.each([
    ['without the right', { status: 'ATT' as const, canKickoff: false }],
    ['once the match is on', { status: 'ON' as const, canKickoff: true }],
    ['once the match is closed', { status: 'END' as const, canKickoff: true }],
  ])('is a read-only badge %s', async (_label, props) => {
    const wrapper = await mountSuspended(StatusBadge, { props })
    expect(wrapper.attributes('role')).toBeUndefined()
    await wrapper.trigger('click')
    expect(wrapper.emitted('kickoff')).toBeUndefined()
  })
})
