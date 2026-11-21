<script setup lang="ts">
/**
 * « Double authentification » section of /profile/security (AUTH_HARDENING.md § 5): status, activation, change of
 * method (AUTH-2FA-14), new recovery codes (AUTH-2FA-06), trusted devices (AUTH-2FA-11), deactivation (> 4 only).
 */
import { SecondFactorError, type SecondFactorStatus } from '~/composables/useSecondFactor'
import { graceDaysLeft, isCompleteCode, normalizeCode } from '~/utils/secondFactor'

type Action = 'enroll' | 'change' | 'recovery' | 'disable'

const { t } = useI18n()
const authStore = useAuthStore()
const secondFactor = useSecondFactor()

const status = ref<SecondFactorStatus | null>(null)
const action = ref<Action | null>(null)
/** Code of the active factor, typed before a change, new recovery codes or a deactivation. */
const proofCode = ref('')
const proofSent = ref(false)
const proved = ref(false)
const newRecoveryCodes = ref<string[] | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)
const notice = ref<string | null>(null)

const account = computed(() => authStore.user?.id ?? '')
const daysLeft = computed(() => graceDaysLeft(status.value?.enrollmentDueAt ?? null))
const enrollmentApi = computed(() => secondFactor.sessionEnrollment(action.value === 'change' ? normalizeCode(proofCode.value) : null))
const needsProof = computed(() => action.value === 'change' || action.value === 'recovery' || action.value === 'disable')

const errorKeys: Record<string, string> = {
  INVALID_CODE: 'second_factor.error.invalid_code',
  TOO_MANY_CODES: 'second_factor.error.too_many_codes',
  SECOND_FACTOR_MANDATORY: 'second_factor.settings.mandatory'
}

async function reload() {
  status.value = await secondFactor.status()
  secondFactor.refreshSummary({
    method: status.value.method,
    required: status.value.required,
    enrollmentDueAt: status.value.enrollmentDueAt
  })
}

async function run(task: () => Promise<void>) {
  error.value = null
  notice.value = null
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

function start(next: Action) {
  action.value = next
  proofCode.value = ''
  proofSent.value = false
  proved.value = next === 'enroll'
  newRecoveryCodes.value = null
  error.value = null
  notice.value = null
}

function close() {
  action.value = null
  proved.value = false
}

const sendProof = () => run(async () => {
  await secondFactor.sendProofCode()
  proofSent.value = true
})

/** The proof is checked by the call that needs it: setup (change), recovery codes, disable. */
const confirmProof = () => run(async () => {
  const code = normalizeCode(proofCode.value)
  if (action.value === 'change') {
    proved.value = true
  } else if (action.value === 'recovery') {
    newRecoveryCodes.value = await secondFactor.regenerateRecoveryCodes(code)
  } else if (action.value === 'disable') {
    await secondFactor.disable(code)
    close()
    notice.value = t('second_factor.settings.disabled')
    await reload()
  }
})

async function enrollmentDone() {
  close()
  notice.value = t('second_factor.settings.activated')
  await reload()
}

async function recoveryDone() {
  close()
  await reload()
}

const revokeTrusted = (id: number) => run(async () => {
  await secondFactor.revokeTrustedDevice(id)
  await reload()
})

function formatDate(value: string): string {
  const date = new Date(value.replace(' ', 'T'))
  return Number.isNaN(date.getTime()) ? value : date.toLocaleDateString()
}

onMounted(() => run(reload))
</script>

<template>
  <section class="bg-white dark:bg-header-900 rounded-lg shadow p-6 space-y-4" data-testid="second-factor-settings">
    <h2 class="text-lg font-semibold text-header-900 dark:text-header-50">{{ t('second_factor.settings.title') }}</h2>

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

    <template v-if="status && !action">
      <!-- Status -->
      <p v-if="status.method" class="text-sm text-header-700 dark:text-header-200">
        {{ t('second_factor.settings.active', { method: t(`second_factor.method.${status.method}`) }) }}
        · {{ t('second_factor.settings.recovery_left', { count: status.recoveryCodesLeft }) }}
      </p>
      <p v-else-if="status.required" class="text-sm text-warning-700 dark:text-warning-300">
        {{ daysLeft !== null && status.enforcedFrom
          ? t('second_factor.settings.required_by', { days: daysLeft })
          : t('second_factor.settings.required') }}
      </p>
      <p v-else class="text-sm text-header-700 dark:text-header-200">{{ t('second_factor.settings.optional') }}</p>

      <div class="flex flex-wrap gap-2">
        <UButton v-if="!status.method" color="primary" size="sm" :disabled="loading" @click="start('enroll')">
          {{ t('second_factor.settings.enable') }}
        </UButton>
        <template v-else>
          <UButton color="neutral" variant="outline" size="sm" :disabled="loading" @click="start('change')">
            {{ t('second_factor.settings.change') }}
          </UButton>
          <UButton color="neutral" variant="outline" size="sm" :disabled="loading" @click="start('recovery')">
            {{ t('second_factor.settings.new_recovery_codes') }}
          </UButton>
          <UButton v-if="status.canDisable" color="danger" variant="ghost" size="sm" :disabled="loading" @click="start('disable')">
            {{ t('second_factor.settings.disable') }}
          </UButton>
        </template>
      </div>
      <p v-if="status.method && !status.canDisable" class="text-sm text-header-600 dark:text-header-300">
        {{ t('second_factor.settings.mandatory') }}
      </p>

      <!-- Trusted devices -->
      <div v-if="status.trustedDevices.length > 0" class="pt-2">
        <h3 class="text-sm font-semibold text-header-900 dark:text-header-50 mb-2">{{ t('second_factor.settings.trusted_title') }}</h3>
        <ul class="divide-y divide-header-200 dark:divide-header-800">
          <li v-for="device in status.trustedDevices" :key="device.id" class="py-2 flex items-center justify-between gap-4">
            <div class="text-sm text-header-700 dark:text-header-200">
              {{ device.label }}
              <span class="text-header-600 dark:text-header-300">· {{ t('second_factor.settings.trusted_until', { date: formatDate(device.expiresAt) }) }}</span>
            </div>
            <UButton color="danger" variant="ghost" size="sm" :disabled="loading" @click="revokeTrusted(device.id)">
              {{ t('security.devices.revoke') }}
            </UButton>
          </li>
        </ul>
      </div>
    </template>

    <!-- Proof of the active factor -->
    <form v-if="action && needsProof && !proved && !newRecoveryCodes" class="space-y-3" @submit.prevent="confirmProof">
      <p class="text-sm text-header-700 dark:text-header-200">
        {{ status?.method === 'email' ? t('second_factor.settings.proof_email') : t('second_factor.settings.proof_totp') }}
      </p>
      <UButton v-if="status?.method === 'email'" type="button" color="neutral" variant="outline" size="sm" :disabled="loading" @click="sendProof">
        {{ proofSent ? t('second_factor.resend') : t('second_factor.settings.send_code') }}
      </UButton>
      <UInput v-model="proofCode" inputmode="numeric" autocomplete="one-time-code" placeholder="123 456" autofocus />
      <div class="flex gap-2">
        <UButton type="button" color="neutral" variant="ghost" @click="close">{{ t('common.cancel') }}</UButton>
        <UButton type="submit" :color="action === 'disable' ? 'danger' : 'primary'" :loading="loading" :disabled="!isCompleteCode(proofCode)">
          {{ t(`second_factor.settings.confirm_${action}`) }}
        </UButton>
      </div>
    </form>

    <!-- Enrollment (first activation or change of method) -->
    <AuthSecondFactorEnrollment
      v-if="status && action && proved && (action === 'enroll' || action === 'change')"
      :api="enrollmentApi"
      :account="account"
      :totp-available="status.totpAvailable"
      :masked-email="status.maskedEmail"
      @done="enrollmentDone"
      @cancel="close"
    />

    <AuthRecoveryCodes v-if="newRecoveryCodes" :codes="newRecoveryCodes" :account="account" @acknowledged="recoveryDone" />
  </section>
</template>
