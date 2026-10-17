import { describe, expect, it } from 'vitest'
import en from '../../i18n/locales/en.json'
import fr from '../../i18n/locales/fr.json'
import { MAIN_MENU, isMenuGroup } from '../../app/utils/navigation'

type Messages = { [key: string]: string | Messages }

const flatKeys = (messages: Messages, prefix = ''): string[] =>
  Object.entries(messages).flatMap(([key, value]) =>
    typeof value === 'string' ? [`${prefix}${key}`] : flatKeys(value, `${prefix}${key}.`),
  )

describe('translations', () => {
  it('French and English define exactly the same keys', () => {
    expect(flatKeys(en).sort()).toEqual(flatKeys(fr).sort())
  })

  it('every menu entry and group has a label (nav.<id>)', () => {
    const ids = MAIN_MENU.flatMap(item => (isMenuGroup(item) ? [item.id, ...item.children.map(child => child.id)] : [item.id]))
    const navKeys = Object.keys(fr.nav)
    expect(ids.filter(id => !navKeys.includes(id))).toEqual([])
  })
})
