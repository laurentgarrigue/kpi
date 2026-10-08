import { readFileSync } from 'node:fs'
import { fixtureFile } from './paths.mjs'

interface Fixture {
  status: number
  body: unknown
}

/** Real api2 response captured on SQL/fixtures (scripts/capture-api2-fixtures.mjs). */
export function api2Fixture(path: string): Fixture {
  return JSON.parse(readFileSync(new URL(fixtureFile(path), import.meta.url), 'utf8')) as Fixture
}

/** Same contract as `useApi2()`: resolves the body, or rejects like `$fetch` with the HTTP status. */
export async function fakeApi2<T>(path: string): Promise<T> {
  let fixture: Fixture
  try {
    fixture = api2Fixture(path)
  } catch {
    throw Object.assign(new Error(`No api2 fixture for ${path}`), { statusCode: 599 })
  }
  if (fixture.status >= 400) {
    throw Object.assign(new Error(`api2 ${fixture.status}`), { statusCode: fixture.status })
  }
  return fixture.body as T
}
