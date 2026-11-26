// PWA-01 à PWA-04 (DOC/specs/PWA_AUTO_UPDATE.md §8) — `make ci_scripts_test`, job CI `lint-docker`.
//
// Démarre le binaire `nginx` local sur chaque conf de docker/config/nginx-static*.conf, avec des fixtures à la
// place des builds Nuxt, et vérifie les en-têtes réellement servis. Seuls les ports, racines et journaux sont
// réécrits : les blocs `location`, le `map` et les `add_header` testés sont ceux du fichier versionné.
import { test, before, after, describe } from 'node:test'
import assert from 'node:assert/strict'
import { spawn, execFileSync } from 'node:child_process'
import { mkdtempSync, mkdirSync, writeFileSync, readFileSync, rmSync, chmodSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import http from 'node:http'
import net from 'node:net'

const CONFIG_DIR = join(dirname(fileURLToPath(import.meta.url)), '../../docker/config')
const NGINX = process.env.NGINX_BIN ?? 'nginx'

const REVALIDATE = 'no-cache, must-revalidate'
const DEV_HTML = 'no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0'
const SECURITY_HEADERS = {
  'x-frame-options': 'SAMEORIGIN',
  'x-content-type-options': 'nosniff',
  'x-xss-protection': '1; mode=block',
}
const SERVERS = [
  { app: 'app2', host: 'app.kpi.test' },
  { app: 'app4', host: 'kpi.test' },
]

const nginxInfo = (() => {
  try {
    // `nginx -V` écrit sur stderr.
    const out = execFileSync('sh', ['-c', `${NGINX} -V 2>&1`], { encoding: 'utf8' })
    const confPath = out.match(/--conf-path=(\S+)/)?.[1] ?? '/etc/nginx/nginx.conf'
    return { mimeTypes: join(dirname(confPath), 'mime.types') }
  } catch {
    return null
  }
})()
// Locally a missing nginx skips the suite; in CI (CI=true, set by GitHub Actions) it is an error.
if (!nginxInfo && process.env.CI) throw new Error(`${NGINX} is required in CI (scripts/ci/nginx-static.test.mjs)`)

const freePort = () => new Promise((resolve, reject) => {
  const server = net.createServer()
  server.listen(0, '127.0.0.1', () => {
    const { port } = server.address()
    server.close(() => resolve(port))
  })
  server.on('error', reject)
})

const writeFixtures = (root, app) => {
  const files = {
    'index.html': `<!doctype html><title>${app}</title>`,
    'sw.js': 'self.addEventListener("message",()=>{})',
    'registerSW.js': '/* register */',
    'workbox-4b126c97.js': '/* workbox */',
    'manifest.webmanifest': '{"name":"KPI"}',
    'pwa-192x192.png': 'png',
    '_nuxt/v1/entry.js': 'export {}',
    '.env': 'SECRET=1',
  }
  for (const [path, content] of Object.entries(files)) {
    mkdirSync(dirname(join(root, path)), { recursive: true })
    writeFileSync(join(root, path), content)
  }
}

/** The versioned conf, with only listen ports, roots and log paths rewritten for the sandbox. */
const sandboxConf = (confFile, port, workDir) => readFileSync(join(CONFIG_DIR, confFile), 'utf8')
  .replace(/listen 80 default_server;/g, `listen 127.0.0.1:${port} default_server;`)
  .replace(/listen 80;/g, `listen 127.0.0.1:${port};`)
  .replace(/root \/srv\/(app\d)\/\.output\/public;/g, (_, app) => `root ${join(workDir, app)};`)
  .replace(/(access|error)_log \/var\/log\/nginx\/\S+;/g, (_, kind) => `${kind}_log ${join(workDir, `${kind}.log`)};`)

const startNginx = async (confFile) => {
  const workDir = mkdtempSync(join(tmpdir(), 'nginx-static-'))
  chmodSync(workDir, 0o755)
  for (const { app } of SERVERS) writeFixtures(join(workDir, app), app)
  const port = await freePort()
  const runAsRoot = process.getuid?.() === 0 ? 'user root;' : ''
  writeFileSync(join(workDir, 'nginx.conf'), `${runAsRoot}
pid ${join(workDir, 'nginx.pid')};
error_log ${join(workDir, 'error.log')};
events {}
http {
  include ${nginxInfo.mimeTypes};
  default_type application/octet-stream;
  client_body_temp_path ${workDir}/body;
  proxy_temp_path ${workDir}/proxy;
  fastcgi_temp_path ${workDir}/fastcgi;
  uwsgi_temp_path ${workDir}/uwsgi;
  scgi_temp_path ${workDir}/scgi;
  ${sandboxConf(confFile, port, workDir)}
}
`)
  const child = spawn(NGINX, ['-p', workDir, '-c', join(workDir, 'nginx.conf'), '-g', 'daemon off;'], { stdio: ['ignore', 'ignore', 'pipe'] })
  let stderr = ''
  child.stderr.on('data', (chunk) => { stderr += chunk })
  for (let attempt = 0; attempt < 50; attempt++) {
    if (child.exitCode !== null) throw new Error(`nginx refused ${confFile}:\n${stderr}`)
    try {
      await request(port, 'kpi.test', '/')
      return { child, port, workDir }
    } catch {
      await new Promise((resolve) => setTimeout(resolve, 100))
    }
  }
  child.kill()
  throw new Error(`nginx did not start on ${confFile}:\n${stderr}`)
}

const request = (port, host, path) => new Promise((resolve, reject) => {
  const req = http.request({ host: '127.0.0.1', port, path, headers: { host } }, (res) => {
    let body = ''
    res.on('data', (chunk) => { body += chunk })
    res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, rawHeaders: res.rawHeaders, body }))
  })
  req.on('error', reject)
  req.end()
})

/** Every value of a header (node joins repeated headers, rawHeaders keeps them apart). */
const allValues = (response, name) => response.rawHeaders
  .filter((_, index) => index % 2 === 0)
  .map((header, index) => [header.toLowerCase(), response.rawHeaders[index * 2 + 1]])
  .filter(([header]) => header === name)
  .map(([, value]) => value)

const assertCacheControl = (response, expected) => {
  assert.deepEqual(allValues(response, 'cache-control'), [expected])
}

const assertSecurityHeaders = (response) => {
  for (const [name, value] of Object.entries(SECURITY_HEADERS)) {
    assert.deepEqual(allValues(response, name), [value], `${name} on ${response.status}`)
  }
}

const CASES = [
  {
    confFile: 'nginx-static-prod.conf',
    html: REVALIDATE,
    asset: 'public, max-age=31536000, immutable',
  },
  {
    confFile: 'nginx-static.conf',
    html: DEV_HTML,
    asset: 'public, max-age=3600',
  },
]

for (const { confFile, html, asset } of CASES) {
  describe(confFile, { skip: nginxInfo ? false : `binaire ${NGINX} introuvable` }, () => {
    let nginx
    before(async () => { nginx = await startNginx(confFile) })
    after(() => {
      nginx?.child.kill()
      if (nginx) rmSync(nginx.workDir, { recursive: true, force: true })
    })

    for (const { app, host } of SERVERS) {
      const get = (path) => request(nginx.port, host, path)

      test(`PWA-01 ${app} : index.html, / et une route profonde servent le HTML, revalidé`, async () => {
        for (const path of ['/index.html', '/', '/games/123']) {
          const response = await get(path)
          assert.equal(response.status, 200, path)
          assert.match(response.body, new RegExp(`<title>${app}</title>`), path)
          assertCacheControl(response, html)
          assert.deepEqual(allValues(response, 'expires'), [], `${path} sans Expires`)
        }
      })

      test(`PWA-02 ${app} : service worker, registre, workbox et manifest toujours revalidés`, async () => {
        for (const path of ['/sw.js', '/registerSW.js', '/workbox-4b126c97.js', '/manifest.webmanifest']) {
          const response = await get(path)
          assert.equal(response.status, 200, path)
          assertCacheControl(response, REVALIDATE)
        }
      })

      test(`PWA-03 ${app} : assets en cache long, chunk absent en 404`, async () => {
        for (const path of ['/_nuxt/v1/entry.js', '/pwa-192x192.png']) {
          const response = await get(path)
          assert.equal(response.status, 200, path)
          assertCacheControl(response, asset)
        }
        const missing = await get('/_nuxt/v0/gone.js')
        assert.equal(missing.status, 404)
        assert.doesNotMatch(missing.body, /<title>app/)
      })

      test(`PWA-04 ${app} : en-têtes de sécurité sur 200, 403 et 404`, async () => {
        const responses = await Promise.all(['/', '/games/123', '/sw.js', '/_nuxt/v1/entry.js', '/.env', '/_nuxt/v0/gone.js'].map(get))
        assert.deepEqual(responses.map((response) => response.status), [200, 200, 200, 200, 403, 404])
        responses.forEach(assertSecurityHeaders)
      })
    }
  })
}
