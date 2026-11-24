#!/usr/bin/env node
// Synthèse de couverture pour la CI (COV-05, COV-06) — DOC/developer/in-progress/TEST_COVERAGE_STRATEGY.md
//
//   node scripts/ci/coverage-summary.mjs <brique> <vitest|clover> <fichier-de-rapport>
//
// Écrit un tableau Markdown dans $GITHUB_STEP_SUMMARY (stdout en local). Ne fait JAMAIS échouer la CI :
// un rapport absent ou illisible donne une ligne « non disponible » (étape 1 = mesure, aucun seuil).
import { appendFileSync, existsSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

export const MARKDOWN_HEADER = '| Brique | Lignes | Instructions |\n|---|---|---|'

const percentage = (covered, total) => (total > 0 ? (covered / total) * 100 : 0)

/** Total d'un `coverage-summary.json` (reporter `json-summary` de Vitest), ou null si illisible. */
export function summarizeVitest(json) {
  try {
    const { total } = JSON.parse(json)
    if (!total?.lines || !total?.statements) return null
    return { lines: total.lines.pct, statements: total.statements.pct }
  } catch {
    return null
  }
}

/**
 * Métriques du `<project>` d'un clover.xml PHPUnit, ou null si illisible. PHPUnit ne publie pas de taux « lignes »
 * distinct : ses instructions sont les lignes exécutables, les deux colonnes portent donc la même valeur.
 */
export function summarizeClover(xml) {
  const project = /<project\b[^>]*>([\s\S]*)<\/project>/.exec(String(xml ?? ''))
  if (!project) return null
  // Le dernier <metrics> du projet est l'agrégat global ; ceux des <file> le précèdent.
  const all = [...project[1].matchAll(/<metrics\b[^>]*\bfiles="[^"]*"[^>]*>/g)]
  const metrics = all.at(-1)?.[0]
  const statements = Number(/\bstatements="(\d+)"/.exec(metrics ?? '')?.[1])
  const covered = Number(/\bcoveredstatements="(\d+)"/.exec(metrics ?? '')?.[1])
  if (Number.isNaN(statements) || Number.isNaN(covered)) return null
  const pct = percentage(covered, statements)
  return { lines: pct, statements: pct }
}

export function toMarkdownRow(label, summary) {
  if (!summary) return `| ${label} | non disponible | non disponible |`
  return `| ${label} | ${summary.lines.toFixed(2)} % | ${summary.statements.toFixed(2)} % |`
}

const SUMMARIZERS = { vitest: summarizeVitest, clover: summarizeClover }

function main([label, format, reportPath]) {
  const summarize = SUMMARIZERS[format]
  if (!label || !summarize || !reportPath) {
    console.error('Usage : coverage-summary.mjs <brique> <vitest|clover> <fichier>')
    return
  }
  const summary = existsSync(reportPath) ? summarize(readFileSync(reportPath, 'utf8')) : null
  const table = `${MARKDOWN_HEADER}\n${toMarkdownRow(label, summary)}\n`
  if (process.env.GITHUB_STEP_SUMMARY) appendFileSync(process.env.GITHUB_STEP_SUMMARY, `\n${table}`)
  else process.stdout.write(table)
}

if (process.argv[1] === fileURLToPath(import.meta.url)) main(process.argv.slice(2))
