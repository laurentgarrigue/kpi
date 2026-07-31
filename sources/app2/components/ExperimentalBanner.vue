<script setup lang="ts">
/**
 * Bandeau « préprod expérimentale » (Phase 7 du plan CI/CD).
 *
 * Jumeau de `sources/app4/components/ExperimentalBanner.vue`. Affiché UNIQUEMENT
 * quand une branche `feature/*` a été déployée en préprod à la place de
 * `develop`. Volontairement criard et non masquable : son but est qu'un testeur
 * ne puisse pas prendre cet état temporaire pour la préprod de référence.
 */
const { flag, isExperimental, hoursLeft } = useExperimentalFlag()
</script>

<template>
  <div
    v-if="isExperimental && flag"
    class="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 bg-fuchsia-600 px-3 py-1.5 text-center text-xs font-semibold text-white sm:text-sm"
    role="status"
  >
    <!-- Clés en PascalCase : convention des locales d'app2 (app4 utilise du snake_case). -->
    <span>🧪 {{ $t('Experimental.Title') }}</span>
    <span class="font-mono font-bold">{{ flag.branch }}</span>
    <span class="opacity-90">{{ $t('Experimental.ExpiresIn', { hours: hoursLeft }) }}</span>
    <span class="font-mono text-[0.65rem] opacity-75">{{ flag.sha.slice(0, 7) }}</span>
  </div>
</template>
