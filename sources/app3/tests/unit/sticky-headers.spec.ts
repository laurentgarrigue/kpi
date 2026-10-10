import { readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

const APP = join(__dirname, '../../app')

function vueFiles(dir: string): string[] {
  return readdirSync(dir, { withFileTypes: true }).flatMap(entry =>
    entry.isDirectory() ? vueFiles(join(dir, entry.name)) : entry.name.endsWith('.vue') ? [join(dir, entry.name)] : [])
}

describe('sticky table headers (SITE_LAYOUT.md § 2)', () => {
  it('LAY-12: the site stylesheet pins the header cells of every table of the page', () => {
    const css = readFileSync(join(APP, 'assets/css/main.css'), 'utf8')
    expect(css).toMatch(/main thead th\s*\{[^}]*position: sticky;[^}]*top: 0;/)
  })

  it('LAY-12: no table sits in a horizontal scroll wrapper on large screens (it would break sticky headers)', () => {
    const offenders = vueFiles(APP).filter((file) => {
      const source = readFileSync(file, 'utf8')
      return source.includes('<table') && /class="[^"]*overflow-x-auto(?![^"]*lg:overflow-visible)[^"]*"/.test(source)
    })
    expect(offenders).toEqual([])
  })
})
