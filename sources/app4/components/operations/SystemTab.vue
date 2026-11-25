<script setup lang="ts">
import type { CachePurgeResult } from '~/types/operations'
import { RULE_CATALOGS, type RuleCatalog } from '~/utils/pdf'

const { t } = useI18n()
const api = useApi()
const toast = useToast()
const authStore = useAuthStore()
const workContext = useWorkContextStore()
const { openDocument } = usePdf()

// État PDF des catalogues de règles métier : profil 1 uniquement (FEATURE_RULES_CATALOG_PDF.md RCAT-12)
const canPrintRules = computed(() => canGetAdminPdf(authStore.profile, 'rules-catalog'))
const rulesSeason = ref(workContext.season)
const rulesCatalog = ref<RuleCatalog | ''>('')
watch(() => workContext.season, (season) => {
  rulesSeason.value ||= season
})
onMounted(() => {
  if (canPrintRules.value) {
    workContext.initContext()
  }
})
const printRules = () => openDocument('rules-catalog', {
  season: rulesSeason.value,
  ...(rulesCatalog.value ? { catalog: rulesCatalog.value } : {})
})

// State
const loading = ref(false)
const confirmPurgeModal = ref(false)

// Purge cache
const openPurgeModal = () => {
  confirmPurgeModal.value = true
}

const confirmPurge = async () => {
  loading.value = true
  try {
    const result = await api.post<CachePurgeResult>('/admin/operations/cache/purge')
    toast.add({
      title: t('common.success'),
      description: t('operations.system.success_purge', {
        matchFiles: result.matchFilesDeleted,
        eventFiles: result.eventFilesDeleted
      }),
      color: 'success',
      duration: 5000
    })
    confirmPurgeModal.value = false
  } catch {
    toast.add({
      title: t('common.error'),
      description: t('operations.system.error_purge'),
      color: 'error',
      duration: 3000
    })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="space-y-8">
    <!-- Cache management -->
    <section>
      <h2 class="text-lg font-semibold text-header-900 dark:text-header-50 mb-4">
        {{ t('operations.system.cache') }}
      </h2>

      <div class="bg-header-50 dark:bg-header-900 rounded-lg p-4">
        <div class="flex items-start gap-4">
          <div class="p-3 bg-white dark:bg-header-900 rounded-lg shadow-sm">
            <UIcon name="i-heroicons-trash" class="w-6 h-6 text-header-900 dark:text-header-50" />
          </div>
          <div class="flex-1">
            <h3 class="font-medium text-header-900 dark:text-header-50">{{ t('operations.system.purge_cache') }}</h3>
            <p class="mt-1 text-sm text-header-900 dark:text-header-50">{{ t('operations.system.purge_description') }}</p>
            <button
              :disabled="loading"
              class="mt-4 px-4 py-2 bg-danger-600 text-white rounded-lg hover:bg-danger-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
              @click="openPurgeModal"
            >
              <UIcon v-if="loading" name="i-heroicons-arrow-path" class="w-4 h-4 animate-spin" />
              {{ t('operations.system.purge_button') }}
            </button>
          </div>
        </div>
      </div>
    </section>

    <!-- Business rule catalogues (profile 1) -->
    <section v-if="canPrintRules" data-testid="rules-catalog">
      <h2 class="text-lg font-semibold text-header-900 dark:text-header-50 mb-4">
        {{ t('operations.system.rules_catalog') }}
      </h2>

      <div class="bg-header-50 dark:bg-header-900 rounded-lg p-4">
        <div class="flex items-start gap-4">
          <div class="p-3 bg-white dark:bg-header-900 rounded-lg shadow-sm">
            <UIcon name="i-heroicons-scale" class="w-6 h-6 text-header-900 dark:text-header-50" />
          </div>
          <div class="flex-1">
            <p class="text-sm text-header-900 dark:text-header-50">{{ t('operations.system.rules_catalog_description') }}</p>
            <div class="mt-4 flex flex-wrap items-end gap-3">
              <label class="text-sm text-header-700 dark:text-header-200">
                {{ t('operations.system.rules_catalog_season') }}
                <select v-model="rulesSeason" class="mt-1 block rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" data-testid="rules-catalog-season">
                  <option v-for="season in workContext.seasons" :key="season.code" :value="season.code">{{ season.code }}</option>
                </select>
              </label>
              <label class="text-sm text-header-700 dark:text-header-200">
                {{ t('operations.system.rules_catalog_catalog') }}
                <select v-model="rulesCatalog" class="mt-1 block rounded border border-header-300 px-2 py-1.5 dark:bg-header-800" data-testid="rules-catalog-catalog">
                  <option value="">{{ t('operations.system.rules_catalog_all') }}</option>
                  <option v-for="catalog in RULE_CATALOGS" :key="catalog" :value="catalog">{{ t(`operations.system.rules_catalogs.${catalog}`) }}</option>
                </select>
              </label>
              <button
                :disabled="!rulesSeason"
                class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                data-testid="rules-catalog-print"
                @click="printRules"
              >
                <UIcon name="i-heroicons-document-arrow-down" class="w-4 h-4" />
                {{ t('operations.system.rules_catalog_button') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Confirm purge modal -->
    <AdminConfirmModal
      :open="confirmPurgeModal"
      :title="t('operations.system.confirm_purge')"
      :message="t('operations.system.purge_description')"
      :confirm-text="t('operations.system.purge_button')"
      :cancel-text="t('common.cancel')"
      :loading="loading"
      danger
      @close="confirmPurgeModal = false"
      @confirm="confirmPurge"
    />
  </div>
</template>
