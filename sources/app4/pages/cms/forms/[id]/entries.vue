<script setup lang="ts">
import type { EntryStatus, FormDefinition, FormEntries, FormMail } from '~/types/cms'

// Entries of a form (PAGE_CMS.md § 6, ADM-CMS-06/07): confirm (e-mail to the registrant), put on hold, cancel,
// export CSV / Excel, e-mail log with resend of a failed e-mail.
definePageMeta({ layout: 'admin', middleware: ['auth', 'editorial'] })

const route = useRoute()
const { t } = useI18n()
const toast = useToast()
const cms = useCmsApi()
const id = Number(route.params.id)
const form = ref<FormDefinition | null>(null)
const data = ref<FormEntries | null>(null)
const mails = ref<FormMail[]>([])
const ALL = 'all'
const statusFilter = ref<typeof ALL | EntryStatus>(ALL)
const STATUS_COLORS = { confirmed: 'success', waiting: 'warning', cancelled: 'neutral' } as const
const statusItems = computed(() => [{ label: t('cms.all_statuses'), value: ALL }, ...(['confirmed', 'waiting', 'cancelled'] as const).map(value => ({ label: t(`cms.forms.entry_status.${value}`), value }))])
const entries = computed(() => (data.value?.entries ?? []).filter(entry => statusFilter.value === ALL || entry.status === statusFilter.value))

async function setStatus(entryId: number, status: EntryStatus) {
  data.value = await cms.setEntryStatus(id, entryId, status)
  toast.add({ title: t('common.success'), description: t(`cms.forms.status_changed.${status}`), color: 'success', duration: 3000 })
  mails.value = await cms.formMails(id)
}

async function exportEntries(format: 'csv' | 'xlsx') {
  const content = await cms.exportForm(id, format)
  const blob = new Blob([content], { type: format === 'csv' ? 'text/csv;charset=utf-8' : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `inscriptions-${form.value?.slug ?? id}.${format}`
  link.click()
  URL.revokeObjectURL(link.href)
}

async function resend(mailId: number) {
  const result = await cms.resendMail(id, mailId)
  toast.add({ title: t(`cms.forms.mail_status.${result.status}`), color: result.status === 'sent' ? 'success' : 'error', duration: 3000 })
  mails.value = await cms.formMails(id)
}

onMounted(async () => {
  [form.value, data.value, mails.value] = await Promise.all([cms.form(id), cms.formEntries(id), cms.formMails(id)])
})
</script>

<template>
  <div v-if="form && data" class="space-y-4">
    <div>
      <NuxtLink :to="`/cms/forms/${id}`" class="text-sm text-primary-700 dark:text-primary-300 hover:underline">← {{ form.title_fr }}</NuxtLink>
      <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">{{ t('cms.forms.entries') }}</h1>
      <p class="text-sm">{{ t('cms.forms.counts', { confirmed: data.counts.confirmed, waiting: data.counts.waiting, capacity: form.capacity ?? '∞' }) }}
        <template v-if="form.archived_entries"> · {{ t('cms.forms.archived', { count: form.archived_entries }) }}</template>
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <USelect v-model="statusFilter" :items="statusItems" class="w-48" :aria-label="t('cms.status_label')" />
      <UButton variant="outline" icon="heroicons:arrow-down-tray" @click="exportEntries('csv')">CSV</UButton>
      <UButton variant="outline" icon="heroicons:arrow-down-tray" @click="exportEntries('xlsx')">Excel</UButton>
    </div>
    <div class="overflow-x-auto rounded-lg bg-white dark:bg-header-900 shadow">
      <table class="min-w-full divide-y divide-header-200 dark:divide-header-700 text-sm">
        <thead class="bg-header-50 dark:bg-header-900">
          <tr>
            <th class="px-3 py-2 text-left">{{ t('cms.forms.date') }}</th>
            <th class="px-3 py-2 text-left">{{ t('cms.status_label') }}</th>
            <th v-for="column in data.columns" :key="column.key" class="px-3 py-2 text-left">{{ column.label }}</th>
            <th class="px-3 py-2"><span class="sr-only">{{ t('cms.forms.actions') }}</span></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-header-200 dark:divide-header-700">
          <tr v-if="entries.length === 0"><td :colspan="data.columns.length + 3" class="px-3 py-6 text-center">{{ t('cms.forms.no_entries') }}</td></tr>
          <tr v-for="entry in entries" :key="entry.id" :data-entry="entry.id">
            <td class="px-3 py-2 tabular-nums whitespace-nowrap">{{ entry.created_at.slice(0, 16) }}</td>
            <td class="px-3 py-2">
              <UBadge :color="STATUS_COLORS[entry.status]" variant="subtle" size="sm">{{ t(`cms.forms.entry_status.${entry.status}`) }}</UBadge>
              <span v-if="entry.reason" class="block text-xs text-header-600 dark:text-header-300">{{ t(`cms.forms.reason.${entry.reason}`) }}</span>
            </td>
            <td v-for="column in data.columns" :key="column.key" class="px-3 py-2">{{ entry.answers[column.key] }}</td>
            <td class="px-3 py-2 whitespace-nowrap text-right">
              <UButton v-if="entry.status !== 'confirmed'" size="xs" color="success" variant="soft" data-testid="confirm-entry" @click="setStatus(entry.id, 'confirmed')">{{ t('cms.forms.confirm') }}</UButton>
              <UButton v-if="entry.status === 'confirmed'" size="xs" color="warning" variant="ghost" @click="setStatus(entry.id, 'waiting')">{{ t('cms.forms.hold') }}</UButton>
              <UButton v-if="entry.status !== 'cancelled'" size="xs" color="neutral" variant="ghost" @click="setStatus(entry.id, 'cancelled')">{{ t('cms.forms.cancel') }}</UButton>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <details class="rounded-lg bg-white dark:bg-header-900 p-4 shadow">
      <summary class="cursor-pointer font-semibold">{{ t('cms.forms.mail_log') }} ({{ mails.length }})</summary>
      <ul class="mt-3 divide-y divide-header-200 dark:divide-header-700 text-sm">
        <li v-for="mail in mails" :key="mail.id" class="flex flex-wrap items-center gap-2 py-2">
          <span class="tabular-nums">{{ mail.sent_at.slice(0, 16) }}</span>
          <span class="flex-1">{{ mail.recipient }} — {{ mail.subject }}</span>
          <UBadge :color="mail.status === 'sent' ? 'success' : 'error'" variant="subtle" size="sm">{{ t(`cms.forms.mail_status.${mail.status}`) }}</UBadge>
          <UButton v-if="mail.resendable" size="xs" variant="outline" @click="resend(mail.id)">{{ t('cms.forms.resend') }}</UButton>
        </li>
      </ul>
    </details>
  </div>
</template>
