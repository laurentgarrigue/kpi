<script setup lang="ts">
/**
 * Bandeau « une nouvelle version est disponible » — `DOC/specs/PWA_AUTO_UPDATE.md` §5.5.
 *
 * Le service worker installé attend : rien ne bascule avant le clic sur « Recharger »,
 * pour ne jamais interrompre une saisie en cours (console de scoring). Le bandeau est
 * volontairement discret et non bloquant, mais il reste affiché tant que la mise à jour
 * n'est pas appliquée.
 */
const { needRefresh, updateApp } = usePwa()

const reloading = ref(false)

const reload = async () => {
  reloading.value = true
  await updateApp()
}
</script>

<template>
  <div
    v-if="needRefresh"
    class="fixed bottom-4 left-1/2 z-[9998] flex -translate-x-1/2 items-center gap-3 rounded-lg border border-primary-200 bg-white px-4 py-2.5 shadow-lg dark:border-primary-800 dark:bg-header-900"
    role="status"
    aria-live="polite"
  >
    <UIcon name="heroicons:arrow-path" class="h-5 w-5 shrink-0 text-primary-600 dark:text-primary-400" />
    <span class="text-sm text-header-900 dark:text-header-100">{{ $t('pwa.update_available') }}</span>
    <UButton size="xs" :loading="reloading" @click="reload">
      {{ $t('pwa.reload') }}
    </UButton>
  </div>
</template>
