#!/usr/bin/env node
// Cliquet de couverture (COV-11 à COV-16) — DOC/developer/in-progress/TEST_COVERAGE_STRATEGY.md
//
//   node scripts/ci/coverage-check.mjs <brique> <vitest|clover> <dossier-de-la-brique> <rapport> [--lcov <lcov.info>] [--base <ref>]
//
//   <rapport> : coverage-summary.json (vitest) ou clover.xml (clover, qui sert aussi aux lignes du diff).
//   --base    : mode PR, vérifie aussi la couverture des lignes modifiées depuis <ref>. Sans lui : plancher seul.
// Politique : scripts/ci/coverage-policy.json. Code de sortie 1 si la politique est violée.
import { appendFileSync, readFileSync } from 'node:fs'
import { execFileSync } from 'node:child_process'
import { posix, isAbsolute, relative, dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { summarizeClover, summarizeVitest } from './coverage-summary.mjs'

const RAISE_FLOOR_MARGIN = 1

const toRepoPath = (file, appDir, repoRoot) =>
  isAbsolute(file) ? relative(repoRoot, file).split('\\').join('/') : posix.normalize(posix.join(appDir, file))

/** lcov.info → Map<fichier (depuis la racine du dépôt), Map<ligne, passages>>. */
export function parseLcov(text, appDir, repoRoot) {
  const files = new Map()
  let current = null
  for (const line of text.split('\n')) {
    if (line.startsWith('SF:')) {
      current = new Map()
      files.set(toRepoPath(line.slice(3).trim(), appDir, repoRoot), current)
    } else if (line.startsWith('DA:') && current) {
      const [num, hits] = line.slice(3).split(',').map(Number)
      current.set(num, hits)
    }
  }
  return files
}

/** clover.xml → même structure ; seules les lignes `stmt` sont exécutables (pas `method`). */
export function parseCloverLines(xml, repoRoot) {
  const files = new Map()
  for (const [, name, body] of xml.matchAll(/<file\b[^>]*\bname="([^"]+)"[^>]*>([\s\S]*?)<\/file>/g)) {
    const lines = new Map()
    for (const [, tag] of body.matchAll(/<line\b([^>]*)\/?>/g)) {
      if (!/\btype="stmt"/.test(tag)) continue
      lines.set(Number(/\bnum="(\d+)"/.exec(tag)[1]), Number(/\bcount="(\d+)"/.exec(tag)[1]))
    }
    files.set(toRepoPath(name, '', repoRoot), lines)
  }
  return files
}

/** Sortie de `git diff -U0` → Map<fichier, Set<ligne ajoutée ou modifiée>>. */
export function parseChangedLines(diff) {
  const files = new Map()
  let current = null
  for (const line of diff.split('\n')) {
    if (line.startsWith('+++ ')) {
      const path = line.slice(4).trim()
      current = path === '/dev/null' ? null : new Set()
      if (current) files.set(path.replace(/^b\//, ''), current)
    } else if (line.startsWith('@@') && current) {
      const [, start, count] = /\+(\d+)(?:,(\d+))?/.exec(line)
      for (let i = 0; i < Number(count ?? 1); i++) current.add(Number(start) + i)
    }
  }
  return files
}

/** Part des lignes modifiées qui sont exécutables (présentes dans le rapport) et couvertes. */
export function measureDiffCoverage(coverage, changed) {
  let total = 0
  let covered = 0
  const uncovered = []
  for (const [file, lines] of changed) {
    const hits = coverage.get(file)
    if (!hits) continue
    for (const line of [...lines].sort((a, b) => a - b)) {
      if (!hits.has(line)) continue
      total++
      if (hits.get(line) > 0) covered++
      else uncovered.push(`${file}:${line}`)
    }
  }
  return { total, covered, pct: total === 0 ? 100 : (covered / total) * 100, uncovered }
}

export function checkFloor(pct, floor) {
  if (floor === null || floor === undefined) {
    return { ok: true, message: `Plancher non défini (baseline à fixer) : ${pct.toFixed(2)} % mesurés.` }
  }
  if (pct < floor) return { ok: false, message: `Couverture ${pct.toFixed(2)} % sous le plancher de ${floor} %.` }
  const raise = pct - floor >= RAISE_FLOOR_MARGIN
    ? ` Vous pouvez relever le plancher à ${Math.floor(pct * 10) / 10} % (scripts/ci/coverage-policy.json).`
    : ''
  return { ok: true, message: `Couverture ${pct.toFixed(2)} % ≥ plancher ${floor} %.${raise}` }
}

export function evaluate({ pct, floor, diff, diffThreshold }) {
  const floorResult = checkFloor(pct, floor)
  const messages = [floorResult.message]
  let ok = floorResult.ok
  if (diff && diff.total > 0) {
    const diffOk = diffThreshold == null || diff.pct >= diffThreshold
    messages.push(`Lignes modifiées : ${diff.covered}/${diff.total} couvertes (${diff.pct.toFixed(1)} %, seuil ${diffThreshold} %).`)
    if (!diffOk) {
      ok = false
      messages.push(`Lignes modifiées non couvertes :\n${diff.uncovered.map(l => `  - ${l}`).join('\n')}`)
    }
  }
  return { ok, messages }
}

function main(argv) {
  const [label, format, appDir, report] = argv
  const option = name => argv[argv.indexOf(name) + 1]
  const base = argv.includes('--base') ? option('--base') : null
  const repoRoot = process.cwd()
  const policy = JSON.parse(readFileSync(join(dirname(fileURLToPath(import.meta.url)), 'coverage-policy.json'), 'utf8'))

  const text = readFileSync(report, 'utf8')
  const summary = format === 'clover' ? summarizeClover(text) : summarizeVitest(text)
  if (!summary) {
    console.error(`::error::Rapport de couverture illisible : ${report}`)
    process.exit(1)
  }

  let diff = null
  if (base) {
    const coverage = format === 'clover'
      ? parseCloverLines(text, repoRoot)
      : parseLcov(readFileSync(option('--lcov'), 'utf8'), appDir, repoRoot)
    const changed = parseChangedLines(
      execFileSync('git', ['diff', '-U0', '--diff-filter=AM', `${base}...HEAD`, '--', appDir], { encoding: 'utf8' }),
    )
    diff = measureDiffCoverage(coverage, changed)
  }

  const { ok, messages } = evaluate({
    pct: summary.lines, floor: policy.floors[label], diff, diffThreshold: policy.diffThresholds[label],
  })
  const output = `**${label}** — ${ok ? '✅' : '❌'}\n\n${messages.map(m => `- ${m}`).join('\n')}\n`
  if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, `\n${output}`)
  console.log(output)
  if (!ok) process.exit(1)
}

if (process.argv[1] === fileURLToPath(import.meta.url)) main(process.argv.slice(2))
