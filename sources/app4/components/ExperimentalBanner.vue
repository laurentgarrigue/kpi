<script setup lang="ts">
/**
 * Bandeau « préprod expérimentale » (Phase 7 du plan CI/CD).
 *
 * Affiché UNIQUEMENT quand une branche `feature/*` a été déployée en préprod à
 * la place de `develop` (voir `useExperimentalFlag`). Volontairement criard et
 * non masquable : son but est qu'un testeur ne puisse pas prendre cet état
 * temporaire pour la préprod de référence.
 */
const { flag, isExperimental, hoursLeft } = useExperimentalFlag()
</script>

<template>
  <div
    v-if="isExperimental && flag"
    class="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 bg-fuchsia-600 px-3 py-1.5 text-center text-xs font-semibold text-white sm:text-sm"
    role="status"
  >
    <span>🧪 {{ $t('experimental.title') }}</span>
    <span class="font-mono font-bold">{{ flag.branch }}</span>
    <span class="opacity-90">{{ $t('experimental.expires_in', { hours: hoursLeft }) }}</span>
    <span class="font-mono text-[0.65rem] opacity-75">{{ flag.sha.slice(0, 7) }}</span>
  </div>
</template>
