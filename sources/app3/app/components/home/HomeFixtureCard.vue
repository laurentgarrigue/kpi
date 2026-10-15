<script setup lang="ts">
import { eventLogoUrl, formatEventDates, isOngoing } from '~/utils/events'
import { fixturePath, fixturePlace, fixtureTitle, type Fixture } from '~/utils/fixtures'

// One fixture, linking to its event or group page (PAGE_HOME.md § 2, HOME-04, HOME-09).
const props = defineProps<{ fixture: Fixture, today: string }>()
const { legacyBaseUrl } = useRuntimeConfig().public
const localePath = useLocalePath()
const { locale } = useI18n()

const href = computed(() => localePath(fixturePath(props.fixture)))
const title = computed(() => fixtureTitle(props.fixture, locale.value))
const logo = computed(() => eventLogoUrl(props.fixture.logo, legacyBaseUrl))
const language = useLanguageTag()
const dates = computed(() => formatEventDates(props.fixture.start, props.fixture.end, language.value))
const place = computed(() => fixturePlace(props.fixture))
const ongoing = computed(() => isOngoing(props.fixture, props.today))
</script>

<template>
  <NuxtLink
    :to="href"
    class="flex h-full items-center gap-4 rounded border border-line p-4 hover:border-kpi-blue-500 hover:bg-kpi-blue-50"
  >
    <img v-if="logo" :src="logo" :alt="title" loading="lazy" class="kpi-logo size-16 shrink-0 object-contain">
    <span class="flex min-w-0 flex-col gap-1">
      <span class="font-semibold text-kpi-blue-600">{{ title }}</span>
      <span class="text-sm"><span class="tabular-nums">{{ dates }}</span><span v-if="place"> · {{ place }}</span></span>
    </span>
    <span class="ml-auto flex shrink-0 flex-col items-end gap-1 self-start">
      <UBadge color="neutral" variant="subtle" data-testid="fixture-kind">
        {{ fixture.kind === 'group' ? $t('home.groupFixture') : $t('home.eventFixture') }}
      </UBadge>
      <UBadge v-if="ongoing" color="error" variant="subtle" data-testid="ongoing-badge">
        {{ $t('home.ongoing') }}
      </UBadge>
    </span>
  </NuxtLink>
</template>
