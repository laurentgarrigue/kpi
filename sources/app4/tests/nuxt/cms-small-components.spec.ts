// PAGE_CMS.md § 2 — status badge, pager and SEO fields of the editorial contents.
import { describe, expect, it } from 'vitest'
import { reactive } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import StatusBadge from '../../components/cms/StatusBadge.vue'
import Pager from '../../components/cms/Pager.vue'
import SeoFields from '../../components/cms/SeoFields.vue'
import type { CmsSeoFields } from '../../types/cms'
import { t } from './helpers'

describe('CmsStatusBadge', () => {
  it.each(['draft', 'published', 'scheduled', 'archived'] as const)('shows the translated %s status', async (status) => {
    const wrapper = await mountSuspended(StatusBadge, { props: { status } })
    expect(wrapper.text()).toBe(t(`cms.status.${status}`))
  })
})

describe('CmsPager', () => {
  const pager = (page: number, pages: number) => mountSuspended(Pager, { props: { page, pages } })
  const previous = (w: Awaited<ReturnType<typeof pager>>) => w.find(`button[aria-label="${t('cms.previous_page')}"]`)
  const next = (w: Awaited<ReturnType<typeof pager>>) => w.find(`button[aria-label="${t('cms.next_page')}"]`)

  it('shows the current page out of the total', async () => {
    const wrapper = await pager(2, 5)
    expect(wrapper.text()).toContain(t('cms.page_of', { page: 2, pages: 5 }))
  })

  it('goes to the previous and next page, as a parent holding the v-model does', async () => {
    const wrapper = await pager(2, 5)
    await previous(wrapper).trigger('click')
    expect(wrapper.emitted('update:page')).toEqual([[1]])
    await wrapper.setProps({ page: 4 })
    await next(wrapper).trigger('click')
    expect(wrapper.emitted('update:page')).toEqual([[1], [5]])
  })

  it('cannot go before the first page', async () => {
    const wrapper = await pager(1, 5)
    expect(previous(wrapper).attributes('disabled')).toBeDefined()
    expect(next(wrapper).attributes('disabled')).toBeUndefined()
  })

  it('cannot go after the last page', async () => {
    const wrapper = await pager(5, 5)
    expect(next(wrapper).attributes('disabled')).toBeDefined()
    expect(previous(wrapper).attributes('disabled')).toBeUndefined()
  })
})

describe('CmsSeoFields (FEATURE_CMS.md § 2)', () => {
  const seo = (): CmsSeoFields => reactive({
    seo_title_fr: 'Titre FR', seo_title_en: 'Title EN', seo_description_fr: 'Description FR', seo_description_en: 'Description EN',
  })
  const mount = (props: Record<string, unknown> = {}) => mountSuspended(SeoFields, {
    props: { modelValue: seo(), language: 'fr', fallbackTitle: 'Titre de l\'article', errors: {}, ...props },
  })

  it('edits the fields of the language in use', async () => {
    const fr = await mount({ language: 'fr' })
    expect((fr.find('input').element as HTMLInputElement).value).toBe('Titre FR')
    expect((fr.find('textarea').element as HTMLTextAreaElement).value).toBe('Description FR')

    const en = await mount({ language: 'en' })
    expect((en.find('input').element as HTMLInputElement).value).toBe('Title EN')
    expect((en.find('textarea').element as HTMLTextAreaElement).value).toBe('Description EN')
  })

  it('writes what is typed into the field of the language in use only', async () => {
    const model = seo()
    const wrapper = await mount({ modelValue: model, language: 'en' })
    await wrapper.find('input').setValue('New title')
    expect(model.seo_title_en).toBe('New title')
    expect(model.seo_title_fr).toBe('Titre FR')
  })

  it('suggests the content title when no SEO title is set, and previews it', async () => {
    const empty = reactive({ seo_title_fr: '', seo_title_en: '', seo_description_fr: '', seo_description_en: '' })
    const wrapper = await mount({ modelValue: empty })
    expect(wrapper.find('input').attributes('placeholder')).toBe('Titre de l\'article')
    expect(wrapper.find(`[aria-label="${t('cms.seo.preview')}"]`).text()).toContain('Titre de l\'article — kayak-polo.info')
  })

  it('previews the SEO title and description as a search result', async () => {
    const wrapper = await mount()
    const preview = wrapper.find(`[aria-label="${t('cms.seo.preview')}"]`).text()
    expect(preview).toContain('Titre FR — kayak-polo.info')
    expect(preview).toContain('Description FR')
  })

  it('limits the title to 255 characters and the description to 300', async () => {
    const wrapper = await mount()
    expect(wrapper.find('input').attributes('maxlength')).toBe('255')
    expect(wrapper.find('textarea').attributes('maxlength')).toBe('300')
  })

  it('shows the error of the field of the language in use only', async () => {
    const errors = { seo_title_en: 'too_long' }
    const fr = await mount({ language: 'fr', errors })
    expect(fr.text()).not.toContain(t('cms.errors.too_long'))
    const en = await mount({ language: 'en', errors })
    expect(en.text()).toContain(t('cms.errors.too_long'))
  })
})
