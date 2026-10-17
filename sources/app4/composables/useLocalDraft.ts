import { draftStorageKey } from '~/utils/cms'

const AUTOSAVE_MS = 30_000

/**
 * Local draft of a content being edited (PAGE_CMS.md § 2): saved in the browser every 30 s, offered again when the
 * screen is reopened, cleared after a successful save.
 */
export function useLocalDraft<T>(kind: 'article' | 'page', id: () => number | 'new', value: () => T) {
  const key = () => draftStorageKey(kind, id())
  let timer: ReturnType<typeof setInterval> | undefined

  const read = (): { savedAt: string, value: T } | null => {
    try {
      const raw = localStorage.getItem(key())
      return raw ? JSON.parse(raw) : null
    } catch {
      return null
    }
  }

  const clear = () => {
    try { localStorage.removeItem(key()) } catch { /* storage unavailable */ }
  }

  onMounted(() => {
    timer = setInterval(() => {
      try { localStorage.setItem(key(), JSON.stringify({ savedAt: new Date().toISOString(), value: value() })) } catch { /* storage unavailable */ }
    }, AUTOSAVE_MS)
  })
  onBeforeUnmount(() => clearInterval(timer))

  return { read, clear }
}
