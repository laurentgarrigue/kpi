<script setup lang="ts">
import type { GamedayOption } from '#kpi-layer/utils/results/games'

// Games filters as a GET form: works without JavaScript; with it, applies on change (CMP-06).
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
const { locale, locales } = useI18n()
const language = computed(() => locales.value.find(item => item.code === locale.value)?.language ?? locale.value)

function shortDate(date: string | null): string {
  return date ? new Intl.DateTimeFormat(language.value, { day: 'numeric', month: 'short', timeZone: 'UTC' }).format(new Date(date)) : ''
}

function apply() {
  if (!form.value) {
    return
  }
  const query = Object.fromEntries([...new FormData(form.value)].filter(([, value]) => value !== '').map(([key, value]) => [key, String(value)]))
  navigateTo({ path: route.path, query })
}
</script>

<template>
  <form ref="form" method="get" :action="route.path" class="flex flex-wrap items-end gap-4" data-testid="games-filters" @change="apply" @submit.prevent="apply">
    <input v-for="(value, name) in hiddenFields" :key="name" type="hidden" :name="name" :value="value">
    <label v-if="props.gamedays && props.gamedays.length > 1" class="flex flex-col text-sm">
      {{ $t('results.filters.gameday') }}
      <select name="gameday" class="rounded border border-line px-2 py-1">
        <option value="">{{ $t('results.filters.all') }}</option>
        <option v-for="option in props.gamedays" :key="option.id" :value="option.id" :selected="option.id === gameday">
          {{ option.label }} — {{ option.place }} ({{ shortDate(option.start) }})
        </option>
      </select>
    </label>
    <label v-if="dates.length > 1" class="flex flex-col text-sm">
      {{ $t('results.filters.day') }}
      <select name="day" class="rounded border border-line px-2 py-1">
        <option value="">{{ $t('results.filters.allDays') }}</option>
        <option v-for="date in dates" :key="date" :value="date" :selected="date === day">{{ shortDate(date) }}</option>
      </select>
    </label>
    <label class="flex items-center gap-2 text-sm">
      <input type="checkbox" name="upcoming" value="1" :checked="upcoming">
      {{ $t('results.filters.upcoming') }}
    </label>
    <UButton type="submit" size="sm" variant="outline">{{ $t('results.filters.apply') }}</UButton>
  </form>
</template>
