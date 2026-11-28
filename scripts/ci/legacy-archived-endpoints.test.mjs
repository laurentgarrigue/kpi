// ARCH-01 à ARCH-05 (DOC/developer/reference/LEGACY_ARCHIVED_ENDPOINTS.md) — `make ci_scripts_test`, job CI
// `tests-ci-scripts`.
//
// Les scripts PHP de sources/connector/ et sources/live/ sont archivés : fermés en HTTP (410) par une garde PHP,
// doublée d'un .htaccess. Le test fonctionnel démarre `php -S` sur sources/ (aucune base : un script qui passerait la
// garde échouerait ou répondrait autre chose que 410) ; il est ignoré si le binaire `php` est absent.
import { test, before, after, describe } from 'node:test'
import assert from 'node:assert/strict'
import { spawn, execFileSync } from 'node:child_process'
import { mkdtempSync, readdirSync, readFileSync, writeFileSync, rmSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import http from 'node:http'
import net from 'node:net'

const SOURCES = join(dirname(fileURLToPath(import.meta.url)), '../../sources')
const ARCHIVED_DIRS = ['connector', 'live']
const PHP = process.env.PHP_BIN ?? 'php'
const GONE = 410
const GUARD = "require_once __DIR__ . '/../commun/ArchivedEndpoint.php'; archivedEndpointGuard(__FILE__);"

const phpScripts = ARCHIVED_DIRS.flatMap((dir) =>
  readdirSync(join(SOURCES, dir))
    .filter((name) => name.endsWith('.php'))
    .map((name) => `${dir}/${name}`),
)

const phpAvailable = (() => {
  try {
    execFileSync(PHP, ['-v'], { stdio: 'ignore' })
    return true
  } catch {
    return false
  }
})()

/** Première instruction PHP du fichier, après l'éventuel shebang et la balise d'ouverture. */
const firstStatement = (source) =>
  source
    .replace(/^#!.*\n/, '')
    .replace(/^<\?php\s*/, '')
    .split('\n')
    .map((line) => line.trim())
    .find((line) => line !== '' && !line.startsWith('//'))

describe('ARCH-01 — garde en première instruction', () => {
  test('il y a des scripts à contrôler', () => {
    assert.ok(phpScripts.length > 10, `${phpScripts.length} scripts trouvés`)
  })
  for (const script of phpScripts) {
    test(script, () => {
      assert.equal(firstStatement(readFileSync(join(SOURCES, script), 'utf8')), GUARD)
    })
  }
})

describe('ARCH-05 — .htaccess', () => {
  test('connector/ refuse tout', () => {
    const conf = readFileSync(join(SOURCES, 'connector/.htaccess'), 'utf8')
    assert.match(conf, /^\s*Require all denied\s*$/m)
    assert.doesNotMatch(conf, /FilesMatch/)
  })
  test('live/ refuse les *.php', () => {
    const conf = readFileSync(join(SOURCES, 'live/.htaccess'), 'utf8')
    assert.match(conf, /<FilesMatch "\\\.php\$">\s*Require all denied\s*<\/FilesMatch>/)
  })
})

const freePort = () =>
  new Promise((resolve) => {
    const server = net.createServer()
    server.listen(0, '127.0.0.1', () => {
      const { port } = server.address()
      server.close(() => resolve(port))
    })
  })

const get = (port, path) =>
  new Promise((resolve, reject) => {
    http
      .get({ host: '127.0.0.1', port, path }, (res) => {
        let body = ''
        res.on('data', (chunk) => (body += chunk))
        res.on('end', () => resolve({ status: res.statusCode, body }))
      })
      .on('error', reject)
  })

/** Démarre `php -S` sur `root` et attend qu'il réponde. */
const startPhpServer = async (root) => {
  const port = await freePort()
  const child = spawn(PHP, ['-S', `127.0.0.1:${port}`, '-t', root], { stdio: 'ignore' })
  for (let attempt = 0; attempt < 50; attempt++) {
    try {
      await get(port, '/')
      return { port, child }
    } catch {
      await new Promise((resolve) => setTimeout(resolve, 100))
    }
  }
  child.kill()
  throw new Error(`php -S ne répond pas sur ${root}`)
}

describe('ARCH-02 à ARCH-04 — comportement de la garde', { skip: !phpAvailable && 'binaire php absent' }, () => {
  const guardPath = join(SOURCES, 'commun/ArchivedEndpoint.php')
  let sources
  let fixtures
  let fixtureDir

  before(async () => {
    // Fixture : un script archivé, et un script qui l'inclut comme le fait l'admin legacy
    // (admin/v2/* → live/create_cache_match.php, admin/GestionOperations.php → connector/replace_evenement.php).
    fixtureDir = mkdtempSync(join(tmpdir(), 'kpi-archived-'))
    writeFileSync(join(fixtureDir, 'archived.php'), `<?php\nrequire_once '${guardPath}'; archivedEndpointGuard(__FILE__);\necho 'executed';\n`)
    writeFileSync(join(fixtureDir, 'includer.php'), "<?php\ninclude __DIR__ . '/archived.php';\n")
    writeFileSync(join(fixtureDir, 'index.php'), '<?php\n')
    ;[sources, fixtures] = await Promise.all([startPhpServer(SOURCES), startPhpServer(fixtureDir)])
  })

  after(() => {
    sources?.child.kill()
    fixtures?.child.kill()
    rmSync(fixtureDir, { force: true, recursive: true })
  })

  for (const script of phpScripts) {
    test(`ARCH-02 — GET /${script} → 410`, async () => {
      const { status, body } = await get(sources.port, `/${script}?url=http://127.0.0.1/&evt=1&voie=1&action=start&lst=1`)
      assert.equal(status, GONE)
      assert.match(body, /archivé/)
    })
  }

  test('ARCH-02 — appelé directement, le script archivé ne s’exécute pas', async () => {
    const { status, body } = await get(fixtures.port, '/archived.php')
    assert.equal(status, GONE)
    assert.doesNotMatch(body, /executed/)
  })

  test('ARCH-03 — inclus par un autre script, il s’exécute', async () => {
    const { status, body } = await get(fixtures.port, '/includer.php')
    assert.equal(status, 200)
    assert.equal(body, 'executed')
  })

  test('ARCH-04 — la CLI passe', () => {
    const out = execFileSync(PHP, [join(fixtureDir, 'archived.php')], { encoding: 'utf8' })
    assert.equal(out, 'executed')
  })
})
