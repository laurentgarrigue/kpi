<script setup lang="ts">
// Beta notice, shown on every page until the switch-over (SITE_LAYOUT.md § 2.1, LAY-02).
const { beta, legacyBaseUrl } = useRuntimeConfig().public
</script>

<template>
  <div
    v-if="beta"
    role="region"
    :aria-label="$t('a11y.betaRegion')"
    class="bg-sky text-ink text-sm"
    data-testid="beta-banner"
  >
    <p class="mx-auto max-w-7xl px-4 py-2 lg:px-8">
      <strong>{{ $t('beta.message') }}</strong>
      {{ $t('beta.legacyPrefix') }}
      <a :href="legacyBaseUrl" class="font-semibold underline">{{ $t('beta.legacyLink') }}</a>
    </p>
  </div>
</template>
