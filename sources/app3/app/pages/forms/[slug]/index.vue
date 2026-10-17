<script setup lang="ts">
import { HONEYPOT_FIELD, TOKEN_FIELD, type SubmissionOutcome } from '~~/shared/utils/form-submission'
import { formApiPath, formDateTime, postedValue, type FormDefinition } from '~/utils/forms'

// Registration form (FEATURE_FORMS.md § 3, FRM-01): a plain HTML form posted to this page; the server relays it to
// api2 (server/middleware/form-submission.ts) and renders the confirmation or the errors, with or without JavaScript.
const route = useRoute()
const { t, locale } = useI18n()
const localePath = useLocalePath()
const language = useLanguageTag()

const slug = computed(() => String(route.params.slug))
const { data: form } = await useApiResource<FormDefinition>(() => formApiPath(slug.value, locale.value))

// Set by the server while rendering the response to a POST, sent to the browser with the payload.
const outcome = useState<SubmissionOutcome | null>(`form-outcome:${slug.value}`, () => useRequestEvent()?.context.formSubmission ?? null)
const success = computed(() => (outcome.value?.kind === 'success' ? outcome.value.result : null))
const errors = computed(() => (outcome.value?.kind === 'invalid' ? outcome.value.errors : {}))
const rejected = computed(() => (outcome.value?.kind === 'rejected' ? outcome.value.reason : null))
const values = computed(() => (outcome.value && outcome.value.kind !== 'success' ? outcome.value.values : undefined))
const erroneousFields = computed(() => (form.value?.fields ?? []).filter(field => errors.value[field.key]))

const stateMessage = computed(() => {
  const current = form.value
  if (!current) {
    return ''
  }
  if (current.state === 'closed') {
    return t('forms.state.closed')
  }
  if (current.state === 'upcoming') {
    return t('forms.state.upcoming', { date: formDateTime(current.opens_at ?? '', language.value) })
  }
  if (current.full) {
    return t('forms.state.full')
  }
  return current.closes_at && !current.closes_at.startsWith('9999')
    ? t('forms.state.open', { date: formDateTime(current.closes_at, language.value) })
    : t('forms.state.openNoEnd')
})

// The error summary takes the focus, so that screen readers announce it (FEATURE_FORMS.md § 3.2).
const summary = useTemplateRef<HTMLElement>('summary')
onMounted(() => summary.value?.focus())
// The outcome belongs to this response only: coming back to the form later shows it empty.
onBeforeUnmount(() => {
  outcome.value = null
})

useSeoMeta({
  title: () => form.value?.title,
  robots: 'noindex, nofollow',
})
</script>

<template>
  <div v-if="form" class="mx-auto max-w-3xl space-y-6">
    <SiteBreadcrumb :items="[{ label: $t('nav.home'), to: localePath('/') }, { label: form.title }]" />
    <h1 class="text-5xl leading-tight text-kpi-blue-600">{{ form.title }}</h1>

    <!-- Confirmation (FEATURE_FORMS.md § 3.3) -->
    <section v-if="success" class="space-y-4 rounded border border-line p-6" aria-labelledby="form-outcome" data-testid="form-success" :data-status="success.status">
      <h2 id="form-outcome" class="text-3xl" :class="success.status === 'confirmed' ? 'text-kpi-green-600' : 'text-kpi-gold-700'">
        {{ success.status === 'confirmed' ? $t('forms.confirmedTitle') : $t('forms.waitingTitle') }}
      </h2>
      <p v-if="success.status === 'waiting'" data-testid="form-waiting-reason">{{ success.reason === 'full' ? $t('forms.waitingFull') : $t('forms.waitingManual') }}</p>
      <p>{{ $t('forms.emailSent') }}</p>
      <p v-if="success.payment_url" class="space-y-1">
        <UButton :to="success.payment_url" external target="_blank" rel="noopener" icon="i-heroicons-credit-card" data-testid="form-payment">
          {{ $t('forms.payment') }}<span class="sr-only">{{ $t('a11y.newTab') }}</span>
        </UButton>
        <span class="block text-sm text-ink/70">{{ $t('forms.paymentHint') }}</span>
      </p>
      <NuxtLink v-if="form.public_list" :to="localePath(`/forms/${form.slug}/entries`)" class="block hover:underline">{{ $t('forms.entries') }}</NuxtLink>
    </section>

    <template v-else>
      <!-- eslint-disable-next-line vue/no-v-html -- HTML filtered by the api2 whitelist (FEATURE_CMS.md § 3.2) -->
      <div v-if="form.intro" class="kpi-prose" data-testid="form-intro" v-html="form.intro" />
      <p class="font-semibold" data-testid="form-state" :data-state="form.state">{{ stateMessage }}</p>
      <p v-if="form.capacity" class="text-sm text-ink/70">{{ $t('forms.places', { confirmed: form.confirmed, capacity: form.capacity }) }}</p>
      <p v-if="form.manual_confirmation && form.state === 'open'" class="text-sm">{{ $t('forms.manual') }}</p>
      <NuxtLink v-if="form.public_list" :to="localePath(`/forms/${form.slug}/entries`)" class="inline-block hover:underline" data-testid="form-entries-link">{{ $t('forms.entries') }} →</NuxtLink>

      <div v-if="rejected" ref="summary" tabindex="-1" role="alert" class="rounded bg-kpi-red-50 p-4 text-kpi-red-800" data-testid="form-rejected">
        {{ $t(`forms.rejected.${rejected}`, $t('forms.rejected.unavailable')) }}
      </div>
      <div v-else-if="erroneousFields.length > 0" ref="summary" tabindex="-1" role="alert" class="space-y-2 rounded bg-kpi-red-50 p-4 text-kpi-red-800" data-testid="form-error-summary">
        <p class="font-semibold">{{ $t('forms.errorSummary', { count: erroneousFields.length }, erroneousFields.length) }}</p>
        <ul class="list-disc pl-6">
          <li v-for="field in erroneousFields" :key="field.key"><a :href="`#field-${field.key}`" class="underline">{{ field.label }}</a></li>
        </ul>
      </div>

      <form v-if="form.state === 'open'" method="post" :action="localePath(`/forms/${form.slug}`)" class="space-y-4" novalidate data-testid="form">
        <p class="text-sm text-ink/70">{{ $t('forms.requiredHint') }}</p>
        <input type="hidden" :name="TOKEN_FIELD" :value="form.token">
        <!-- Honeypot: invisible to people, filled by robots (FRM-05) -->
        <div class="absolute -left-[9999px]" aria-hidden="true">
          <label :for="HONEYPOT_FIELD">{{ $t('forms.honeypot') }}</label>
          <input :id="HONEYPOT_FIELD" type="text" :name="HONEYPOT_FIELD" tabindex="-1" autocomplete="off">
        </div>
        <FormField
          v-for="field in form.fields"
          :key="field.key"
          :field="field"
          :values="postedValue(values, field.key)"
          :error="errors[field.key] ?? null"
        />
        <UButton type="submit" size="lg" data-testid="form-submit">{{ $t('forms.submit') }}</UButton>
      </form>

      <section aria-labelledby="form-privacy" class="border-t border-line pt-4 text-sm text-ink/70" data-testid="form-privacy">
        <h2 id="form-privacy" class="font-semibold">{{ $t('forms.privacy') }}</h2>
        <p>{{ $t('forms.privacyText', { purpose: form.purpose, days: form.retention_days }) }}</p>
      </section>
    </template>
  </div>
</template>
