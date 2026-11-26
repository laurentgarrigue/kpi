// PWA-05 (DOC/specs/PWA_AUTO_UPDATE.md §8) — `make ci_scripts_test`
import { test } from 'node:test'
import assert from 'node:assert/strict'
import { findSilentActivation } from './check-sw-prompt.mjs'

// Shapes emitted by workbox-build (generateSW), minified as in .output/public/sw.js.
const PROMPT_SW = 'define(["./workbox-1"],(function(e){"use strict";self.addEventListener("message",(e=>{e.data&&"SKIP_WAITING"===e.data.type&&self.skipWaiting()})),e.precacheAndRoute([],{})}));'
const AUTO_UPDATE_SW = 'define(["./workbox-1"],(function(e){"use strict";self.skipWaiting(),e.clientsClaim(),e.precacheAndRoute([],{})}));'

test('PWA-05 un worker prompt (skipWaiting sur message SKIP_WAITING) est accepté', () => {
  assert.deepEqual(findSilentActivation(PROMPT_SW), [])
})

test('PWA-05 un worker autoUpdate (skipWaiting + clientsClaim directs) est refusé', () => {
  assert.deepEqual(findSilentActivation(AUTO_UPDATE_SW), ['skipWaiting()', 'clientsClaim()'])
})

test('PWA-05 un clientsClaim seul est refusé', () => {
  assert.deepEqual(findSilentActivation(PROMPT_SW.replace('e.precacheAndRoute', 'e.clientsClaim(),e.precacheAndRoute')), ['clientsClaim()'])
})
