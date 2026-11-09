<script setup lang="ts">
import { byFamily, FAMILY_CLASSES, medalExplanation, medalName } from '~/utils/medals'

// Explanation page of the medals, labels and statistics (FEATURE_MATCH_MEDALS.md § 8, MED-48): everything published
// on app2 OR app3, by family, with the thresholds of the chosen season (MED-41). Maquette `medailles`.
const route = useRoute()
const { t, locale } = useI18n()
const localePath = useLocalePath()
const season = computed(() => (typeof route.query.season === 'string' && /^\d{4}$/.test(route.query.season) ? route.query.season : null))
const { catalog } = await useMedalCatalog(season)
const shownSeason = computed(() => catalog.value?.season ?? null)
const families = computed(() => byFamily((catalog.value?.items ?? []).filter(item => item.type !== 'stat')))
const statistics = computed(() => (catalog.value?.items ?? []).filter(item => item.type === 'stat'))
const sections = computed(() => [
  ...families.value.map(group => ({ id: group.family, title: t(`medals.family.${group.family}`), items: group.items })),
  ...(statistics.value.length ? [{ id: 'statistics', title: t('medals.page.statistics'), items: statistics.value }] : []),
])
const scopes = (item: { type: string, scopes: string[] }) => (item.type === 'label' ? ['game'] : item.scopes)
const places = (surfaces: string[] | undefined) => (surfaces ?? []).map(surface => t(`medals.page.${surface}`)).join(' · ')

useSeoMeta({ title: () => t('medals.page.title'), description: () => t('medals.page.description') })
</script>

<template>
  <div class="pb-14">
    <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: $t('medals.page.title') }]" />
    <SitePageHeading :title="$t('medals.page.title')" :intro="$t('medals.page.intro')" />
    <div class="mt-6 grid gap-10 md:grid-cols-[13rem_minmax(0,1fr)]">
      <aside class="space-y-4 text-sm md:sticky md:top-4 md:self-start">
        <nav v-if="(catalog?.seasons.length ?? 0) > 1" :aria-label="$t('medals.page.season')" class="flex flex-wrap gap-2" data-testid="medal-seasons">
          <NuxtLink
            v-for="item in catalog!.seasons"
            :key="item"
            :to="{ query: { season: item } }"
            :aria-current="item === shownSeason ? 'page' : undefined"
            class="rounded-full border px-3 py-1"
            :class="item === shownSeason ? 'border-navy bg-navy text-white' : 'border-line hover:bg-wash'"
          >
            {{ item }}
          </NuxtLink>
        </nav>
        <nav :aria-label="$t('medals.page.onThisPage')">
          <p class="mb-1 font-semibold text-muted">{{ $t('medals.page.onThisPage') }}</p>
          <a v-for="section in sections" :key="section.id" :href="`#${section.id}`" class="block py-1 hover:underline">{{ section.title }}</a>
        </nav>
      </aside>
      <div>
        <div class="mb-8 grid gap-4 rounded-lg bg-wash p-4 text-sm sm:grid-cols-2" data-testid="medal-principles">
          <p><b class="block">{{ $t('medals.page.validatedTitle') }}</b>{{ $t('medals.page.validated') }}</p>
          <p><b class="block">{{ $t('medals.page.completeTitle') }}</b>{{ $t('medals.page.complete') }}</p>
          <p><b class="block">{{ $t('medals.page.refereesTitle') }}</b>{{ $t('medals.page.referees') }}</p>
          <p v-if="shownSeason"><b class="block">{{ $t('medals.page.thresholdsTitle', { season: shownSeason }) }}</b>{{ $t('medals.page.thresholds') }}</p>
        </div>
        <p v-if="sections.length === 0" data-testid="medal-empty">{{ $t('medals.page.empty') }}</p>
        <section v-for="section in sections" :id="section.id" :key="section.id" class="mb-8 scroll-mt-4" data-testid="medal-family">
          <h2 class="mb-2 text-3xl leading-none text-kpi-blue-700">{{ section.title }}</h2>
          <article
            v-for="item in section.items"
            :key="item.code"
            class="grid grid-cols-[3.5rem_minmax(0,1fr)] gap-x-4 gap-y-0.5 border-t border-line py-3.5"
            :data-medal="item.code"
            data-testid="medal-explained"
          >
            <span class="row-span-3 inline-grid size-12 place-items-center rounded-full ring-1" :class="[FAMILY_CLASSES[item.family].text, FAMILY_CLASSES[item.family].disc]">
              <MedalIcon :item="item" :size="28" />
            </span>
            <h3 class="font-sans text-lg font-bold">
              {{ medalName(item, locale).main }}<span v-if="medalName(item, locale).english" class="font-normal text-muted italic"> · {{ medalName(item, locale).english }}</span>
            </h3>
            <p class="max-w-[65ch]">{{ medalExplanation(item, locale) }}</p>
            <p class="text-sm text-muted">
              <span v-for="scope in scopes(item)" :key="scope" class="mr-1 rounded border border-line px-1.5 text-xs">{{ $t(`medals.page.scope.${scope}`) }}</span>
              {{ $t('medals.page.where', { places: places(item.surfaces) }) }}
            </p>
          </article>
        </section>
      </div>
    </div>
  </div>
</template>
