<template>
  <div class="grid grid-cols-[auto_1fr] gap-x-2 px-3 pt-4 pb-3">
    <!-- Team A: above the line, number on top -->
    <div />
    <div class="relative" :style="{ height: `${timeline.laneCount.A * LANE_HEIGHT}px` }">
      <div
        v-for="marker in markersOf('A')"
        :key="marker.event.e_id"
        class="absolute flex flex-col items-center -translate-x-1/2"
        :style="{ left: `${marker.position}%`, bottom: `${marker.lane * LANE_HEIGHT + TICK_HEIGHT}px` }"
        :title="tooltip(marker.event, teamALabel)"
      >
        <span class="text-xs font-medium text-blue-700 dark:text-blue-300 leading-4">#{{ marker.event.e_number }}</span>
        <div class="h-7 flex items-center justify-center scale-75">
          <GameEventIcon :type="marker.event.e_type" />
        </div>
      </div>
    </div>

    <!-- Chrono + line: one labelled segment per period, width proportional to its duration -->
    <UIcon name="i-heroicons-clock" class="h-6 w-6 self-center text-gray-700 dark:text-gray-400" />
    <div class="relative h-6">
      <div
        v-for="segment in timeline.segments"
        :key="segment.period"
        class="absolute inset-y-0 flex items-center justify-center rounded-sm overflow-hidden text-[11px] font-medium text-white"
        :class="segment.period.startsWith('P') ? 'bg-amber-600 dark:bg-amber-500' : 'bg-slate-600 dark:bg-slate-500'"
        :style="segmentStyle(segment)"
        :title="t('Games.Period.' + segment.period)"
      >
        <span class="truncate pl-1 pr-2">{{ t('Games.Period.' + segment.period) }}</span>
      </div>
      <div
        v-for="marker in timeline.markers"
        :key="`tick-${marker.event.e_id}`"
        class="absolute w-0.5 -translate-x-1/2"
        :class="marker.event.e_team === 'A' ? 'bottom-full bg-blue-700 dark:bg-blue-300' : 'top-full bg-red-700 dark:bg-red-400'"
        :style="{ left: `${marker.position}%`, height: `${TICK_HEIGHT}px` }"
      />
    </div>

    <!-- Team B: below the line, number underneath -->
    <div />
    <div class="relative" :style="{ height: `${timeline.laneCount.B * LANE_HEIGHT}px` }">
      <div
        v-for="marker in markersOf('B')"
        :key="marker.event.e_id"
        class="absolute flex flex-col items-center -translate-x-1/2"
        :style="{ left: `${marker.position}%`, top: `${marker.lane * LANE_HEIGHT + TICK_HEIGHT}px` }"
        :title="tooltip(marker.event, teamBLabel)"
      >
        <div class="h-7 flex items-center justify-center scale-75">
          <GameEventIcon :type="marker.event.e_type" />
        </div>
        <span class="text-xs font-medium text-red-700 dark:text-red-400 leading-4">#{{ marker.event.e_number }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { buildHorizontalTimeline, formatEventTime } from '~/utils/gameTimeline'
import GameEventIcon from '~/components/GameEventIcon.vue'

// Height of one stacking lane: player number + icon
const LANE_HEIGHT = 44
// Length of the tick linking a marker to the line
const TICK_HEIGHT = 6
// Blank space on each side of a period segment, to separate periods
const SEGMENT_GAP_PX = 2
// Depth of the bevel on the right end of a segment, pointing in the direction of play
const BEVEL_PX = 8

const props = defineProps({
  events: { type: Array, required: true },
  currentPeriod: { type: String, default: null },
  teamALabel: { type: String, default: '' },
  teamBLabel: { type: String, default: '' }
})

const { t } = useI18n()

const timeline = computed(() => buildHorizontalTimeline(props.events, props.currentPeriod))

const markersOf = team => timeline.value.markers.filter(m => (team === 'A') === (m.event.e_team === 'A'))

const segmentStyle = segment => ({
  left: `calc(${segment.left}% + ${SEGMENT_GAP_PX}px)`,
  width: `calc(${segment.width}% - ${2 * SEGMENT_GAP_PX}px)`,
  clipPath: `polygon(0 0, calc(100% - ${BEVEL_PX}px) 0, 100% 50%, calc(100% - ${BEVEL_PX}px) 100%, 0 100%)`
})

const tooltip = (event, teamLabel) =>
  `${formatEventTime(event.e_time)} - #${event.e_number} ${event.e_firstname ?? ''} ${event.e_name ?? ''} (${teamLabel})`
</script>
