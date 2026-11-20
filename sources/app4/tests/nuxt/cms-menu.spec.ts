// PAGE_CMS.md § 5 (ADM-CMS-08) — the editorial menu: entries, up to three levels, move and remove.
import { describe, expect, it } from 'vitest'
import { reactive } from 'vue'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import MenuItemRow from '../../components/cms/MenuItemRow.vue'
import MenuTree from '../../components/cms/MenuTree.vue'
import type { CmsMenuItem } from '../../types/cms'
import { t } from './helpers'

const entry = (overrides: Partial<CmsMenuItem> = {}): CmsMenuItem => ({
  label_fr: 'Accueil', label_en: 'Home', target_kind: 'page', target_ref: 'home', children: [], ...overrides,
})
const pageItems = [{ label: 'Accueil', value: 'home' }, { label: 'Contact', value: 'contact' }]
const targetItems = [{ label: 'Page', value: 'page' }, { label: 'URL', value: 'url' }, { label: 'Article', value: 'article' }]

describe('CmsMenuItemRow', () => {
  const row = (props: Record<string, unknown> = {}) => mountSuspended(MenuItemRow, {
    props: { modelValue: reactive(entry()), pageItems, targetItems, errors: {}, prefix: 'items.0', first: false, last: false, ...props },
  })
  const button = (w: Awaited<ReturnType<typeof row>>, key: string) => w.find(`button[aria-label="${t(key)}"]`)

  it('edits the French and English labels', async () => {
    const model = reactive(entry())
    const wrapper = await row({ modelValue: model })
    const inputs = wrapper.findAll('input')
    expect((inputs[0]!.element as HTMLInputElement).value).toBe('Accueil')
    expect((inputs[1]!.element as HTMLInputElement).value).toBe('Home')
    await inputs[0]!.setValue('Résultats')
    expect(model.label_fr).toBe('Résultats')
  })

  it('moves up, moves down and removes the entry', async () => {
    const wrapper = await row()
    await button(wrapper, 'cms.move_up').trigger('click')
    await button(wrapper, 'cms.move_down').trigger('click')
    await button(wrapper, 'common.delete').trigger('click')
    expect(wrapper.emitted('up')).toHaveLength(1)
    expect(wrapper.emitted('down')).toHaveLength(1)
    expect(wrapper.emitted('remove')).toHaveLength(1)
  })

  it('cannot move the first entry up, nor the last one down', async () => {
    const first = await row({ first: true })
    expect(button(first, 'cms.move_up').attributes('disabled')).toBeDefined()
    expect(button(first, 'cms.move_down').attributes('disabled')).toBeUndefined()

    const last = await row({ last: true })
    expect(button(last, 'cms.move_down').attributes('disabled')).toBeDefined()
    expect(button(last, 'cms.move_up').attributes('disabled')).toBeUndefined()
  })

  it('asks for a free destination when the target is not a page', async () => {
    const url = await row({ modelValue: reactive(entry({ target_kind: 'url', target_ref: '' })) })
    expect(url.findAll('input').some(i => i.attributes('placeholder') === 'https://… ou /calendar')).toBe(true)

    const article = await row({ modelValue: reactive(entry({ target_kind: 'article', target_ref: '' })) })
    expect(article.findAll('input').some(i => i.attributes('placeholder') === t('cms.menu.article_id'))).toBe(true)
  })

  it('offers a list of pages when the target is a page: no free input for the destination', async () => {
    const wrapper = await row()
    expect(wrapper.findAll('input')).toHaveLength(2) // the two labels only
  })

  it('shows the errors of its own entry only, through its prefix', async () => {
    const wrapper = await row({ errors: { 'items.0.label_fr': 'required', 'items.1.label_fr': 'invalid' } })
    expect(wrapper.text()).toContain(t('cms.errors.required'))
    expect(wrapper.text()).not.toContain(t('cms.errors.invalid'))
  })

  it('shows the error of the destination', async () => {
    const wrapper = await row({ errors: { 'items.0.target_ref': 'invalid' } })
    expect(wrapper.text()).toContain(t('cms.errors.invalid'))
  })
})

describe('CmsMenuTree', () => {
  const tree = (items: CmsMenuItem[], props: Record<string, unknown> = {}) => mountSuspended(MenuTree, {
    props: { modelValue: items, depth: 1, prefix: 'items', pageItems, targetItems, errors: {}, ...props },
  })

  it('shows one numbered level badge per entry', async () => {
    const wrapper = await tree([entry(), entry({ label_fr: 'Contact' })])
    const levels = wrapper.findAll('li[data-level]')
    expect(levels).toHaveLength(2)
    expect(levels[0]!.text()).toContain(t('cms.menu.level', { level: 1 }))
  })

  it('shows the sub-entries recursively, one level deeper', async () => {
    const wrapper = await tree([entry({ children: [entry({ label_fr: 'Sous-entrée', children: [entry({ label_fr: 'Feuille' })] })] })])
    expect(wrapper.findAll('ol[data-depth]').map(o => o.attributes('data-depth'))).toEqual(['1', '2', '3'])
    expect(wrapper.findAll('li[data-level="3"]')).toHaveLength(1)
  })

  it('indents the sub-levels, not the top level', async () => {
    const wrapper = await tree([entry({ children: [entry()] })])
    const [top, sub] = wrapper.findAll('ol[data-depth]')
    expect(top!.classes()).not.toContain('ml-6')
    expect(sub!.classes()).toContain('ml-6')
  })

  it('offers to add a sub-entry up to the third level, not beyond', async () => {
    const top = await tree([entry()])
    expect(top.text()).toContain(t('cms.menu.add_child', { level: 2 }))

    const third = await tree([entry()], { depth: 3 })
    expect(third.text()).not.toContain(t('cms.menu.add_child', { level: 4 }))
    expect(third.findAll('button').some(b => b.text().includes('add_child'))).toBe(false)
  })

  it('adds an empty sub-entry', async () => {
    const wrapper = await tree([reactive(entry())] as CmsMenuItem[])
    expect(wrapper.findAll('li[data-level="2"]')).toHaveLength(0)
    await wrapper.findAll('button').find(b => b.text() === t('cms.menu.add_child', { level: 2 }))!.trigger('click')
    expect(wrapper.findAll('li[data-level="2"]')).toHaveLength(1)
  })

  it('moves an entry and removes one, through the v-model', async () => {
    const wrapper = await tree([entry({ label_fr: 'A' }), entry({ label_fr: 'B' })])
    const rows = wrapper.findAllComponents({ name: 'CmsMenuItemRow' })
    rows[0]!.vm.$emit('down')
    expect((wrapper.emitted('update:modelValue')![0]![0] as CmsMenuItem[]).map(i => i.label_fr)).toEqual(['B', 'A'])

    // The list is now [B, A]: removing the second row removes A.
    rows[1]!.vm.$emit('remove')
    expect((wrapper.emitted('update:modelValue')![1]![0] as CmsMenuItem[]).map(i => i.label_fr)).toEqual(['B'])
  })

  it('tells the first entry it is first and the last one it is last', async () => {
    const wrapper = await tree([entry(), entry(), entry()])
    const rows = wrapper.findAllComponents({ name: 'CmsMenuItemRow' })
    expect(rows.map(r => [r.props('first'), r.props('last')])).toEqual([[true, false], [false, false], [false, true]])
  })

  it('gives each entry its own error prefix, children included', async () => {
    const wrapper = await tree([entry({ children: [entry()] })])
    expect(wrapper.findAllComponents({ name: 'CmsMenuItemRow' }).map(r => r.props('prefix'))).toEqual(['items.0', 'items.0.children.0'])
  })

  it('shows the error of an entry\'s sub-entries', async () => {
    const wrapper = await tree([entry()], { errors: { 'items.0.children': 'too_many' } })
    expect(wrapper.text()).toContain(t('cms.errors.too_many'))
  })
})
