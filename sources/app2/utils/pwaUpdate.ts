/**
 * What the « reload » button of the update banner must do — DOC/specs/PWA_AUTO_UPDATE.md, PWA-06b.
 *
 * `SKIP_WAITING` only helps when a worker waits AND controls-to-be the page: the reload is then triggered by
 * the `controlling` event. On a first visit (no `clientsClaim`, the page is not controlled) the next build's
 * worker activates on its own, vite-plugin-pwa still raises `needRefresh`, and that event never comes: a plain
 * reload is the only way to load the new build.
 */
export type PwaUpdateAction = 'activate-waiting-worker' | 'reload'

export const updateActionFor = ({ hasWaitingWorker, isControlled }: {
  hasWaitingWorker: boolean
  isControlled: boolean
}): PwaUpdateAction => (hasWaitingWorker && isControlled ? 'activate-waiting-worker' : 'reload')
