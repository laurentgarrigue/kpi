#!/usr/bin/env node
// PWA-05 (DOC/specs/PWA_AUTO_UPDATE.md §2) : le service worker généré ne doit s'activer que sur le message
// SKIP_WAITING du bandeau « Recharger », jamais en silence. Avec `registerType: 'autoUpdate'`, @vite-pwa/nuxt
// force `self.skipWaiting()` + `clientsClaim()` dans sw.js, quelles que soient les options workbox.
//
// Usage : node scripts/ci/check-sw-prompt.mjs <chemin/vers/sw.js>
import { readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'

const SKIP_WAITING_ON_MESSAGE = /addEventListener\(\s*["']message["'][^;]*?SKIP_WAITING[^;]*?skipWaiting\(\)/g
const SILENT_CALLS = ['skipWaiting()', 'clientsClaim()']

/** The activation calls made outside the SKIP_WAITING message handler (empty = prompt-to-update). */
export const findSilentActivation = (source) => {
  const outsideHandler = source.replace(SKIP_WAITING_ON_MESSAGE, '')
  return SILENT_CALLS.filter((call) => outsideHandler.includes(call))
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  const path = process.argv[2]
  if (!path) {
    console.error('Usage: node scripts/ci/check-sw-prompt.mjs <sw.js>')
    process.exit(2)
  }
  const silent = findSilentActivation(readFileSync(path, 'utf8'))
  if (silent.length > 0) {
    console.error(`::error::${path} active le worker sans action de l'utilisateur (${silent.join(', ')}) : registerType doit être 'prompt'`)
    process.exit(1)
  }
  console.log(`OK: ${path} n'appelle skipWaiting() que sur le message SKIP_WAITING`)
}
