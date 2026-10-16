<script setup lang="ts">
import { formApiPath, type PublicEntries } from '~/utils/forms'

// Public list of a form (FEATURE_FORMS.md § 3.5, FRM-06): confirmed then pending registrations, published columns
// only, cancelled ones never shown. Not public → 404.
const route = useRoute()
const { t, locale } = useI18n()
const localePath = useLocalePath()

const slug = computed(() => String(route.params.slug))
const { data: entries } = await useApiResource<PublicEntries>(() => formApiPath(slug.value, locale.value, '/entries'))
const tables = computed(() => (entries.value
  ? [
      { id: 'confirmed', title: t('forms.confirmedEntries'), hint: null, rows: entries.value.confirmed },
      { id: 'waiting', title: t('forms.waitingEntries'), hint: t('forms.waitingEntriesHint'), rows: entries.value.waiting },
    ]
  : []))

useSeoMeta({ title: () => t('forms.entriesTitle', { title: entries.value?.title ?? '' }) })
</script>

<template>
  <div v-if="entries" class="space-y-8">
    <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: entries.title, to: localePath(`/forms/${slug}`) }, { label: $t('forms.entries') }]" />
    <h1 class="text-5xl leading-tight text-kpi-blue-600">{{ $t('forms.entriesTitle', { title: entries.title }) }}</h1>
    <section v-for="table in tables" :key="table.id" :aria-labelledby="`entries-${table.id}`" class="space-y-2" :data-testid="`entries-${table.id}`">
      <h2 :id="`entries-${table.id}`" class="text-3xl text-kpi-blue-600">
        {{ table.title }}
        <span class="text-base text-ink/70">— {{ $t('forms.count', { count: table.rows.length }, table.rows.length) }}<template v-if="table.id === 'confirmed' && entries.capacity"> / {{ entries.capacity }}</template></span>
      </h2>
      <p v-if="table.hint" class="text-sm text-ink/70">{{ table.hint }}</p>
      <p v-if="table.rows.length === 0">{{ $t('forms.noEntries') }}</p>
      <table v-else class="w-full text-left">
        <thead>
          <tr>
            <th scope="col" class="w-12 px-2 py-1">#</th>
            <th v-for="column in entries.columns" :key="column" scope="col" class="px-2 py-1">{{ column }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in table.rows" :key="index" class="border-b border-line">
            <td class="px-2 py-1">{{ index + 1 }}</td>
            <td v-for="(cell, column) in row" :key="column" class="px-2 py-1">{{ cell }}</td>
          </tr>
        </tbody>
      </table>
    </section>
  </div>
</template>
