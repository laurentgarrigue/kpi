<script setup lang="ts">
import type { FormSummary } from '~/types/cms'

// Registration forms (PAGE_CMS.md § 6): state, entries (confirmed / waiting / capacity), access to the entries.
definePageMeta({ layout: 'admin', middleware: ['auth', 'editorial'] })

const { t } = useI18n()
const cms = useCmsApi()
const router = useRouter()
const forms = ref<FormSummary[]>([])
const loading = ref(true)
const STATE_COLORS = { draft: 'neutral', upcoming: 'info', open: 'success', closed: 'warning' } as const

onMounted(async () => {
  try {
    forms.value = await cms.forms()
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div>
    <h1 class="mb-6 text-2xl font-bold text-header-900 dark:text-header-50">{{ t('cms.forms.title') }}</h1>
    <AdminToolbar :add-label="t('cms.forms.add')" @add="router.push('/cms/forms/new')" />
    <div class="sticky-thead rounded-lg bg-white dark:bg-header-900 shadow">
      <table class="min-w-full divide-y divide-header-200 dark:divide-header-700 text-sm">
        <thead class="bg-header-50 dark:bg-header-900">
          <tr>
            <th class="px-4 py-2 text-left">{{ t('cms.fields.title') }}</th>
            <th class="px-4 py-2 text-left">{{ t('cms.forms.state_label') }}</th>
            <th class="hidden px-4 py-2 text-left md:table-cell">{{ t('cms.forms.closes_at') }}</th>
            <th class="px-4 py-2 text-right">{{ t('cms.forms.entries') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-header-200 dark:divide-header-700">
          <tr v-if="loading"><td colspan="4" class="px-4 py-6 text-center">{{ t('common.loading') }}</td></tr>
          <tr v-else-if="forms.length === 0"><td colspan="4" class="px-4 py-6 text-center">{{ t('cms.forms.empty') }}</td></tr>
          <tr v-for="form in forms" :key="form.id" class="hover:bg-header-50 dark:hover:bg-header-800">
            <td class="px-4 py-2">
              <NuxtLink :to="`/cms/forms/${form.id}`" class="font-medium text-primary-700 dark:text-primary-300 hover:underline">{{ form.title }}</NuxtLink>
              <UBadge v-if="form.imported" size="sm" variant="outline" class="ml-2">{{ t('cms.imported') }}</UBadge>
            </td>
            <td class="px-4 py-2"><UBadge :color="STATE_COLORS[form.state]" variant="subtle" size="sm">{{ t(`cms.forms.state.${form.state}`) }}</UBadge></td>
            <td class="hidden px-4 py-2 tabular-nums md:table-cell">{{ form.closes_at?.slice(0, 16) ?? '—' }}</td>
            <td class="px-4 py-2 text-right">
              <NuxtLink :to="`/cms/forms/${form.id}/entries`" class="tabular-nums text-primary-700 dark:text-primary-300 hover:underline">
                {{ t('cms.forms.counts', { confirmed: form.counts.confirmed, waiting: form.counts.waiting, capacity: form.capacity ?? '∞' }) }}
              </NuxtLink>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
