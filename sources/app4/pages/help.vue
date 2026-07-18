<script setup lang="ts">
import { TOURS } from '~/utils/tourSteps'

definePageMeta({
  layout: 'admin',
  middleware: 'auth'
})

const { t } = useI18n()

// Liste unifiée des tutoriels : accueil + tours de page, filtrée sur le profil
// (le tour Rankings n'apparaît que pour les profils <= 4).
// Voir DOC/specs/TUTORIEL_ADMIN2.md
const availableTours = useAvailableTours()

// `useGuidedTour` doit être appelé au setup (il utilise useI18n/useRouter) :
// on pré-instancie un lanceur par tour disponible plutôt qu'à la volée au clic.
const tourLaunchers = computed(() =>
  availableTours.value.map(tour => ({
    id: tour.id,
    icon: tour.icon,
    label: t(tour.labelKey),
  }))
)

const runners = Object.fromEntries(
  Object.keys(TOURS).map(id => [id, useGuidedTour(id)])
)

function replay(tourId: string): void {
  runners[tourId]?.startTour(false)
}

// Nouveautés mises en valeur (les 4 essentielles du MVP).
const features = [
  { icon: 'heroicons:identification', key: 'mandate' },
  { icon: 'heroicons:adjustments-horizontal', key: 'work_context' },
  { icon: 'heroicons:bars-3', key: 'menu' },
  { icon: 'heroicons:cursor-arrow-rays', key: 'clickable_cells' },
  { icon: 'heroicons:pencil-square', key: 'context_summary' },
] as const
</script>

<template>
  <div class="max-w-3xl">
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">
        {{ t('help.title') }}
      </h1>
      <p class="mt-1 text-sm text-header-600">{{ t('help.subtitle') }}</p>
    </div>

    <!-- Tutoriels interactifs (accueil + tours de page, filtrés par profil) -->
    <section class="mb-8 p-5 bg-white dark:bg-header-900 rounded-lg shadow">
      <h2 class="text-lg font-semibold text-header-900 dark:text-header-50 mb-1">
        {{ t('help.tours_title') }}
      </h2>
      <p class="text-sm text-header-600 dark:text-header-300 mb-4">
        {{ t('help.tours_subtitle') }}
      </p>
      <ul class="space-y-2">
        <li
          v-for="tour in tourLaunchers"
          :key="tour.id"
          class="flex items-center justify-between gap-4 p-3 rounded-lg border border-header-200 dark:border-header-700"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="p-2 bg-primary-100 dark:bg-primary-950 rounded-lg shrink-0">
              <UIcon :name="tour.icon" class="w-5 h-5 text-primary-600 dark:text-primary-400" />
            </div>
            <span class="font-medium text-header-900 dark:text-header-50 truncate">
              {{ tour.label }}
            </span>
          </div>
          <button
            type="button"
            class="shrink-0 inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium transition-colors cursor-pointer"
            @click="replay(tour.id)"
          >
            <UIcon name="heroicons:play-circle" class="w-4 h-4" />
            {{ t('help.replay') }}
          </button>
        </li>
      </ul>
    </section>

    <!-- app1 vs app2 -->
    <section class="mb-8 p-5 bg-white dark:bg-header-900 rounded-lg shadow">
      <h2 class="text-lg font-semibold text-header-900 dark:text-header-50 mb-2">
        {{ t('help.diff_title') }}
      </h2>
      <p class="text-sm text-header-700 dark:text-header-300 whitespace-pre-line">
        {{ t('help.diff_body') }}
      </p>
    </section>

    <!-- Nouveautés -->
    <section class="mb-8">
      <h2 class="text-lg font-semibold text-header-900 dark:text-header-50 mb-3">
        {{ t('help.features_title') }}
      </h2>
      <div class="space-y-3">
        <div
          v-for="f in features"
          :key="f.key"
          class="flex items-start gap-4 p-4 bg-white dark:bg-header-900 rounded-lg shadow"
        >
          <div class="p-2 bg-primary-100 dark:bg-primary-950 rounded-lg shrink-0">
            <UIcon :name="f.icon" class="w-6 h-6 text-primary-600 dark:text-primary-400" />
          </div>
          <div>
            <h3 class="font-semibold text-header-900 dark:text-header-50">
              {{ t(`tour.${f.key}_title`) }}
            </h3>
            <p class="mt-0.5 text-sm text-header-600 dark:text-header-300">
              {{ t(`tour.${f.key}_body`) }}
            </p>
          </div>
        </div>
      </div>
    </section>

    <!-- Navigation -->
    <section class="p-5 bg-white dark:bg-header-900 rounded-lg shadow">
      <h2 class="text-lg font-semibold text-header-900 dark:text-header-50 mb-2">
        {{ t('help.nav_title') }}
      </h2>
      <p class="text-sm text-header-700 dark:text-header-300 whitespace-pre-line">
        {{ t('help.nav_body') }}
      </p>
    </section>
  </div>
</template>
