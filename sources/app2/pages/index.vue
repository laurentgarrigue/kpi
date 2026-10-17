<template>
  <div class="container-fluid pb-16">
    <AppSecondaryNav>
      <template #center>
        <h1 class="text-2xl font-bold text-gray-800">KPI Application</h1>
      </template>
      <template #right></template>
    </AppSecondaryNav>

    <!-- Home page (PAGE_HOME.md § 7): what is followed, its views, what comes next, then sharing and help. -->
    <div class="mx-auto max-w-6xl space-y-8 px-4 py-6">
      <EventSelector />

      <HomeSection v-if="hasEventSelected" id="quick-links" :title="t('Home.QuickAccess')" icon="i-heroicons-bolt">
        <HomeQuickLinks />
      </HomeSection>

      <UpcomingFixtures />

      <div v-if="hasEventSelected" class="grid gap-8 lg:grid-cols-3">
        <HomeSection id="share" :title="t('Event.ShareEvent')" icon="i-heroicons-share">
          <EventQrCode />
        </HomeSection>
        <HomeSection id="guide" :title="t('Event.TutoTitle')" icon="i-heroicons-information-circle" class="lg:col-span-2">
          <HomeGuide />
        </HomeSection>
      </div>
    </div>

    <AppFooter />
  </div>
</template>

<script setup>
import { useEventGuard } from '~/composables/useEventGuard'

const { t } = useI18n()
const { hasEventSelected } = useEventGuard()

// Page-specific SEO
useSeoMeta({
  title: 'Home - KPI Application',
  description: 'Welcome to KPI Application - Your kayak polo information hub. Access live scores, rankings, team statistics, and tournament information.',
  ogTitle: 'KPI Application - Kayak Polo Information',
  ogDescription: 'Your kayak polo information hub - Live scores, rankings, and statistics'
})
</script>
