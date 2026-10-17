<script setup lang="ts">
import { FORM_FIELD_TYPES, FORM_PERSONAL_TYPES, FORM_TYPES_WITH_OPTIONS, type FormDefinition, type FormField } from '~/types/cms'
import { fieldKeyFromLabel, fromDateTimeLocal, moveItem, slugify, toDateTimeLocal, validationErrors } from '~/utils/cms'

// Form builder (PAGE_CMS.md § 6, FEATURE_FORMS.md § 2): identity, fields, opening, capacity and waiting list,
// confirmation mode, payment link, e-mails, public list (non-personal columns only), GDPR.
definePageMeta({ layout: 'admin', middleware: ['auth', 'editorial'] })

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const cms = useCmsApi()
const authStore = useAuthStore()
const { publicSiteUrl } = useRuntimeConfig().public

const id = computed<number | 'new'>(() => (route.params.id === 'new' ? 'new' : Number(route.params.id)))
const DEFAULT_RETENTION_DAYS = 90
const emptyForm = (): FormDefinition => ({
  slug: '', title_fr: '', title_en: '', intro_fr: '', intro_en: '', status: 'draft', opens_at: null, closes_at: null,
  capacity: null, waiting_list: true, confirmation_mode: 'auto', payment_url: '', notify_emails: '',
  confirmation_text_fr: '', confirmation_text_en: '', public_list: false, public_columns: [], purpose_fr: '', purpose_en: '',
  retention_days: DEFAULT_RETENTION_DAYS, event_id: null, competition: null,
  fields: [{ key: 'email', type: 'email', label_fr: 'E-mail', label_en: 'E-mail', required: true, help_fr: '', help_en: '', options: [], min: null, max: null }],
})
const form = ref<FormDefinition>(emptyForm())
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const loading = ref(true)
const deleteOpen = ref(false)
const typeItems = computed(() => FORM_FIELD_TYPES.map(value => ({ label: t(`cms.forms.type.${value}`), value })))
const modeItems = computed(() => (['auto', 'manual'] as const).map(value => ({ label: t(`cms.forms.mode.${value}`), value })))
const statusItems = computed(() => (['draft', 'published'] as const).map(value => ({ label: t(`cms.status.${value}`), value })))
const publishable = computed(() => form.value.fields.filter(field => !FORM_PERSONAL_TYPES.includes(field.type)))
const opensAt = computed({ get: () => toDateTimeLocal(form.value.opens_at), set: (value: string) => { form.value.opens_at = fromDateTimeLocal(value) } })
const closesAt = computed({ get: () => toDateTimeLocal(form.value.closes_at), set: (value: string) => { form.value.closes_at = fromDateTimeLocal(value) } })
const error = (field: string) => (errors.value[field] ? t(`cms.errors.${errors.value[field]}`) : undefined)

function addField() {
  const label = t('cms.forms.new_field')
  form.value.fields.push({ key: fieldKeyFromLabel(label, form.value.fields.map(field => field.key)), type: 'text', label_fr: label, label_en: '', required: false, help_fr: '', help_en: '', options: [], min: null, max: null })
}

function hasOptions(field: FormField): boolean {
  return FORM_TYPES_WITH_OPTIONS.includes(field.type)
}

function togglePublicColumn(key: string, checked: boolean) {
  const columns = new Set(form.value.public_columns)
  if (checked) columns.add(key)
  else columns.delete(key)
  form.value.public_columns = [...columns]
}

async function save() {
  errors.value = {}
  saving.value = true
  try {
    const saved = await cms.saveForm({ ...form.value, slug: form.value.slug || slugify(form.value.title_fr) })
    form.value = saved
    toast.add({ title: t('common.success'), description: t('cms.saved'), color: 'success', duration: 3000 })
    if (id.value === 'new') {
      await router.replace(`/cms/forms/${saved.id}`)
    }
  } catch (failure) {
    errors.value = validationErrors(failure)
  } finally {
    saving.value = false
  }
}

async function remove() {
  if (!form.value.id) return
  await cms.deleteForm(form.value.id)
  await router.push('/cms/forms')
}

onMounted(async () => {
  try {
    form.value = id.value === 'new' ? emptyForm() : await cms.form(id.value)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div v-if="!loading" class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <NuxtLink to="/cms/forms" class="text-sm text-primary-700 dark:text-primary-300 hover:underline">← {{ t('cms.forms.title') }}</NuxtLink>
        <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">{{ form.title_fr || t('cms.forms.new') }}</h1>
      </div>
      <div class="flex flex-wrap gap-2">
        <UButton v-if="form.id" variant="outline" icon="heroicons:users" :to="`/cms/forms/${form.id}/entries`">{{ t('cms.forms.entries') }}</UButton>
        <UButton v-if="form.id && form.status === 'published'" variant="outline" icon="heroicons:eye" :to="`${publicSiteUrl}/forms/${form.slug}`" target="_blank">{{ t('cms.forms.view') }}</UButton>
        <UButton :loading="saving" @click="save">{{ t('common.save') }}</UButton>
      </div>
    </div>
    <UAlert v-if="errors.fields === 'email_required'" color="error" variant="subtle" :title="t('cms.errors.email_required')" />

    <section class="grid gap-4 rounded-lg bg-white dark:bg-header-900 p-4 shadow md:grid-cols-2">
      <UFormField :label="t('cms.fields.title') + ' (FR)'" required :error="error('title_fr')"><UInput v-model="form.title_fr" class="w-full" /></UFormField>
      <UFormField :label="t('cms.fields.title') + ' (EN)'"><UInput v-model="form.title_en" class="w-full" /></UFormField>
      <UFormField :label="t('cms.fields.slug')" :error="error('slug')"><UInput v-model="form.slug" class="w-full" :placeholder="slugify(form.title_fr)" /></UFormField>
      <UFormField :label="t('cms.status_label')"><USelect v-model="form.status" :items="statusItems" class="w-full" /></UFormField>
      <UFormField :label="t('cms.forms.intro')" class="md:col-span-2"><CmsRichEditor v-model="form.intro_fr" /></UFormField>
      <UFormField :label="t('cms.forms.event_id')" :help="t('cms.forms.attach_help')" :error="error('event_id')"><UInputNumber v-model="form.event_id" class="w-full" :min="1" /></UFormField>
      <UFormField :label="t('cms.forms.competition')" :error="error('competition')"><UInput :model-value="form.competition ?? ''" class="w-full" placeholder="2026/N1H" @update:model-value="form.competition = String($event) || null" /></UFormField>
    </section>

    <section class="space-y-3 rounded-lg bg-white dark:bg-header-900 p-4 shadow" data-testid="form-fields">
      <h2 class="font-semibold">{{ t('cms.forms.fields') }}</h2>
      <div v-for="(field, index) in form.fields" :key="index" class="space-y-2 rounded border border-header-200 dark:border-header-700 p-3">
        <div class="flex flex-wrap items-end gap-2">
          <div class="flex flex-col">
            <UButton size="xs" variant="ghost" icon="heroicons:chevron-up" :disabled="index === 0" :aria-label="t('cms.move_up')" @click="form.fields = moveItem(form.fields, index, -1)" />
            <UButton size="xs" variant="ghost" icon="heroicons:chevron-down" :disabled="index === form.fields.length - 1" :aria-label="t('cms.move_down')" @click="form.fields = moveItem(form.fields, index, 1)" />
          </div>
          <UFormField :label="t('cms.forms.field_label')" :error="error(`fields.${index}.label_fr`)"><UInput v-model="field.label_fr" class="w-56" /></UFormField>
          <UFormField :label="t('cms.forms.field_label_en')"><UInput v-model="field.label_en" class="w-44" /></UFormField>
          <UFormField :label="t('cms.forms.field_type')"><USelect v-model="field.type" :items="typeItems" class="w-40" /></UFormField>
          <UFormField :label="t('cms.forms.field_key')" :error="error(`fields.${index}.key`)"><UInput v-model="field.key" class="w-36 font-mono" /></UFormField>
          <UCheckbox v-model="field.required" :label="t('cms.forms.required')" class="mb-2" />
          <UButton variant="ghost" color="error" icon="heroicons:trash" :aria-label="t('common.delete')" @click="form.fields.splice(index, 1)" />
        </div>
        <UFormField :label="t('cms.forms.help')"><UInput v-model="field.help_fr" class="w-full" /></UFormField>
        <div v-if="field.type === 'number'" class="flex gap-2">
          <UFormField :label="t('cms.forms.min')" :error="error(`fields.${index}.min`)"><UInputNumber v-model="field.min" class="w-32" /></UFormField>
          <UFormField :label="t('cms.forms.max')"><UInputNumber v-model="field.max" class="w-32" /></UFormField>
        </div>
        <div v-if="hasOptions(field)" class="space-y-1">
          <p class="text-sm font-medium">{{ t('cms.forms.options') }}</p>
          <div v-for="(option, optionIndex) in field.options" :key="optionIndex" class="flex gap-1">
            <UInput v-model="option.label_fr" class="w-56" :placeholder="t('cms.forms.option_label')" @update:model-value="option.value = option.value || slugify(String($event))" />
            <UInput v-model="option.value" class="w-36 font-mono" :placeholder="t('cms.forms.option_value')" />
            <UButton variant="ghost" color="neutral" icon="heroicons:x-mark" :aria-label="t('cms.remove')" @click="field.options.splice(optionIndex, 1)" />
          </div>
          <p v-if="errors[`fields.${index}.options`]" class="text-sm text-danger-600">{{ t(`cms.errors.options_${errors[`fields.${index}.options`]}`) }}</p>
          <UButton size="xs" variant="outline" icon="heroicons:plus" @click="field.options.push({ value: '', label_fr: '', label_en: '' })">{{ t('cms.forms.add_option') }}</UButton>
        </div>
      </div>
      <UButton variant="outline" icon="heroicons:plus" @click="addField">{{ t('cms.forms.add_field') }}</UButton>
    </section>

    <section class="grid gap-4 rounded-lg bg-white dark:bg-header-900 p-4 shadow md:grid-cols-3">
      <h2 class="font-semibold md:col-span-3">{{ t('cms.forms.opening') }}</h2>
      <UFormField :label="t('cms.forms.opens_at')" :error="error('opens_at')"><UInput v-model="opensAt" type="datetime-local" class="w-full" /></UFormField>
      <UFormField :label="t('cms.forms.closes_at')" :error="error('closes_at')"><UInput v-model="closesAt" type="datetime-local" class="w-full" /></UFormField>
      <UFormField :label="t('cms.forms.capacity')" :help="t('cms.forms.capacity_help')" :error="error('capacity')"><UInputNumber v-model="form.capacity" :min="1" class="w-full" /></UFormField>
      <UCheckbox v-model="form.waiting_list" :label="t('cms.forms.waiting_list')" />
      <UFormField :label="t('cms.forms.confirmation_mode')" :help="t('cms.forms.confirmation_help')"><USelect v-model="form.confirmation_mode" :items="modeItems" class="w-full" /></UFormField>
      <UFormField :label="t('cms.forms.payment_url')" :help="t('cms.forms.payment_help')" :error="error('payment_url')"><UInput v-model="form.payment_url" class="w-full" placeholder="https://www.helloasso.com/…" /></UFormField>
    </section>

    <section class="grid gap-4 rounded-lg bg-white dark:bg-header-900 p-4 shadow md:grid-cols-2">
      <h2 class="font-semibold md:col-span-2">{{ t('cms.forms.mails') }}</h2>
      <UFormField :label="t('cms.forms.notify_emails')" :help="t('cms.forms.notify_help')" :error="error('notify_emails')"><UInput v-model="form.notify_emails" class="w-full" /></UFormField>
      <UFormField :label="t('cms.forms.confirmation_text')"><UTextarea v-model="form.confirmation_text_fr" class="w-full" :rows="3" /></UFormField>
    </section>

    <section class="space-y-3 rounded-lg bg-white dark:bg-header-900 p-4 shadow">
      <h2 class="font-semibold">{{ t('cms.forms.public_list') }}</h2>
      <UCheckbox v-model="form.public_list" :label="t('cms.forms.public_list_enable')" />
      <fieldset v-if="form.public_list" class="space-y-1">
        <legend class="text-sm">{{ t('cms.forms.public_columns') }}</legend>
        <UCheckbox v-for="field in publishable" :key="field.key" :model-value="form.public_columns.includes(field.key)" :label="field.label_fr" @update:model-value="togglePublicColumn(field.key, !!$event)" />
        <p class="text-xs text-header-600 dark:text-header-300">{{ t('cms.forms.public_columns_help') }}</p>
        <p v-if="errors.public_columns" class="text-sm text-danger-600">{{ t('cms.errors.personal') }}</p>
      </fieldset>
    </section>

    <section class="grid gap-4 rounded-lg bg-white dark:bg-header-900 p-4 shadow md:grid-cols-2">
      <h2 class="font-semibold md:col-span-2">{{ t('cms.forms.gdpr') }}</h2>
      <UFormField :label="t('cms.forms.purpose')" :help="t('cms.forms.purpose_help')"><UTextarea v-model="form.purpose_fr" class="w-full" :rows="3" /></UFormField>
      <UFormField :label="t('cms.forms.retention_days')" :help="t('cms.forms.retention_help')" :error="error('retention_days')"><UInputNumber v-model="form.retention_days" :min="1" :max="730" class="w-40" /></UFormField>
    </section>

    <UButton v-if="form.id && authStore.canAdministerContent" color="error" variant="ghost" icon="heroicons:trash" @click="deleteOpen = true">{{ t('common.delete') }}</UButton>
    <AdminConfirmModal :open="deleteOpen" :title="t('common.delete')" :message="t('cms.forms.delete_confirm', { title: form.title_fr })" @close="deleteOpen = false" @confirm="remove" />
  </div>
</template>
