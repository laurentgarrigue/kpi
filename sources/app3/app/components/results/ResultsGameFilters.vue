<script setup lang="ts">
import type { GamedayOption } from '#kpi-layer/utils/results/games'

/**
 * Games filters (CMP-06, CMP-19): days as links keeping the other filters; gameday (championships) and « upcoming
 * games only » in a GET form, applied on change with JavaScript, by its button without.
 */
const props = defineProps<{
  gamedays?: GamedayOption[]
  dates: string[]
  gameday?: number
  day?: string
  upcoming: boolean
  hiddenFields?: Record<string, string>
}>()
const route = useRoute()
const form = useTemplateRef<HTMLFormElement>('form')
const language = useLanguageTag()

function shortDate(date: string | null, withWeekday = false): string {
  return date
    ? new Intl.DateTimeFormat(language.value, { ...(withWeekday ? { weekday: 'short' } : { month: 'short' }), day: 'numeric', timeZone: 'UTC' }).format(new Date(date))
    : ''
}

const days = computed(() => [undefined, ...props.dates].map(date => ({
  date,
  to: { path: route.path, query: { ...route.query, day: date } },
})))

function apply() {
  if (!form.value) {
    return
  }
  const query = Object.fromEntries([...new FormData(form.value)].filter(([, value]) => value !== '').map(([key, value]) => [key, String(value)]))
  navigateTo({ path: route.path, query })
}
</script>

<template>
  <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
    <nav v-if="dates.length > 1" :aria-label="$t('results.days')" data-testid="games-days">
      <ul class="flex flex-wrap overflow-hidden rounded-md border border-line">
        <li v-for="item in days" :key="item.date ?? 'all'" class="border-l border-line first:border-l-0">
          <NuxtLink
            :to="item.to"
            :aria-current="item.date === day ? 'true' : undefined"
            class="block px-3.5 py-1.5 text-sm capitalize hover:bg-wash"
            :class="{ 'bg-wash font-bold text-navy dark:text-ink': item.date === day }"
          >
            {{ item.date ? shortDate(item.date, true) : $t('results.allDaysShort') }}
          </NuxtLink>
        </li>
      </ul>
    </nav>
    <form ref="form" method="get" :action="route.path" class="flex flex-wrap items-center gap-x-5 gap-y-3" data-testid="games-filters" @change="apply" @submit.prevent="apply">
      <input v-for="(value, name) in hiddenFields" :key="name" type="hidden" :name="name" :value="value">
      <input v-if="day" type="hidden" name="day" :value="day">
      <label v-if="props.gamedays && props.gamedays.length > 1" class="flex items-center gap-2 text-sm">
        {{ $t('results.filters.gameday') }}
        <select name="gameday" class="rounded-md border border-line bg-surface px-2 py-1.5">
          <option value="">{{ $t('results.filters.all') }}</option>
          <option v-for="option in props.gamedays" :key="option.id" :value="option.id" :selected="option.id === gameday">
            {{ option.label }} — {{ option.place }} ({{ shortDate(option.start) }})
          </option>
        </select>
      </label>
      <label class="inline-flex cursor-pointer items-center gap-2 text-sm">
        <input type="checkbox" name="upcoming" value="1" :checked="upcoming" class="size-4 accent-navy">
        {{ $t('results.upcomingOnly') }}
      </label>
      <noscript>
        <button type="submit" class="rounded-md border border-line px-3 py-1 text-sm">{{ $t('results.filters.apply') }}</button>
      </noscript>
    </form>
  </div>
</template>
