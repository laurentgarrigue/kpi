// COV-12 à COV-15 — `make ci_scripts_test`
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import {
  checkFloor, parseLcov, parseCloverLines, parseChangedLines, measureDiffCoverage, evaluate, changedLinesSince,
} from './coverage-check.mjs'

const REPO = '/repo'

test('COV-12 le plancher bloque sous sa valeur, pas au-dessus ni à égalité', () => {
  assert.equal(checkFloor(90.9, 91).ok, false)
  assert.equal(checkFloor(91, 91).ok, true)
  assert.equal(checkFloor(95, 91).ok, true)
})

test('COV-12 un plancher null ne bloque pas', () => {
  assert.equal(checkFloor(0, null).ok, true)
  assert.match(checkFloor(0, null).message, /baseline/i)
})

test('COV-14 suggère de relever le plancher à partir de 1 point d\'écart', () => {
  assert.match(checkFloor(93, 91).message, /relever/i)
  assert.doesNotMatch(checkFloor(91.5, 91).message, /relever/i)
})

test('COV-13 lcov : chemins relatifs à la brique ramenés à la racine du dépôt, lignes DA', () => {
  const lcov = 'SF:components/A.vue\nDA:1,3\nDA:2,0\nend_of_record\nSF:../kpi-layer/app/b.ts\nDA:5,1\nend_of_record\n'
  const map = parseLcov(lcov, 'sources/app3', REPO)
  assert.deepEqual([...map.get('sources/app3/components/A.vue')], [[1, 3], [2, 0]])
  assert.deepEqual([...map.get('sources/kpi-layer/app/b.ts')], [[5, 1]])
})

test('COV-13 clover : chemins absolus ramenés à la racine, seules les lignes stmt comptent', () => {
  const xml = `<project><file name="/repo/sources/api2/src/A.php">
    <line num="3" type="stmt" count="2"/><line num="4" type="method" name="f" count="1"/><line num="5" type="stmt" count="0"/>
  </file></project>`
  const map = parseCloverLines(xml, REPO)
  assert.deepEqual([...map.get('sources/api2/src/A.php')], [[3, 2], [5, 0]])
})

test('COV-13 git diff -U0 : lignes ajoutées par fichier, suppressions ignorées', () => {
  const diff = [
    'diff --git a/sources/app4/x.vue b/sources/app4/x.vue',
    '--- a/sources/app4/x.vue', '+++ b/sources/app4/x.vue',
    '@@ -3,2 +3,3 @@', '@@ -10 +11,0 @@', '@@ -20,0 +21,2 @@',
    'diff --git a/old.ts b/old.ts', '--- a/old.ts', '+++ /dev/null', '@@ -1,3 +0,0 @@',
  ].join('\n')
  const changed = parseChangedLines(diff)
  assert.deepEqual([...changed.get('sources/app4/x.vue')], [3, 4, 5, 21, 22])
  assert.equal(changed.has('old.ts'), false)
})

test('COV-13 la couverture du diff ne compte que les lignes exécutables du rapport', () => {
  const coverage = new Map([['f.ts', new Map([[1, 2], [2, 0], [3, 1]])]])
  const changed = new Map([['f.ts', new Set([1, 2, 9])], ['hors-perimetre.md', new Set([1])]])
  const r = measureDiffCoverage(coverage, changed)
  assert.equal(r.total, 2) // ligne 9 non exécutable, fichier .md hors rapport
  assert.equal(r.covered, 1)
  assert.equal(r.pct, 50)
  assert.deepEqual(r.uncovered, ['f.ts:2'])
})

test('COV-13 un diff sans ligne exécutable ne bloque pas', () => {
  const r = measureDiffCoverage(new Map(), new Map([['a.md', new Set([1])]]))
  assert.equal(r.total, 0)
  assert.equal(evaluate({ pct: 40, floor: 91, diff: r, diffThreshold: 80 }).ok, false) // plancher
  assert.equal(evaluate({ pct: 95, floor: 91, diff: r, diffThreshold: 80 }).ok, true)
})

test('COV-13/14/15 évalue plancher + diff, liste les lignes non couvertes ; sans diff, plancher seul', () => {
  const diff = { total: 4, covered: 1, pct: 25, uncovered: ['f.ts:2', 'f.ts:3', 'f.ts:4'] }
  const ko = evaluate({ pct: 95, floor: 91, diff, diffThreshold: 80 })
  assert.equal(ko.ok, false)
  assert.match(ko.messages.join('\n'), /f\.ts:2/)
  assert.equal(evaluate({ pct: 95, floor: 91, diff: null, diffThreshold: 80 }).ok, true)
})

test('COV-13 un diff de plusieurs Mo (lockfile régénéré) est lu en entier, sans dépasser le tampon', () => {
  const repo = mkdtempSync(join(tmpdir(), 'coverage-diff-'))
  const git = (...args) => execFileSync('git', ['-C', repo, '-c', 'user.name=ci', '-c', 'user.email=ci@example.invalid', ...args])
  try {
    git('init', '-q', '-b', 'main')
    writeFileSync(join(repo, 'a.ts'), 'export const a = 1\n')
    git('add', '.')
    git('commit', '-q', '-m', 'base')
    // ~3 Mo de lignes ajoutées : au-delà du tampon par défaut d'execFileSync (1 Mo).
    writeFileSync(join(repo, 'package-lock.json'), `${'"x": "0123456789012345678901234567890123456789",\n'.repeat(60_000)}`)
    writeFileSync(join(repo, 'a.ts'), 'export const a = 1\nexport const b = 2\n')
    git('add', '.')
    git('commit', '-q', '-m', 'big')
    const changed = changedLinesSince('HEAD~1', '.', repo)
    assert.deepEqual([...changed.get('a.ts')], [2])
    assert.equal(changed.get('package-lock.json').size, 60_000)
  } finally {
    rmSync(repo, { recursive: true, force: true })
  }
})
