<script setup lang="ts">
import type { ClubPosition } from '~/utils/clubs'

// Map of clubs (Q-P3-5): Leaflet is bundled with the site, but nothing is loaded — no script, no
// OpenStreetMap tile — until the visitor clicks « Show the map » (CLB-02). The list stays available.
const props = defineProps<{ markers: { code: string, label: string, position: ClubPosition }[], zoom?: number }>()
const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'
const DEFAULT_ZOOM = 6
const FRANCE = { lat: 46.6, lng: 2.4 }

const localePath = useLocalePath()
const { t } = useI18n()
const container = useTemplateRef<HTMLDivElement>('container')
const state = ref<'idle' | 'loading' | 'shown'>('idle')
let map: import('leaflet').Map | null = null

async function show() {
  state.value = 'loading'
  const [{ default: L }] = await Promise.all([import('leaflet'), import('leaflet/dist/leaflet.css')])
  state.value = 'shown'
  await nextTick()
  if (!container.value) {
    return
  }
  const center = props.markers.length === 1 ? props.markers[0]!.position : FRANCE
  map = L.map(container.value).setView([center.lat, center.lng], props.zoom ?? DEFAULT_ZOOM)
  L.tileLayer(TILES, { attribution: t('clubs.mapAttribution'), maxZoom: 18 }).addTo(map)
  for (const marker of props.markers) {
    const link = document.createElement('a')
    link.href = localePath(`/clubs/${marker.code}`)
    link.textContent = marker.label
    // Circle markers: no marker image to host or to fetch.
    L.circleMarker([marker.position.lat, marker.position.lng], { radius: 7, weight: 2 }).bindPopup(link).addTo(map)
  }
}

onBeforeUnmount(() => map?.remove())
</script>

<template>
  <div data-testid="club-map">
    <div v-if="state !== 'shown'" class="space-y-2 rounded border border-line p-4">
      <p class="text-sm">{{ $t('clubs.mapNotice') }}</p>
      <UButton :loading="state === 'loading'" icon="i-heroicons-map" data-testid="show-map" @click="show">{{ $t('clubs.showMap') }}</UButton>
    </div>
    <div v-show="state === 'shown'" ref="container" role="region" :aria-label="$t('clubs.mapLabel')" class="h-[28rem] w-full rounded border border-line" />
  </div>
</template>
