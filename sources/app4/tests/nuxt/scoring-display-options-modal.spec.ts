// PAGE_SCORING.md §0.15 — options of a full-screen display, asked before the popup opens, remembered per device.
import { beforeEach, describe, expect, it } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { nextTick } from 'vue'
import DisplayOptionsModal from '../../components/scoring/DisplayOptionsModal.vue'
import type { DisplayKind } from '../../utils/scoringDisplayOptions'
import { bodyButton, track } from './modal-helpers'
import { t } from './helpers'

const mountFor = async (kind: DisplayKind | null) =>
  track(await mountSuspended(DisplayOptionsModal, { props: { kind } }))

const themeButton = (theme: 'dark' | 'light') => bodyButton(t(`scoring.display_options.theme_${theme}`))!
const submit = () => bodyButton(t('scoring.display_options.open'))!
const switchEl = () => document.body.querySelector<HTMLElement>('[role="switch"]')!

beforeEach(() => window.localStorage.clear())

describe('ScoringDisplayOptionsModal', () => {
  it('renders nothing when no display is being opened', async () => {
    await mountFor(null)
    expect(document.body.textContent).not.toContain(t('scoring.display_options.hint'))
  })

  it('has the title and the switches of the chosen display', async () => {
    await mountFor('scoreboard')
    expect(document.body.textContent).toContain(t('scoring.display_options.title_scoreboard'))
    expect(document.body.textContent).toContain(t('scoring.display_options.scoreboard_shotclock'))

    await mountFor('shotclock')
    expect(document.body.textContent).toContain(t('scoring.display_options.shotclock_clock'))
  })

  it('opens with the default options the first time', async () => {
    const wrapper = await mountFor('scoreboard')
    submit().click()
    expect(wrapper.emitted('open')).toEqual([['scoreboard', { theme: 'dark', shotclock: true }]])
  })

  it('shows the dark theme as the pressed one by default', async () => {
    await mountFor('scoreboard')
    expect(themeButton('dark').getAttribute('aria-pressed')).toBe('true')
    expect(themeButton('light').getAttribute('aria-pressed')).toBe('false')
  })

  it('opens with the options changed in the dialog', async () => {
    const wrapper = await mountFor('scoreboard')
    themeButton('light').click()
    await nextTick()
    switchEl().click()
    await nextTick()
    submit().click()
    expect(wrapper.emitted('open')).toEqual([['scoreboard', { theme: 'light', shotclock: false }]])
  })

  it('remembers the last choice on the device, per display', async () => {
    const first = await mountFor('scoreboard')
    themeButton('light').click()
    await nextTick()
    submit().click()
    first.unmount()

    expect(JSON.parse(window.localStorage.getItem('kpi.scoring.display.scoreboard')!)).toMatchObject({ theme: 'light' })
    expect(window.localStorage.getItem('kpi.scoring.display.shotclock')).toBeNull()

    await mountFor('scoreboard')
    expect(themeButton('light').getAttribute('aria-pressed')).toBe('true')
  })

  it('falls back to the defaults when the stored choice is corrupted', async () => {
    window.localStorage.setItem('kpi.scoring.display.scoreboard', '{not json')
    const wrapper = await mountFor('scoreboard')
    submit().click()
    expect(wrapper.emitted('open')).toEqual([['scoreboard', { theme: 'dark', shotclock: true }]])
  })

  it('closes with Cancel without opening anything', async () => {
    const wrapper = await mountFor('scoreboard')
    bodyButton(t('common.cancel'))!.click()
    expect(wrapper.emitted('close')).toHaveLength(1)
    expect(wrapper.emitted('open')).toBeUndefined()
  })
})
