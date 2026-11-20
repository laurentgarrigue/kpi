<script setup lang="ts">
/** Reminder shown during the 7-day grace (AUTH_HARDENING.md § 5, AUTH-2FA-02), until a factor is active. */
import { graceDaysLeft } from '~/utils/secondFactor'

const { t } = useI18n()
const authStore = useAuthStore()
const route = useRoute()

const daysLeft = computed(() => {
  const summary = authStore.user?.secondFactor
  return summary && !summary.method ? graceDaysLeft(summary.enrollmentDueAt) : null
})
const visible = computed(() => daysLeft.value !== null && route.path !== '/profile/security')
</script>

<template>
  <div
    v-if="visible"
    class="px-4 py-2 bg-warning-50 dark:bg-warning-950 border-b border-warning-200 dark:border-warning-800 text-sm text-warning-800 dark:text-warning-200 flex flex-wrap items-center gap-2"
    data-testid="second-factor-grace-banner"
  >
    <UIcon name="i-heroicons-shield-exclamation" class="w-5 h-5 shrink-0" />
    <span>{{ t('second_factor.banner.text', { days: daysLeft }) }}</span>
    <NuxtLink to="/profile/security" class="font-semibold underline">{{ t('second_factor.banner.link') }}</NuxtLink>
  </div>
</template>
