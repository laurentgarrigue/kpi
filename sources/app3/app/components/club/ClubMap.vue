<script setup lang="ts">
import type { ClubPosition } from '~/utils/clubs'

/**
 * Map of clubs (Q-P3-5, CLB-02): Leaflet is bundled with the site, but nothing is loaded — no script, no OpenStreetMap
 * tile — until the visitor clicks « Show the map ». Touching a dot selects its club (`select`), the `selected` one
 * stands out and the map flies to it, « Clubs near me » centres it on the visitor.
 */
const props = defineProps<{
  markers: { code: string, label: string, position: ClubPosition }[]
  selected?: string | null
}>()
const emit = defineEmits<{ select: [string] }>()
const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
const DEFAULT_ZOOM = 6
const CLUB_ZOOM = 11
const NEAR_ZOOM = 10
const FLIGHT_SECONDS = 0.6
const FRANCE = { lat: 46.6, lng: 2.4 }
// The visitor's choice to load the third-party tiles, kept on the device (CLB-09); storage may be unavailable.
const CHOICE_KEY = 'kpi.clubs.map'
const CHOICE_SHOWN = 'shown'

function rememberChoice(shown: boolean) {
  try {
    if (shown) {
      localStorage.setItem(CHOICE_KEY, CHOICE_SHOWN)
    }
    else {
      localStorage.removeItem(CHOICE_KEY)
    }
  }
  catch {
    // Not remembered: the map is asked for again next time (CLB-02).
  }
}

function choiceRemembered(): boolean {
  try {
    return localStorage.getItem(CHOICE_KEY) === CHOICE_SHOWN
  }
  catch {
    return false
  }
}

const { t } = useI18n()
const container = useTemplateRef<HTMLDivElement>('container')
const state = ref<'idle' | 'loading' | 'shown'>('idle')
const nearUnavailable = ref(false)
let map: import('leaflet').Map | null = null
const dots = new Map<string, import('leaflet').CircleMarker>()

/** Colours of the dots, from the charter tokens (no colour in the code). */
function dotStyle(on: boolean) {
  const token = (name: string) => getComputedStyle(document.documentElement).getPropertyValue(name).trim()
  return on
    ? { radius: 10, weight: 2, color: token('--color-live'), fillColor: token('--color-live'), fillOpacity: 1 }
    : { radius: 6, weight: 2, color: token('--color-navy'), fillColor: token('--color-sky'), fillOpacity: 0.85 }
}

function highlight(code: string | null | undefined, fly: boolean) {
  for (const [key, dot] of dots) {
    dot.setStyle(dotStyle(key === code))
  }
  const dot = code ? dots.get(code) : undefined
  dot?.bringToFront()
  if (fly && dot && map) {
    map.flyTo(dot.getLatLng(), CLUB_ZOOM, { duration: FLIGHT_SECONDS })
  }
}

async function show() {
  rememberChoice(true)
  state.value = 'loading'
  const [{ default: L }] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')])
  state.value = 'shown'
  await nextTick()
  if (!container.value) {
    return
  }
  map = L.map(container.value).setView([FRANCE.lat, FRANCE.lng], DEFAULT_ZOOM)
  L.tileLayer(TILES, { attribution: t('clubs.mapAttribution'), maxZoom: 18 }).addTo(map)
  for (const marker of props.markers) {
    // Circle markers: no marker image to host or to fetch.
    const dot = L.circleMarker([marker.position.lat, marker.position.lng], dotStyle(false)).addTo(map)
    dot.bindTooltip(marker.label, { direction: 'top' }).on('click', () => emit('select', marker.code))
    dots.set(marker.code, dot)
  }
  highlight(props.selected, true)
}

function nearMe() {
  navigator.geolocation?.getCurrentPosition(
    position => map?.flyTo([position.coords.latitude, position.coords.longitude], NEAR_ZOOM, { duration: FLIGHT_SECONDS }),
    () => { nearUnavailable.value = true },
  )
}

function hide() {
  rememberChoice(false)
  map?.remove()
  map = null
  dots.clear()
  nearUnavailable.value = false
  state.value = 'idle'
}

watch(() => props.selected, code => highlight(code, true))
onMounted(() => {
  if (choiceRemembered()) {
    show()
  }
})
onBeforeUnmount(() => map?.remove())
</script>

<template>
  <div class="relative h-full" data-testid="club-map">
    <div v-if="state !== 'shown'" class="grid h-full place-content-center gap-3 rounded-lg border border-line bg-wash p-6 text-center">
      <p class="max-w-[40ch] text-sm">{{ $t('clubs.mapNotice') }}</p>
      <UButton :loading="state === 'loading'" icon="i-heroicons-map" class="justify-self-center" data-testid="show-map" @click="show">{{ $t('clubs.showMap') }}</UButton>
    </div>
    <div v-show="state === 'shown'" ref="container" role="region" :aria-label="$t('clubs.mapLabel')" class="z-0 h-full min-h-[28rem] w-full rounded-lg border border-line" />
    <p v-if="state === 'shown'" class="absolute top-3 right-3 z-10 flex gap-3 rounded-md bg-surface px-3 py-1.5 text-sm shadow">
      <button v-if="!nearUnavailable" type="button" class="text-kpi-blue-700 underline" @click="nearMe">{{ $t('clubs.nearMe') }}</button>
      <span v-else>{{ $t('clubs.nearUnavailable') }}</span>
      <button type="button" class="text-kpi-blue-700 underline" data-testid="hide-map" @click="hide">{{ $t('clubs.hideMap') }}</button>
    </p>
  </div>
</template>
