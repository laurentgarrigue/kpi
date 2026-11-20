// PAGE_CMS.md § 4 — media library in a modal: pick an existing file or send a new one.
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'
import { mockNuxtImport, mountSuspended } from '@nuxt/test-utils/runtime'
import MediaPicker from '../../components/cms/MediaPicker.vue'
import type { CmsMedia } from '../../types/cms'
import { bodyButton, track } from './modal-helpers'
import { t } from './helpers'

const media = (overrides: Partial<CmsMedia> = {}): CmsMedia => ({
  id: 1, url: 'https://kpi.localhost/img/cms/photo.jpg?v=2', thumbnail: '/img/cms/photo-thumb.jpg', name: 'photo.jpg', mime: 'image/jpeg',
  width: 800, height: 600, size: 1000, alt_fr: 'Une photo', alt_en: 'A photo', credit: '', album_id: null, created_at: '', updated_at: '', ...overrides,
})

const api = vi.hoisted(() => ({ media: vi.fn(), uploadMedia: vi.fn() }))
mockNuxtImport('useCmsApi', () => () => api)

const open = async (props: Record<string, unknown> = {}) => {
  const wrapper = track(await mountSuspended(MediaPicker, { props: { open: false, ...props } }))
  await wrapper.setProps({ open: true })
  await vi.waitFor(() => expect(api.media).toHaveBeenCalled())
  await nextTick()
  return wrapper
}
const flush = async () => { await nextTick(); await nextTick() }
const items = () => [...document.body.querySelectorAll<HTMLButtonElement>('ul li button')]
const fileInput = () => document.body.querySelector<HTMLInputElement>('[data-testid="media-file"]')!
const altInput = () => document.body.querySelector<HTMLInputElement>('input:not([type="file"])')!
const uploadButton = () => bodyButton(t('cms.media.upload'))!
const chooseFile = async (file: File) => {
  Object.defineProperty(fileInput(), 'files', { value: [file], configurable: true })
  fileInput().dispatchEvent(new Event('change', { bubbles: true }))
  await nextTick()
}
const png = () => new File(['x'], 'logo.png', { type: 'image/png' })
const pdf = () => new File(['x'], 'rules.pdf', { type: 'application/pdf' })

beforeEach(() => {
  api.media.mockReset().mockResolvedValue({ items: [media(), media({ id: 2, name: 'rules.pdf', mime: 'application/pdf' })], pages: 1 })
  api.uploadMedia.mockReset()
})

describe('CmsMediaPicker — library', () => {
  it('loads the library only when it opens', async () => {
    const wrapper = track(await mountSuspended(MediaPicker, { props: { open: false } }))
    expect(api.media).not.toHaveBeenCalled()
    await wrapper.setProps({ open: true })
    await vi.waitFor(() => expect(api.media).toHaveBeenCalledWith({ page: 1 }))
  })

  it('asks the library for one type of media when told so', async () => {
    await open({ type: 'image' })
    expect(api.media).toHaveBeenCalledWith({ page: 1, type: 'image' })
  })

  it('shows images as thumbnails with their alternative text, documents by name', async () => {
    await open()
    await flush()
    const img = document.body.querySelector<HTMLImageElement>('ul img')!
    expect(img.alt).toBe('Une photo')
    expect(items()).toHaveLength(2)
    expect(items()[1]!.textContent).toContain('rules.pdf')
    expect(items()[1]!.querySelector('img')).toBeNull()
  })

  it('selects the media that is clicked', async () => {
    const wrapper = await open()
    await flush()
    items()[1]!.click()
    expect(wrapper.emitted('select')![0]![0]).toMatchObject({ id: 2 })
  })

  it('has no pager for a single page', async () => {
    await open()
    await flush()
    expect(document.body.querySelector(`button[aria-label="${t('cms.next_page')}"]`)).toBeNull()
  })

  it('shows the pager when the library has several pages and loads the next one', async () => {
    api.media.mockResolvedValue({ items: [media()], pages: 3 })
    await open()
    await flush()
    const next = document.body.querySelector<HTMLButtonElement>(`button[aria-label="${t('cms.next_page')}"]`)!
    expect(next).not.toBeNull()
    next.click()
    await vi.waitFor(() => expect(api.media).toHaveBeenLastCalledWith({ page: 2 }))
  })

  it('closes from the modal', async () => {
    const wrapper = await open()
    document.body.querySelector<HTMLElement>('.bg-black\\/50')!.click()
    expect(wrapper.emitted('close')).toHaveLength(1)
  })
})

describe('CmsMediaPicker — upload', () => {
  it('cannot send before a file is chosen', async () => {
    await open()
    expect(uploadButton().disabled).toBe(true)
    await chooseFile(png())
    expect(uploadButton().disabled).toBe(false)
  })

  it('requires an alternative text for an image, and sends nothing without it', async () => {
    await open()
    await chooseFile(png())
    uploadButton().click()
    await flush()
    expect(api.uploadMedia).not.toHaveBeenCalled()
    expect(document.body.textContent).toContain(t('cms.errors.required'))
  })

  it('sends an image with its alternative text and selects the new media', async () => {
    api.uploadMedia.mockResolvedValue(media({ id: 9, name: 'logo.png' }))
    const wrapper = await open()
    await chooseFile(png())
    altInput().value = 'Logo du club'
    altInput().dispatchEvent(new Event('input', { bubbles: true }))
    await nextTick()
    uploadButton().click()
    await vi.waitFor(() => expect(wrapper.emitted('select')).toHaveLength(1))
    expect(api.uploadMedia).toHaveBeenCalledWith(expect.any(File), { alt_fr: 'Logo du club' })
    expect(wrapper.emitted('select')![0]![0]).toMatchObject({ id: 9 })
  })

  it('sends a document without alternative text', async () => {
    api.uploadMedia.mockResolvedValue(media({ id: 10, name: 'rules.pdf', mime: 'application/pdf' }))
    const wrapper = await open()
    await chooseFile(pdf())
    uploadButton().click()
    await vi.waitFor(() => expect(wrapper.emitted('select')).toHaveLength(1))
    expect(api.uploadMedia).toHaveBeenCalledWith(expect.any(File), { alt_fr: '' })
  })

  it('shows the error of the server and selects nothing', async () => {
    api.uploadMedia.mockRejectedValue({ error: 'validation_failed', errors: { file: 'type' } })
    const wrapper = await open()
    await chooseFile(pdf())
    uploadButton().click()
    await vi.waitFor(() => expect(document.body.textContent).toContain(t('cms.errors.file_type')))
    expect(wrapper.emitted('select')).toBeUndefined()
  })

  it('keeps the form for a retry after an error, and empties it after a success', async () => {
    api.uploadMedia.mockRejectedValueOnce({ error: 'validation_failed', errors: { file: 'type' } })
    api.uploadMedia.mockResolvedValueOnce(media({ id: 11 }))
    const wrapper = await open()
    await chooseFile(pdf())
    uploadButton().click()
    await vi.waitFor(() => expect(document.body.textContent).toContain(t('cms.errors.file_type')))
    expect(uploadButton().disabled).toBe(false)

    uploadButton().click()
    await vi.waitFor(() => expect(wrapper.emitted('select')).toHaveLength(1))
    await vi.waitFor(() => expect(uploadButton().disabled).toBe(true))
  })
})
