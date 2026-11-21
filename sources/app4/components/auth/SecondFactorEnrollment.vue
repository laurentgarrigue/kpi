<script setup lang="ts">
/**
 * Enrollment of a second factor (AUTH_HARDENING.md § 5, AUTH-2FA-04 and 05): TOTP application (recommended) or
 * e-mail code, then the recovery codes. Used at sign-in (enrollment token) and in /profile/security (session):
 * the `api` prop carries the calls of either case.
 */
import { SecondFactorError, type EnrollmentApi, type TotpSetup } from '~/composables/useSecondFactor'
import { isCompleteCode, normalizeCode, type SecondFactorMethod } from '~/utils/secondFactor'

const props = defineProps<{
  api: EnrollmentApi
  account: string
  totpAvailable: boolean
  maskedEmail: string | null
}>()

const emit = defineEmits<{ done: [], cancel: [] }>()

const { t } = useI18n()

const method = ref<SecondFactorMethod | null>(null)
const totp = ref<TotpSetup | null>(null)
const emailSent = ref(false)
const code = ref('')
const recoveryCodes = ref<string[] | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const qrImage = computed(() => totp.value ? `data:image/svg+xml;charset=utf-8,${encodeURIComponent(totp.value.qrSvg)}` : '')
const groupedSecret = computed(() => totp.value?.secret.match(/.{1,4}/g)?.join(' ') ?? '')
const canActivate = computed(() => isCompleteCode(code.value) && !loading.value)

const errorKeys: Record<string, string> = {
  INVALID_CODE: 'second_factor.error.invalid_code',
  INVALID_TOKEN: 'second_factor.error.expired',
  EMAIL_REQUIRED: 'second_factor.error.email_required',
  TOO_MANY_CODES: 'second_factor.error.too_many_codes',
  SECOND_FACTOR_NOT_CONFIGURED: 'second_factor.error.totp_unavailable'
}

async function run(task: () => Promise<void>) {
  error.value = null
  loading.value = true
  try {
    await task()
  } catch (e) {
    const key = e instanceof SecondFactorError ? errorKeys[e.code] : undefined
    error.value = t(key ?? 'second_factor.error.generic')
  } finally {
    loading.value = false
  }
}

function choose(selected: SecondFactorMethod) {
  method.value = selected
  code.value = ''
  return run(async () => {
    if (selected === 'totp') {
      totp.value = await props.api.setupTotp()
    } else {
      await props.api.setupEmail()
      emailSent.value = true
    }
  })
}

function resendEmail() {
  return run(async () => {
    await props.api.setupEmail()
    emailSent.value = true
  })
}

function activate() {
  if (!canActivate.value || !method.value) return
  const typed = normalizeCode(code.value)
  return run(async () => {
    recoveryCodes.value = method.value === 'totp'
      ? await props.api.activateTotp(typed)
      : await props.api.activateEmail(typed)
  })
}

function back() {
  method.value = null
  totp.value = null
  emailSent.value = false
  error.value = null
}
</script>

<template>
  <div class="space-y-4">
    <AuthRecoveryCodes v-if="recoveryCodes" :codes="recoveryCodes" :account="account" @acknowledged="emit('done')" />

    <template v-else>
      <div
        v-if="error"
        class="p-3 bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 rounded text-sm text-danger-700 dark:text-danger-300"
      >
        {{ error }}
      </div>

      <!-- Choice of the method -->
      <div v-if="!method" class="space-y-3">
        <p class="text-sm text-header-700 dark:text-header-200">{{ t('second_factor.enroll.choose') }}</p>
        <button
          v-if="totpAvailable"
          type="button"
          class="w-full text-left p-4 rounded-lg border-2 border-primary-300 dark:border-primary-700 hover:bg-primary-50 dark:hover:bg-primary-950"
          :disabled="loading"
          data-testid="enroll-totp"
          @click="choose('totp')"
        >
          <div class="font-semibold text-header-900 dark:text-header-50">
            {{ t('second_factor.enroll.totp_title') }}
            <span class="ml-2 text-sm font-semibold px-2 py-0.5 rounded bg-success-100 dark:bg-success-900 text-success-800 dark:text-success-200">{{ t('second_factor.enroll.recommended') }}</span>
          </div>
          <div class="text-sm text-header-600 dark:text-header-300">{{ t('second_factor.enroll.totp_help') }}</div>
        </button>
        <button
          type="button"
          class="w-full text-left p-4 rounded-lg border border-header-300 dark:border-header-700 hover:bg-header-50 dark:hover:bg-header-800"
          :disabled="loading"
          data-testid="enroll-email"
          @click="choose('email')"
        >
          <div class="font-semibold text-header-900 dark:text-header-50">{{ t('second_factor.enroll.email_title') }}</div>
          <div class="text-sm text-header-600 dark:text-header-300">
            {{ t('second_factor.enroll.email_help', { email: maskedEmail ?? '—' }) }}
          </div>
        </button>
        <p class="text-sm text-header-600 dark:text-header-300">{{ t('second_factor.enroll.email_limit') }}</p>
        <UButton color="neutral" variant="ghost" size="sm" @click="emit('cancel')">{{ t('common.cancel') }}</UButton>
      </div>

      <!-- TOTP -->
      <div v-else-if="method === 'totp'" class="space-y-4">
        <p class="text-sm text-header-700 dark:text-header-200">{{ t('second_factor.enroll.totp_scan') }}</p>
        <div v-if="totp" class="flex flex-col items-center gap-3">
          <img :src="qrImage" :alt="t('second_factor.enroll.qr_alt')" class="w-48 h-48 bg-white p-2 rounded">
          <div class="text-sm text-header-600 dark:text-header-300">{{ t('second_factor.enroll.totp_manual') }}</div>
          <code class="font-mono text-sm text-header-900 dark:text-header-50 select-all" data-testid="totp-secret">{{ groupedSecret }}</code>
        </div>
      </div>

      <!-- E-mail -->
      <div v-else class="space-y-2">
        <p v-if="emailSent" class="text-sm text-header-700 dark:text-header-200">
          {{ t('second_factor.code_sent', { email: maskedEmail ?? '—' }) }}
        </p>
        <UButton color="neutral" variant="link" size="sm" :disabled="loading" @click="resendEmail">
          {{ t('second_factor.resend') }}
        </UButton>
      </div>

      <form v-if="method" class="space-y-3" @submit.prevent="activate">
        <label for="enroll-code" class="block text-sm font-medium text-header-900 dark:text-header-50">{{ t('second_factor.code_label') }}</label>
        <UInput id="enroll-code" v-model="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123 456" autofocus />
        <div class="flex gap-2">
          <UButton type="button" color="neutral" variant="ghost" @click="back">{{ t('second_factor.enroll.other_method') }}</UButton>
          <UButton type="submit" color="primary" class="flex-1 justify-center" :loading="loading" :disabled="!canActivate">
            {{ t('second_factor.enroll.activate') }}
          </UButton>
        </div>
      </form>
    </template>
  </div>
</template>
