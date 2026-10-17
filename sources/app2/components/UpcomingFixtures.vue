<template>
  <HomeSection v-if="fixtures.length" id="upcoming-fixtures" :title="t('Fixtures.Upcoming')" icon="i-heroicons-calendar-days">
    <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      <li v-for="fixture in fixtures" :key="fixtureKey(fixture)">
        <NuxtLink
          :to="fixturePath(fixture)"
          class="flex h-full items-center gap-3 rounded-lg border border-gray-200 bg-surface p-3 shadow-sm transition-colors hover:border-blue-500 hover:bg-blue-50"
        >
          <img v-if="fixture.logo" :src="`${backendBaseUrl}/img/${fixture.logo}`" alt="" class="h-10 w-10 shrink-0 object-contain" loading="lazy">
          <span class="flex min-w-0 flex-col">
            <span class="font-semibold text-blue-600">{{ fixtureTitle(fixture) }}</span>
            <span class="text-sm text-gray-700">{{ [formatDates(fixture), fixturePlace(fixture)].filter(Boolean).join(' · ') }}</span>
          </span>
          <span class="ml-auto flex shrink-0 flex-col items-end gap-1 self-start">
            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
              {{ fixture.kind === 'group' ? t('Fixtures.Competition') : t('Fixtures.Event') }}
            </span>
            <span v-if="isOngoing(fixture)" class="rounded bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">{{ t('Fixtures.Ongoing') }}</span>
          </span>
        </NuxtLink>
      </li>
    </ul>
  </HomeSection>
</template>

<script setup>
import { ref, onMounted } from 'vue'

// « Coming up » under the share QR code (DOC/specs/public/PAGE_HOME.md § 7): app2 events and group days outside
// any event, nearest first; each line selects the event or the group through its deep link.
const { t, locale } = useI18n()
const { getApi } = useApi()
const { getGroupLabel } = useGroupLabel()
const { backendBaseUrl } = useRuntimeConfig().public

const fixtures = ref([])
const today = ref('')

const fixturePath = fixture => fixture.kind === 'event' ? `/event/${fixture.id}` : `/group/${fixture.season}/${fixture.code}`
const fixtureKey = fixture => fixture.kind === 'event' ? `event-${fixture.id}` : `group-${fixture.season}-${fixture.code}-${fixture.start}-${fixture.place}`
const fixtureTitle = fixture => fixture.kind === 'event' ? fixture.libelle : getGroupLabel(fixture)
const fixturePlace = fixture => fixture.kind === 'group' && fixture.department ? `${fixture.place ?? ''} (${fixture.department})` : (fixture.place ?? '')

// The end date defaults to the start date; dates are calendar days, formatted in UTC so no time zone shifts them.
const isOngoing = fixture => fixture.start <= today.value && today.value <= (fixture.end ?? fixture.start)
const formatDates = (fixture) => {
  const format = new Intl.DateTimeFormat(locale.value, { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' })
  return format.formatRange(new Date(fixture.start), new Date(fixture.end ?? fixture.start))
}

// api2 unavailable: the section stays hidden, the rest of the home page is unaffected.
onMounted(async () => {
  try {
    const result = await getApi('/fixtures/app', { silentErrors: true })
    if (!result.ok) {
      return
    }
    const data = await result.json()
    today.value = data.today
    fixtures.value = data.upcoming
  } catch (error) {
    console.warn('Failed to load upcoming fixtures:', error)
  }
})
</script>
