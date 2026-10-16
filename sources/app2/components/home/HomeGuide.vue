<template>
  <div class="flex h-full flex-col gap-4 rounded-lg border border-gray-200 bg-surface p-4 shadow-sm">
    <div class="relative">
      <img src="/img/tuto_md.png" :alt="t('Event.TutoExample')" class="hidden w-full rounded opacity-80 md:block" loading="lazy">
      <img src="/img/tuto_xs.png" :alt="t('Event.TutoExample')" class="mx-auto w-full max-w-xs rounded opacity-80 md:hidden" loading="lazy">
      <span class="absolute right-2 top-2 rounded bg-bar/70 px-2 py-0.5 text-xs font-medium italic text-white">
        {{ t('Event.TutoExample') }}
      </span>
    </div>
    <ul class="grid gap-3 text-sm text-gray-700 md:grid-cols-2">
      <li class="flex gap-2">
        <UIcon name="i-heroicons-users" class="mt-0.5 h-5 w-5 shrink-0 text-blue-600" />
        {{ t('Event.TutoTeam') }}
      </li>
      <li class="flex gap-2">
        <UIcon name="i-heroicons-document-text" class="mt-0.5 h-5 w-5 shrink-0 text-blue-600" />
        {{ t('Event.TutoMatchSheet') }}
      </li>
    </ul>
  </div>
</template>

<script setup>
// How to navigate the game list: an illustrative screenshot (desktop or mobile) and its two tips.
const { t } = useI18n()
</script>
