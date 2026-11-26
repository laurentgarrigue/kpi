// usePwa (composables/usePwa.ts) — DOC/specs/PWA_AUTO_UPDATE.md §4, PWA-06 / PWA-06b / PWA-08 / PWA-11.
// Each test loads a fresh copy of the composable: its registration is a per-document singleton.
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick, ref } from 'vue'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'

const isOnline = ref(true)
mockNuxtImport('useOnlineStatus', () => () => ({ isOnline, checkConnection: () => isOnline.value }))

type RegisterOptions = { onRegistered?: (reg: ServiceWorkerRegistration | undefined) => void }
const pwa = vi.hoisted(() => ({
  options: [] as RegisterOptions[],
  needRefresh: null as unknown as { value: boolean },
  updateServiceWorker: null as unknown as ReturnType<typeof vi.fn>,
}))
vi.mock('virtual:pwa-register/vue', async () => {
  const { ref: vueRef } = await import('vue')
  return {
    useRegisterSW: (options: RegisterOptions) => {
      pwa.options.push(options)
      pwa.needRefresh = vueRef(false)
      pwa.updateServiceWorker = vi.fn(async () => {})
      return { needRefresh: pwa.needRefresh, offlineReady: vueRef(false), updateServiceWorker: pwa.updateServiceWorker }
    },
  }
})

const fakeRegistration = (waiting = false) =>
  ({ update: vi.fn(async () => {}), waiting: waiting ? {} : null }) as unknown as ServiceWorkerRegistration

const stubServiceWorker = ({ registration = fakeRegistration(), controlled = true } = {}) => {
  Object.defineProperty(navigator, 'serviceWorker', {
    configurable: true,
    value: { ready: Promise.resolve(registration), controller: controlled ? {} : null },
  })
  return registration
}

const loadUsePwa = async () => (await import('../../composables/usePwa')).usePwa
const flush = async () => {
  for (let i = 0; i < 5; i++) await new Promise((resolve) => setTimeout(resolve, 0))
}
const reload = vi.fn()

beforeEach(() => {
  vi.resetModules()
  pwa.options.length = 0
  isOnline.value = true
  vi.stubEnv('PROD', true)
  vi.stubGlobal('location', { ...window.location, reload })
  reload.mockClear()
})

afterEach(() => {
  vi.unstubAllEnvs()
  vi.unstubAllGlobals()
  vi.useRealTimers()
})

describe('usePwa', () => {
  it('registers the worker once, however many components call it', async () => {
    stubServiceWorker()
    const usePwa = await loadUsePwa()
    usePwa()
    usePwa()
    await flush()
    expect(pwa.options).toHaveLength(1)
  })

  it('PWA-11: registers no worker in dev', async () => {
    vi.stubEnv('PROD', false)
    stubServiceWorker()
    ;(await loadUsePwa())()
    await flush()
    expect(pwa.options).toHaveLength(0)
  })

  it('PWA-06: raises needRefresh when a new worker is waiting', async () => {
    stubServiceWorker()
    const { needRefresh } = (await loadUsePwa())()
    await flush()
    expect(needRefresh.value).toBe(false)
    pwa.needRefresh.value = true
    await nextTick()
    expect(needRefresh.value).toBe(true)
  })

  it('PWA-06: raises needRefresh for a worker already waiting when the page loads', async () => {
    stubServiceWorker({ registration: fakeRegistration(true) })
    const { needRefresh } = (await loadUsePwa())()
    await flush()
    expect(needRefresh.value).toBe(true)
  })

  it('PWA-08: re-checks the worker when the tab comes back, when back online and every hour', async () => {
    vi.useFakeTimers({ toFake: ['setInterval'] })
    const registration = stubServiceWorker()
    ;(await loadUsePwa())()
    await flush()
    pwa.options[0]!.onRegistered?.(registration)

    document.dispatchEvent(new Event('visibilitychange'))
    expect(registration.update).toHaveBeenCalledTimes(1)

    isOnline.value = false
    await nextTick()
    isOnline.value = true
    await nextTick()
    expect(registration.update).toHaveBeenCalledTimes(2)

    vi.advanceTimersByTime(60 * 60 * 1000)
    expect(registration.update).toHaveBeenCalledTimes(3)
  })

  it('PWA-06b: « reload » activates the waiting worker when the page is controlled', async () => {
    stubServiceWorker({ registration: fakeRegistration(true), controlled: true })
    const { updateApp } = (await loadUsePwa())()
    await flush()
    await updateApp()
    expect(pwa.updateServiceWorker).toHaveBeenCalledWith(true)
    expect(reload).not.toHaveBeenCalled()
  })

  it('PWA-06b: « reload » simply reloads on a first visit (page not controlled)', async () => {
    stubServiceWorker({ registration: fakeRegistration(false), controlled: false })
    const { updateApp } = (await loadUsePwa())()
    await flush()
    await updateApp()
    expect(pwa.updateServiceWorker).not.toHaveBeenCalled()
    expect(reload).toHaveBeenCalledOnce()
  })
})
