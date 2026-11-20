<script setup lang="ts">
/**
 * Second sign-in step (AUTH_HARDENING.md § 5): code of the active factor (AUTH-2FA-07) or enrollment required once
 * the grace is over (AUTH-2FA-02). Reached from /login (token in memory) or from the Google callback (token in
 * the URL fragment, AUTH-2FA-12).
 */
import { PasswordRenewalRequiredError } from '~/composables/useAuth'
import { SecondFactorError, useSecondFactorStep } from '~/composables/useSecondFactor'
import { accountOf, isCompleteCode, normalizeCode, parseSecondFactorFragment } from '~/utils/secondFactor'

definePageMeta({
  layout: 'default',
  middleware: 'auth'
})

const { t } = useI18n()
const step = useSecondFactorStep()
const { verify, sendLoginCode, tokenEnrollment } = useSecondFactor()

const code = ref('')
const trustDevice = ref(false)
const loading = ref(false)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)
const expired = ref(false)

const enrollment = computed(() => step.value?.mode === 'enroll' ? tokenEnrollment(step.value.token) : null)
const canSubmit = computed(() => isCompleteCode(code.value) && !loading.value)

const errorKeys: Record<string, string> = {
  INVALID_CODE: 'second_factor.error.invalid_code',
  ACCOUNT_LOCKED: 'second_factor.error.locked',
  TOO_MANY_CODES: 'second_factor.error.too_many_codes',
  EMAIL_REQUIRED: 'second_factor.error.email_required'
}

onMounted(() => {
  const fromGoogle = parseSecondFactorFragment(window.location.hash)
  if (fromGoogle) {
    step.value = fromGoogle
    window.history.replaceState({}, '', window.location.pathname)
  }
  if (!step.value) {
    navigateTo('/login')
  }
})

function showError(e: unknown) {
  if (e instanceof SecondFactorError && e.code === 'INVALID_TOKEN') {
    expired.value = true
    error.value = t('second_factor.error.expired')
    return
  }
  const key = e instanceof SecondFactorError ? errorKeys[e.code] : undefined
  error.value = t(key ?? 'second_factor.error.generic')
}

async function submit() {
  if (!canSubmit.value || !step.value) return
  error.value = null
  loading.value = true
  try {
    const result = await verify(step.value.token, normalizeCode(code.value), trustDevice.value)
    step.value = null
    await navigateTo(result.hasMandates ? '/select-mandate' : '/')
  } catch (e) {
    if (e instanceof PasswordRenewalRequiredError) {
      step.value = null
      await navigateTo('/renew-password')
      return
    }
    showError(e)
  } finally {
    loading.value = false
  }
}

async function resend() {
  if (!step.value) return
  error.value = null
  notice.value = null
  try {
    await sendLoginCode(step.value.token)
    notice.value = t('second_factor.code_sent', { email: step.value.maskedEmail ?? '—' })
  } catch (e) {
    showError(e)
  }
}

async function enrollmentDone() {
  const destination = enrollment.value?.next() ?? '/'
  step.value = null
  await navigateTo(destination)
}

async function cancel() {
  step.value = null
  await navigateTo('/login')
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-primary-600 dark:text-primary-300">KPI Admin</h1>
      </div>

      <div v-if="step" class="bg-white dark:bg-header-900 rounded-lg shadow-lg p-8 space-y-4">
        <!-- Enrollment required (grace over) -->
        <template v-if="step.mode === 'enroll' && enrollment">
          <h2 class="text-xl font-semibold text-header-900 dark:text-header-50">{{ t('second_factor.enroll.required_title') }}</h2>
          <p class="text-sm text-header-700 dark:text-header-200">{{ t('second_factor.enroll.required_intro') }}</p>
          <AuthSecondFactorEnrollment
            :api="enrollment"
            :account="accountOf(step.token)"
            :totp-available="step.totpAvailable"
            :masked-email="step.maskedEmail"
            @done="enrollmentDone"
            @cancel="cancel"
          />
        </template>

        <!-- Code of the active factor -->
        <template v-else>
          <h2 class="text-xl font-semibold text-header-900 dark:text-header-50">{{ t('second_factor.challenge.title') }}</h2>
          <p class="text-sm text-header-700 dark:text-header-200">
            {{ step.method === 'email'
              ? t('second_factor.challenge.email_intro', { email: step.maskedEmail ?? '—' })
              : t('second_factor.challenge.totp_intro') }}
          </p>

          <div
            v-if="error"
            class="p-3 bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 rounded text-sm text-danger-700 dark:text-danger-300"
          >
            {{ error }}
          </div>
          <div
            v-if="notice"
            class="p-3 bg-success-50 dark:bg-success-950 border border-success-200 dark:border-success-800 rounded text-sm text-success-700 dark:text-success-300"
          >
            {{ notice }}
          </div>

          <NuxtLink
            v-if="expired"
            to="/login"
            class="block text-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium"
          >
            {{ t('login.renewal.back_to_login') }}
          </NuxtLink>

          <form v-else class="space-y-4" @submit.prevent="submit">
            <div>
              <label for="second-factor-code" class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">
                {{ t('second_factor.code_label') }}
              </label>
              <UInput
                id="second-factor-code"
                v-model="code"
                inputmode="numeric"
                autocomplete="one-time-code"
                placeholder="123 456"
                required
                autofocus
              />
              <p class="mt-1 text-xs text-header-600 dark:text-header-300">{{ t('second_factor.challenge.recovery_hint') }}</p>
            </div>

            <UCheckbox v-model="trustDevice" :label="t('second_factor.challenge.trust_device')" />

            <UButton type="submit" color="primary" block :loading="loading" :disabled="!canSubmit">
              {{ t('second_factor.challenge.submit') }}
            </UButton>

            <div class="flex justify-between">
              <UButton v-if="step.method === 'email'" type="button" color="neutral" variant="link" size="sm" @click="resend">
                {{ t('second_factor.resend') }}
              </UButton>
              <UButton type="button" color="neutral" variant="link" size="sm" class="ml-auto" @click="cancel">
                {{ t('login.renewal.back_to_login') }}
              </UButton>
            </div>
          </form>
        </template>
      </div>
    </div>
  </div>
</template>
