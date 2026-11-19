// COV-05 — `node --test scripts/ci/`
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { summarizeVitest, summarizeClover, toMarkdownRow, MARKDOWN_HEADER } from './coverage-summary.mjs'

const vitestJson = JSON.stringify({
  total: {
    lines: { total: 200, covered: 150, pct: 75 },
    statements: { total: 220, covered: 154, pct: 70 },
  },
})

const cloverXml = `<?xml version="1.0"?>
<coverage generated="1"><project timestamp="1">
  <file name="/app/src/A.php"><metrics statements="10" coveredstatements="5"/></file>
  <metrics files="1" loc="40" ncloc="30" classes="1" methods="3" coveredmethods="1" conditionals="0" coveredconditionals="0" statements="10" coveredstatements="5" elements="13" coveredelements="6"/>
</project></coverage>`

test('COV-05 lit le total d\'un coverage-summary.json Vitest', () => {
  assert.deepEqual(summarizeVitest(vitestJson), { lines: 75, statements: 70 })
})

test('COV-05 lit les métriques globales d\'un clover.xml PHPUnit', () => {
  // le <metrics> de <project> fait foi, pas celui du <file>
  assert.deepEqual(summarizeClover(cloverXml), { lines: 50, statements: 50 })
})

test('COV-05 un rapport illisible donne null, sans lever', () => {
  assert.equal(summarizeVitest('{ pas du json'), null)
  assert.equal(summarizeVitest('{}'), null)
  assert.equal(summarizeClover('<html/>'), null)
  assert.equal(summarizeClover(undefined), null)
})

test('COV-05 formate une ligne de tableau Markdown', () => {
  assert.equal(toMarkdownRow('app3', { lines: 75, statements: 70 }), '| app3 | 75.00 % | 70.00 % |')
  assert.equal(toMarkdownRow('app4', null), '| app4 | non disponible | non disponible |')
  assert.match(MARKDOWN_HEADER, /^\| Brique \| Lignes \| Instructions \|\n\|[-| ]+\|$/)
})
