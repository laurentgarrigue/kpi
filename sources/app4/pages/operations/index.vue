<script setup lang="ts">
import type { OperationsTab } from '~/types/operations'

definePageMeta({
  layout: 'admin',
  middleware: 'auth'
})

const { t } = useI18n()
const authStore = useAuthStore()

// Redirect if not admin (profile <= 2)
if (!authStore.hasProfile(2)) {
  navigateTo('/')
}

// Current active tab
const activeTab = ref<OperationsTab>('seasons')

// Tab definitions
const tabs = computed(() => [
  { id: 'images' as OperationsTab, label: t('operations.tabs.images'), icon: 'i-heroicons-photo' },
  { id: 'players' as OperationsTab, label: t('operations.tabs.players'), icon: 'i-heroicons-users' },
  { id: 'teams' as OperationsTab, label: t('operations.tabs.teams'), icon: 'i-heroicons-user-group' },
  { id: 'codes' as OperationsTab, label: t('operations.tabs.codes'), icon: 'i-heroicons-trophy' },
  { id: 'import-export' as OperationsTab, label: t('operations.tabs.import_export'), icon: 'i-heroicons-arrow-down-tray' },
  { id: 'seasons' as OperationsTab, label: t('operations.tabs.seasons'), icon: 'i-heroicons-calendar-days' },
  { id: 'system' as OperationsTab, label: t('operations.tabs.system'), icon: 'i-heroicons-cog-6-tooth' }
])

// Handle tab change
const changeTab = (tabId: OperationsTab) => {
  activeTab.value = tabId
}
</script>

<template>
  <div>
    <!-- Page header -->
    <div class="mb-6">
      <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">
        {{ t('operations.title') }}
      </h1>
    </div>

    <!-- Warning banner -->
    <div class="mb-6 p-4 bg-warning-50 dark:bg-warning-950 border border-warning-200 dark:border-warning-900 rounded-lg">
      <div class="flex items-start gap-3">
        <UIcon name="i-heroicons-exclamation-triangle" class="w-5 h-5 text-warning-600 dark:text-warning-300 mt-0.5 shrink-0" />
        <div>
          <h4 class="font-medium text-warning-800 dark:text-warning-200">{{ t('operations.common.warning') }}</h4>
          <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">{{ t('operations.common.irreversibles') }}</p>
        </div>
      </div>
    </div>

    <!-- Tab navigation -->
    <div class="mb-6">
      <!-- Desktop tabs -->
      <div class="hidden md:block border-b border-header-200 dark:border-header-700">
        <nav class="-mb-px flex space-x-4 overflow-x-auto" aria-label="Tabs">
          <button
            v-for="tab in tabs"
            :key="tab.id"
            :class="[
              activeTab === tab.id
                ? 'border-primary-500 text-primary-600 dark:text-primary-300'
                : 'border-transparent text-header-600 dark:text-header-300 hover:text-header-900 dark:hover:text-header-50 hover:border-header-300',
              'whitespace-nowrap py-3 px-4 border-b-2 font-medium text-sm flex items-center gap-2 transition-colors'
            ]"
            @click="changeTab(tab.id)"
          >
            <UIcon :name="tab.icon" class="w-5 h-5" />
            {{ tab.label }}
          </button>
        </nav>
      </div>

      <!-- Mobile tab selector -->
      <div class="md:hidden">
        <label for="tabs" class="sr-only">Select tab</label>
        <select
          id="tabs"
          v-model="activeTab"
          class="block w-full rounded-md border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 py-2 pl-3 pr-10 text-base focus:border-primary-500 focus:ring-primary-500"
        >
          <option v-for="tab in tabs" :key="tab.id" :value="tab.id">
            {{ tab.label }}
          </option>
        </select>
      </div>
    </div>

    <!-- Tab content -->
    <div class="bg-white dark:bg-header-900 rounded-lg shadow p-6">
      <OperationsImagesTab v-if="activeTab === 'images'" />
      <OperationsPlayersTab v-if="activeTab === 'players'" />
      <OperationsTeamsTab v-if="activeTab === 'teams'" />
      <OperationsCodesTab v-if="activeTab === 'codes'" />
      <OperationsImportExportTab v-if="activeTab === 'import-export'" />
      <OperationsSeasonsTab v-if="activeTab === 'seasons'" />
      <OperationsSystemTab v-if="activeTab === 'system'" />
    </div>
  </div>
</template>
