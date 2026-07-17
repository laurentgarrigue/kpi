<script setup lang="ts">
import { useAuthStore } from '~/stores/authStore'
import type { WorkerConfig, WorkerEvent, WorkerDate, WorkerMonitor, WorkerForm } from '~/types/eventWorker'

definePageMeta({ layout: 'admin', middleware: 'auth' })

const { t } = useI18n()
const api = useApi()
const authStore = useAuthStore()
const toast = useToast()

if (authStore.profile > 2) navigateTo('/')

// ─── State ───
const events = ref<WorkerEvent[]>([])
const dates = ref<WorkerDate[]>([])
const configs = ref<WorkerConfig[]>([])
const form = ref<WorkerForm>({
  idEvent: null,
  dateEvent: new Date().toISOString().slice(0, 10),
  hourEvent: '',
  offsetEvent: 15,
  pitchEvent: 4,
  delayEvent: 10,
})
// When checked, the initial time is captured at worker start (avoids any drift with
// the real wall-clock time in a genuine live event).
const useCurrentTime = ref(false)
const monitorOpen = ref(false)
const monitorConfig = ref<WorkerConfig | null>(null)
const monitorData = ref<WorkerMonitor | null>(null)
const monitorLastUpdate = ref<string>('')
const confirmStopOpen = ref(false)
const confirmStopAll = ref(false)
const confirmStopIdEvent = ref<number | null>(null)
// Wall-clock ref ticked every second while the monitor is open, so the displayed
// simulated clock advances smoothly instead of jumping with the loadStatus cadence.
const nowTick = ref(Date.now())

let statusTimer: ReturnType<typeof setInterval> | null = null
let monitorTimer: ReturnType<typeof setInterval> | null = null
let clockTimer: ReturnType<typeof setInterval> | null = null

// ─── Computed ───
const hasRunning = computed(() => configs.value.some(c => c.isRunning))

// Simulated event clock = initial event time + real seconds elapsed since createdAt.
// Mirrors AdminEventWorkerController::enrichConfig so the monitor stays in sync with
// the JSON the worker daemon generates, and keeps ticking between backend refreshes.
// Returns the simulated moment as a full Date so the date rolls over across the event's
// match days (a multi-day event only advanced the hour before).
function simulatedDate(cfg: WorkerConfig): Date {
  const started = new Date(cfg.createdAt.replace(' ', 'T')).getTime()
  const initial = new Date(`${cfg.dateEvent}T${cfg.hourEventInitial}`).getTime()
  const elapsedMs = Math.max(0, nowTick.value - started)
  return new Date(initial + elapsedMs)
}

function pad(n: number): string {
  return String(n).padStart(2, '0')
}

function dateToYmd(d: Date): string {
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

function dateToHhmm(d: Date): string {
  return `${pad(d.getHours())}:${pad(d.getMinutes())}`
}

// Add the warm-up offset (minutes) to a simulated Date and format as HH:mm.
function offsetHhmm(d: Date, offsetMin: number): string {
  const shifted = new Date(d.getTime() + offsetMin * 60000)
  return dateToHhmm(shifted)
}

const monitorClock = computed(() => {
  if (!monitorConfig.value) return null
  const live = configs.value.find(c => c.idEvent === monitorConfig.value!.idEvent) ?? monitorConfig.value
  const current = simulatedDate(live)
  return {
    currentDate: dateToYmd(current),
    currentTime: dateToHhmm(current),
    workingTime: offsetHhmm(current, live.offsetEvent),
  }
})

function eventLabel(idEvent: number): string {
  const e = events.value.find(e => e.id === idEvent)
  return e ? e.libelle : ''
}

// ─── API calls ───
async function loadEvents() {
  try { events.value = await api.get<WorkerEvent[]>('/admin/tv/events') } catch { /* handled by useApi */ }
}

async function loadDates(idEvent: number) {
  try { dates.value = await api.get<WorkerDate[]>(`/admin/events/worker/${idEvent}/dates`) } catch { /* handled by useApi */ }
}

async function loadStatus() {
  try { configs.value = await api.get<WorkerConfig[]>('/admin/events/worker/status') } catch { /* handled by useApi */ }
}

async function startWorker() {
  // Capture the current date & time at start when "current time" is checked, so the
  // simulated clock matches the real wall-clock (true live) with no drift.
  if (useCurrentTime.value) {
    const now = new Date()
    form.value.dateEvent = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
    form.value.hourEvent = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  }
  if (!form.value.idEvent || !form.value.dateEvent || !form.value.hourEvent) {
    toast.add({ title: t('eventCacheManager.errors.missing_fields'), color: 'warning' })
    return
  }
  await api.post('/admin/events/worker/start', form.value)
  toast.add({ title: t('eventCacheManager.toasts.started'), color: 'success' })
  await loadStatus()
}

async function pauseWorker(idEvent: number) {
  await api.post(`/admin/events/worker/${idEvent}/pause`)
  toast.add({ title: t('eventCacheManager.toasts.paused'), color: 'info' })
  await loadStatus()
}

async function resumeWorker(idEvent: number) {
  await api.post(`/admin/events/worker/${idEvent}/resume`)
  toast.add({ title: t('eventCacheManager.toasts.resumed'), color: 'success' })
  await loadStatus()
}

function askStopWorker(idEvent: number) {
  confirmStopIdEvent.value = idEvent
  confirmStopAll.value = false
  confirmStopOpen.value = true
}

function askStopAll() {
  confirmStopAll.value = true
  confirmStopIdEvent.value = null
  confirmStopOpen.value = true
}

async function confirmStop() {
  confirmStopOpen.value = false
  if (confirmStopAll.value) {
    await api.post('/admin/events/worker/stop')
    toast.add({ title: t('eventCacheManager.toasts.stop_all_done'), color: 'success' })
  } else if (confirmStopIdEvent.value) {
    await api.post(`/admin/events/worker/${confirmStopIdEvent.value}/stop`)
    toast.add({ title: t('eventCacheManager.toasts.stopped'), color: 'success' })
  }
  await loadStatus()
}

// ─── Monitor modal ───
async function openMonitor(c: WorkerConfig) {
  monitorConfig.value = c
  monitorOpen.value = true
  nowTick.value = Date.now()
  await refreshMonitor()
  if (monitorTimer) clearInterval(monitorTimer)
  monitorTimer = setInterval(refreshMonitor, c.delayEvent * 1000)
  if (clockTimer) clearInterval(clockTimer)
  clockTimer = setInterval(() => { nowTick.value = Date.now() }, 1000)
}

async function refreshMonitor() {
  if (!monitorConfig.value) return
  // Read the freshest config from `configs` (refreshed every 5s by loadStatus) so the
  // simulated clock keeps advancing; monitorConfig is only a snapshot from modal open.
  const live = configs.value.find(c => c.idEvent === monitorConfig.value!.idEvent) ?? monitorConfig.value
  try {
    // Client-computed simulated moment so the match selection matches the clock shown
    // in the footer and keeps advancing (date + hour) even if the backend snapshot lags.
    const sim = simulatedDate(live)
    monitorData.value = await api.get<WorkerMonitor>(
      `/admin/events/worker/${live.idEvent}/monitor`,
      {
        dateEvent: dateToYmd(sim),
        hourEvent: dateToHhmm(sim),
        offsetEvent: live.offsetEvent,
        pitchEvent: live.pitchEvent,
      }
    )
    monitorLastUpdate.value = new Date().toLocaleTimeString()
  } catch { /* handled by useApi */ }
}

function closeMonitor() {
  monitorOpen.value = false
  monitorConfig.value = null
  monitorData.value = null
  if (monitorTimer) { clearInterval(monitorTimer); monitorTimer = null }
  if (clockTimer) { clearInterval(clockTimer); clockTimer = null }
}

function setQuickDate(d: WorkerDate) {
  form.value.dateEvent = d.dateMatch
  form.value.hourEvent = d.heureMatch.slice(0, 5)
}

function statusBadgeClass(c: WorkerConfig): string {
  if (c.isRunning) return 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300'
  if (c.isPaused) return 'bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300'
  return 'bg-header-200 dark:bg-header-700 text-header-600 dark:text-header-300'
}

// ─── Watchers ───
watch(() => form.value.idEvent, async (id) => {
  dates.value = []
  if (id) await loadDates(id)
})

// ─── Lifecycle ───
onMounted(async () => {
  const now = new Date()
  form.value.hourEvent = `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
  await Promise.all([loadEvents(), loadStatus()])
  statusTimer = setInterval(loadStatus, 5000)
})

onBeforeUnmount(() => {
  if (statusTimer) clearInterval(statusTimer)
  if (monitorTimer) clearInterval(monitorTimer)
  if (clockTimer) clearInterval(clockTimer)
})
</script>

<template>
  <div class="px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">{{ t('eventCacheManager.title') }}</h1>
      <p class="mt-1 text-sm text-header-600 dark:text-header-300">{{ t('eventCacheManager.subtitle') }}</p>
    </div>

    <!-- Active workers -->
    <div class="bg-white dark:bg-header-900 border border-header-200 dark:border-header-700 rounded-xl mb-6">
      <div class="flex items-center justify-between px-5 py-4 border-b border-header-200 dark:border-header-700">
        <h2 class="text-base font-semibold text-header-900 dark:text-header-50">{{ t('eventCacheManager.active_workers.title') }}</h2>
        <button
          v-if="hasRunning"
          type="button"
          class="px-3 py-1.5 text-sm font-medium text-red-600 dark:text-red-300 bg-red-50 dark:bg-red-950 rounded-lg hover:bg-red-100 dark:hover:bg-red-900 transition-colors flex items-center gap-1"
          @click="askStopAll"
        >
          <UIcon name="heroicons:stop-circle" class="w-4 h-4" />
          {{ t('eventCacheManager.active_workers.stop_all') }}
        </button>
      </div>

      <div v-if="configs.length === 0" class="px-5 py-10 text-center text-header-600 dark:text-header-300">
        {{ t('eventCacheManager.active_workers.none') }}
      </div>

      <div v-else class="divide-y divide-header-100 dark:divide-header-800">
        <div v-for="c in configs" :key="c.id" class="px-5 py-4 flex flex-wrap items-start justify-between gap-4">
          <!-- Info -->
          <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-2">
              <span class="font-semibold text-header-900 dark:text-header-50">Event #{{ c.idEvent }}</span>
              <span v-if="eventLabel(c.idEvent)" class="text-sm text-header-600 dark:text-header-300">{{ eventLabel(c.idEvent) }}</span>
              <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', statusBadgeClass(c)]">
                {{ t(`eventCacheManager.status.${c.status}`) }}
              </span>
              <span :class="['text-xs font-medium px-2 py-0.5 rounded-full flex items-center gap-1', c.isHealthy ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300']">
                <UIcon :name="c.isHealthy ? 'heroicons:check-circle' : 'heroicons:exclamation-triangle'" class="w-3 h-3" />
                {{ c.isHealthy ? t('eventCacheManager.active_workers.healthy') : t('eventCacheManager.active_workers.unhealthy') }}
              </span>
            </div>
            <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-sm sm:grid-cols-4">
              <div><span class="text-header-600 dark:text-header-300">{{ t('eventCacheManager.active_workers.date') }} : </span><span class="text-header-900 dark:text-header-50">{{ c.dateEvent }}</span></div>
              <div><span class="text-header-600 dark:text-header-300">{{ t('eventCacheManager.active_workers.initial_time') }} : </span><span class="text-header-900 dark:text-header-50">{{ c.hourEventInitial.slice(0, 5) }}</span></div>
              <div><span class="text-header-600 dark:text-header-300">{{ t('eventCacheManager.active_workers.current_time') }} : </span><span class="font-medium text-primary-600 dark:text-primary-300"><template v-if="c.currentSimulatedDate && c.currentSimulatedDate !== c.dateEvent">{{ c.currentSimulatedDate }} </template>{{ c.currentSimulatedTime.slice(0, 5) }}</span></div>
              <div><span class="text-header-600 dark:text-header-300">{{ t('eventCacheManager.active_workers.pitches') }} : </span><span class="text-header-900 dark:text-header-50">{{ c.pitchEvent }}</span></div>
              <div><span class="text-header-600 dark:text-header-300">{{ t('eventCacheManager.active_workers.warmup') }} : </span><span class="text-header-900 dark:text-header-50">{{ c.offsetEvent }} min</span></div>
              <div><span class="text-header-600 dark:text-header-300">{{ t('eventCacheManager.active_workers.delay') }} : </span><span class="text-header-900 dark:text-header-50">{{ c.delayEvent }}s</span></div>
              <div><span class="text-header-600 dark:text-header-300">{{ t('eventCacheManager.active_workers.executions') }} : </span><span class="text-header-900 dark:text-header-50">{{ c.executionCount }}</span></div>
              <div v-if="c.lastExecution"><span class="text-header-600 dark:text-header-300">{{ t('eventCacheManager.active_workers.last_execution') }} : </span><span class="text-header-900 dark:text-header-50">{{ c.lastExecution.slice(11, 19) }}</span></div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex flex-wrap gap-2">
            <button type="button" class="px-3 py-1.5 text-sm font-medium text-primary-600 dark:text-primary-300 bg-primary-50 dark:bg-primary-950 rounded-lg hover:bg-primary-100 dark:hover:bg-primary-900 transition-colors flex items-center gap-1" @click="openMonitor(c)">
              <UIcon name="heroicons:magnifying-glass" class="w-4 h-4" />
              {{ t('eventCacheManager.active_workers.monitor') }}
            </button>
            <button v-if="c.isRunning" type="button" class="px-3 py-1.5 text-sm font-medium text-yellow-600 dark:text-yellow-300 bg-yellow-50 dark:bg-yellow-950 rounded-lg hover:bg-yellow-100 dark:hover:bg-yellow-900 transition-colors flex items-center gap-1" @click="pauseWorker(c.idEvent)">
              <UIcon name="heroicons:pause" class="w-4 h-4" />
              {{ t('eventCacheManager.active_workers.pause') }}
            </button>
            <button v-if="c.isPaused" type="button" class="px-3 py-1.5 text-sm font-medium text-green-600 dark:text-green-300 bg-green-50 dark:bg-green-950 rounded-lg hover:bg-green-100 dark:hover:bg-green-900 transition-colors flex items-center gap-1" @click="resumeWorker(c.idEvent)">
              <UIcon name="heroicons:play" class="w-4 h-4" />
              {{ t('eventCacheManager.active_workers.resume') }}
            </button>
            <button type="button" class="px-3 py-1.5 text-sm font-medium text-red-600 dark:text-red-300 bg-red-50 dark:bg-red-950 rounded-lg hover:bg-red-100 dark:hover:bg-red-900 transition-colors flex items-center gap-1" @click="askStopWorker(c.idEvent)">
              <UIcon name="heroicons:x-circle" class="w-4 h-4" />
              {{ t('eventCacheManager.active_workers.stop') }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- New configuration form -->
    <div class="bg-white dark:bg-header-900 border border-header-200 dark:border-header-700 rounded-xl">
      <div class="px-5 py-4 border-b border-header-200 dark:border-header-700">
        <h2 class="text-base font-semibold text-header-900 dark:text-header-50">{{ t('eventCacheManager.form.title') }}</h2>
      </div>

      <div class="px-5 py-4 space-y-4">
        <!-- Event select -->
        <div>
          <label class="block text-xs font-medium text-header-600 dark:text-header-300 mb-1">{{ t('eventCacheManager.form.event') }}</label>
          <select
            v-model="form.idEvent"
            class="w-full px-3 py-2 text-sm border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
          >
            <option :value="null">{{ t('eventCacheManager.form.event_placeholder') }}</option>
            <option v-for="e in events" :key="e.id" :value="e.id">#{{ e.id }} — {{ e.libelle }} — {{ e.lieu }}</option>
          </select>
        </div>

        <!-- Quick date buttons -->
        <div v-if="dates.length > 0">
          <p class="mb-2 text-xs font-medium text-header-600 dark:text-header-300">{{ t('eventCacheManager.form.quick_date') }}</p>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="d in dates"
              :key="d.dateMatch"
              type="button"
              class="px-2 py-1 text-xs font-medium text-primary-600 dark:text-primary-300 bg-primary-50 dark:bg-primary-950 border border-primary-200 dark:border-primary-900 rounded hover:bg-primary-100 dark:hover:bg-primary-900 transition-colors"
              @click="setQuickDate(d)"
            >
              {{ d.dateMatch }} ({{ d.heureMatch.slice(0, 5) }})
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <div>
            <label class="block text-xs font-medium text-header-600 dark:text-header-300 mb-1">{{ t('eventCacheManager.form.date') }}</label>
            <input v-if="!useCurrentTime" v-model="form.dateEvent" type="date" class="w-full px-3 py-2 text-sm border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" >
            <span v-else class="text-sm text-header-900 dark:text-header-50 block px-3 py-2">{{ t('eventCacheManager.form.date_now_help') }}</span>
          </div>
          <div>
            <label class="block text-xs font-medium text-header-600 dark:text-header-300 mb-1">{{ t('eventCacheManager.form.hour') }}</label>
            <label class="flex items-center gap-2 mb-1.5 text-sm text-header-900 dark:text-header-50 cursor-pointer">
              <input v-model="useCurrentTime" type="checkbox" class="rounded border-header-300 dark:border-header-700 text-primary-600 focus:ring-primary-500" >
              {{ t('eventCacheManager.form.hour_now') }}
            </label>
            <input v-if="!useCurrentTime" v-model="form.hourEvent" type="time" class="w-full px-3 py-2 text-sm border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" >
            <span class="text-xs text-header-600 dark:text-header-300 mt-0.5 block">{{ useCurrentTime ? t('eventCacheManager.form.hour_now_help') : t('eventCacheManager.form.hour_help') }}</span>
          </div>
          <div>
            <label class="block text-xs font-medium text-header-600 dark:text-header-300 mb-1">{{ t('eventCacheManager.form.offset') }}</label>
            <input v-model.number="form.offsetEvent" type="number" min="0" max="120" class="w-full px-3 py-2 text-sm border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" >
          </div>
          <div>
            <label class="block text-xs font-medium text-header-600 dark:text-header-300 mb-1">{{ t('eventCacheManager.form.pitch') }}</label>
            <input v-model.number="form.pitchEvent" type="number" min="1" max="20" class="w-full px-3 py-2 text-sm border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" >
          </div>
          <div>
            <label class="block text-xs font-medium text-header-600 dark:text-header-300 mb-1">{{ t('eventCacheManager.form.delay') }}</label>
            <input v-model.number="form.delayEvent" type="number" min="5" max="60" class="w-full px-3 py-2 text-sm border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" >
          </div>
        </div>

        <div class="pt-2">
          <button
            type="button"
            class="px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition-colors flex items-center gap-2"
            @click="startWorker"
          >
            <UIcon name="heroicons:play-circle" class="w-4 h-4" />
            {{ t('eventCacheManager.form.start') }}
          </button>
        </div>
      </div>
    </div>

    <!-- Stop confirmation modal -->
    <AdminModal
      :open="confirmStopOpen"
      :title="confirmStopAll ? t('eventCacheManager.confirm.stop_all') : t('eventCacheManager.confirm.stop_one', { id: confirmStopIdEvent })"
      max-width="md"
      @close="confirmStopOpen = false"
    >
      <template #footer>
        <button type="button" class="px-4 py-2 text-sm font-medium text-header-900 dark:text-header-50 bg-header-200 dark:bg-header-700 rounded-lg hover:bg-header-200 dark:hover:bg-header-700 transition-colors" @click="confirmStopOpen = false">
          {{ t('common.cancel') }}
        </button>
        <button type="button" class="px-4 py-2 text-sm font-medium text-white bg-red-600 dark:bg-red-700 rounded-lg hover:bg-red-700 dark:hover:bg-red-600 transition-colors" @click="confirmStop">
          {{ confirmStopAll ? t('eventCacheManager.active_workers.stop_all') : t('eventCacheManager.active_workers.stop') }}
        </button>
      </template>
    </AdminModal>

    <!-- Monitor modal -->
    <AdminModal :open="monitorOpen" max-width="3xl" @close="closeMonitor">
      <template #header>
        <div v-if="monitorConfig">
          <h3 class="text-base font-semibold text-header-900 dark:text-header-50">
            {{ t('eventCacheManager.monitor.title') }} — {{ t('eventCacheManager.monitor.event') }} #{{ monitorConfig.idEvent }}
          </h3>
          <p class="mt-0.5 text-sm text-header-600 dark:text-header-300">
            {{ t('eventCacheManager.monitor.date') }} {{ monitorConfig.dateEvent }} ·
            {{ t('eventCacheManager.monitor.initial_time') }} {{ monitorConfig.hourEventInitial.slice(0, 5) }} ·
            {{ t('eventCacheManager.monitor.refresh_every', { seconds: monitorConfig.delayEvent }) }}
          </p>
        </div>
      </template>

      <div v-if="monitorConfig">
          <!-- Monitor content -->
          <div>
            <div v-if="monitorData">
              <table class="w-full text-sm">
                <thead>
                  <tr class="border-b border-header-200 dark:border-header-700 text-left">
                    <th class="pb-2 pr-4 font-semibold text-header-900 dark:text-header-50">{{ t('eventCacheManager.monitor.pitch') }}</th>
                    <th class="pb-2 pr-4 font-semibold text-header-900 dark:text-header-50">{{ t('eventCacheManager.monitor.current_game') }}</th>
                    <th class="pb-2 font-semibold text-header-900 dark:text-header-50">{{ t('eventCacheManager.monitor.next_game') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="p in monitorData.pitches" :key="p.pitch" class="border-b border-header-100 dark:border-header-800">
                    <td class="py-2.5 pr-4 font-semibold text-header-900 dark:text-header-50">{{ p.pitch }}</td>
                    <td class="py-2.5 pr-4">
                      <span v-if="p.game" class="font-medium text-header-900 dark:text-header-50">
                        {{ p.time?.slice(0, 5) }} · #{{ p.num }} · ID {{ p.game }}
                      </span>
                      <span v-else class="italic text-header-600 dark:text-header-300">{{ t('eventCacheManager.monitor.waiting') }}</span>
                    </td>
                    <td class="py-2.5">
                      <span v-if="p.next.id" class="text-header-900 dark:text-header-50">
                        {{ p.next.time?.slice(0, 5) }} · #{{ p.next.num }} · ID {{ p.next.id }}
                      </span>
                      <span v-else class="italic text-header-600 dark:text-header-300">{{ t('eventCacheManager.monitor.waiting') }}</span>
                    </td>
                  </tr>
                </tbody>
              </table>
              <div class="mt-3 flex items-center justify-between text-xs text-header-600 dark:text-header-300 pt-2 border-t border-header-100 dark:border-header-800">
                <span v-if="monitorClock">
                  {{ t('eventCacheManager.monitor.time_current') }} <template v-if="monitorConfig && monitorClock.currentDate !== monitorConfig.dateEvent">{{ monitorClock.currentDate }} </template>{{ monitorClock.currentTime }} ·
                  {{ t('eventCacheManager.monitor.time_working') }} {{ monitorClock.workingTime }}
                </span>
                <span v-if="monitorLastUpdate">{{ t('eventCacheManager.monitor.last_update', { time: monitorLastUpdate }) }}</span>
              </div>
            </div>
            <div v-else class="py-6 text-center text-header-600 dark:text-header-300">
              {{ t('eventCacheManager.monitor.load_failed') }}
            </div>
          </div>
      </div>
    </AdminModal>
  </div>
</template>
