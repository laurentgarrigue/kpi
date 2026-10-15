<template>
  <ul class="grid gap-3 sm:grid-cols-3">
    <li v-for="link in links" :key="link.to">
      <NuxtLink
        :to="link.to"
        class="flex h-full items-center gap-3 rounded-lg border border-gray-200 bg-surface p-4 shadow-sm transition-colors hover:border-blue-500 hover:bg-blue-50"
      >
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-600">
          <UIcon :name="link.icon" class="h-6 w-6" />
        </span>
        <span class="flex flex-col">
          <span class="font-semibold text-gray-900">{{ t(link.label) }}</span>
          <span class="text-sm text-gray-600">{{ t(link.hint) }}</span>
        </span>
      </NuxtLink>
    </li>
  </ul>
</template>

<script setup>
// The three views of the followed event or group, as the main entry points of the home page.
const { t } = useI18n()

const links = [
  { to: '/games', icon: 'i-heroicons-list-bullet', label: 'nav.Games', hint: 'Home.GamesHint' },
  { to: '/charts', icon: 'i-heroicons-rectangle-group', label: 'nav.Charts', hint: 'Home.ChartsHint' },
  { to: '/team', icon: 'i-heroicons-users', label: 'Teams.Team', hint: 'Home.TeamHint' },
]
</script>
