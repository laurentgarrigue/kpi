// PAGE_CMS.md § 2 (ADM-CMS-03) — the rich text editor only offers what the api2 HTML filter keeps.
import { describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import RichEditor from '../../components/cms/RichEditor.vue'
import { bodyButton, track } from './modal-helpers'
import { t } from './helpers'

mockNuxtImport('useCmsApi', () => () => ({ media: vi.fn().mockResolvedValue({ items: [], pages: 1 }), uploadMedia: vi.fn() }))

const COMMAND_LABELS = [
  'h2', 'h3', 'h4', 'bold', 'italic', 'underline', 'strike', 'bullet_list', 'ordered_list',
  'quote', 'link', 'image', 'table', 'video', 'undo', 'redo',
]

const editor = async (html = '<p>Texte</p>') => track(await mountSuspended(RichEditor, { props: { modelValue: html } }))

describe('CmsRichEditor', () => {
  it('offers exactly the formatting the HTML filter of api2 keeps', async () => {
    const wrapper = await editor()
    await vi.waitFor(() => expect(wrapper.find('[role="toolbar"]').exists()).toBe(true))
    const labels = wrapper.findAll('[role="toolbar"] button').map(b => b.attributes('aria-label'))
    expect(labels).toEqual(COMMAND_LABELS.map(label => t(`cms.editor.${label}`)))
  })

  it('names its toolbar', async () => {
    const wrapper = await editor()
    await vi.waitFor(() => expect(wrapper.find('[role="toolbar"]').exists()).toBe(true))
    expect(wrapper.find('[role="toolbar"]').attributes('aria-label')).toBe(t('cms.editor.toolbar'))
  })

  it('opens the link dialog from the toolbar, with its help', async () => {
    const wrapper = await editor()
    await vi.waitFor(() => expect(wrapper.find('[role="toolbar"]').exists()).toBe(true))
    wrapper.find(`[role="toolbar"] button[aria-label="${t('cms.editor.link')}"]`).element.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()
    await vi.waitFor(() => expect(document.body.textContent).toContain(t('cms.editor.link_help')))
    expect(bodyButton(t('common.validate'))).toBeDefined()
  })

  it('opens the video dialog from the toolbar', async () => {
    const wrapper = await editor()
    await vi.waitFor(() => expect(wrapper.find('[role="toolbar"]').exists()).toBe(true))
    wrapper.find(`[role="toolbar"] button[aria-label="${t('cms.editor.video')}"]`).element.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()
    await vi.waitFor(() => expect(document.body.textContent).toContain(t('cms.editor.video_url')))
  })

  it('opens the media library from the image button', async () => {
    const wrapper = await editor()
    await vi.waitFor(() => expect(wrapper.find('[role="toolbar"]').exists()).toBe(true))
    wrapper.find(`[role="toolbar"] button[aria-label="${t('cms.editor.image')}"]`).element.dispatchEvent(new MouseEvent('click', { bubbles: true }))
    await nextTick()
    await vi.waitFor(() => expect(document.body.textContent).toContain(t('cms.media.pick')))
  })
})
