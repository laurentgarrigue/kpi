<script setup lang="ts">
definePageMeta({
  layout: 'admin',
  middleware: 'auth'
})

const { t } = useI18n()

// Relance du tour guidé depuis la page d'aide. Voir DOC/specs/TUTORIEL_ADMIN2.md
const { startTour } = useGuidedTour('welcome')

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
    <div class="mb-6 flex items-start justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">
          {{ t('help.title') }}
        </h1>
        <p class="mt-1 text-sm text-header-600">{{ t('help.subtitle') }}</p>
      </div>
      <button
        type="button"
        class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-sm font-medium transition-colors cursor-pointer"
        @click="startTour(false)"
      >
        <UIcon name="heroicons:play-circle" class="w-5 h-5" />
        {{ t('help.start_tour') }}
      </button>
    </div>

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
