import { afterEach, vi } from 'vitest'

// A component name that does not resolve (e.g. a folder-prefixed auto-import name) only logs a Vue
// warning and renders nothing: turn it into a test failure.
const UNRESOLVED_COMPONENT = /Failed to resolve component/

const originalWarn = console.warn
const unresolved: string[] = []

vi.spyOn(console, 'warn').mockImplementation((...args: unknown[]) => {
  const message = args.map(String).join(' ')
  if (UNRESOLVED_COMPONENT.test(message)) {
    unresolved.push(message)
  }
  originalWarn(...args)
})

afterEach(() => {
  const messages = unresolved.splice(0)
  if (messages.length > 0) {
    throw new Error(`Unresolved component(s):\n${messages.join('\n')}`)
  }
})
