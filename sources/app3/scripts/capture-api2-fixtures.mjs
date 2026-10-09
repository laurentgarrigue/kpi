// Captures real api2 responses on the SQL/fixtures dataset into tests/fixtures/api2/, so that app3 tests run
// against the actual api2 contract (DOC/specs/public/API_PUBLIC_RESULTS.md) without a database.
//
// Usage: node scripts/capture-api2-fixtures.mjs http://127.0.0.1:8099
// (api2 served on the fixtures database, see sources/app3/README.md « Données de test api2 »).
import { mkdir, writeFile } from 'node:fs/promises'
import { fixtureFile, FIXTURE_PATHS } from '../tests/fixtures/api2/paths.mjs'

const baseUrl = process.argv[2]
if (!baseUrl) {
  console.error('Usage: node scripts/capture-api2-fixtures.mjs <api2 base URL>')
  process.exit(1)
}

await mkdir(new URL('../tests/fixtures/api2/', import.meta.url), { recursive: true })
for (const path of FIXTURE_PATHS) {
  const response = await fetch(`${baseUrl}${path}`)
  const body = await response.json()
  await writeFile(new URL(`../tests/fixtures/api2/${fixtureFile(path)}`, import.meta.url), `${JSON.stringify({ status: response.status, body }, null, 2)}\n`)
  console.log(response.status, path)
}
