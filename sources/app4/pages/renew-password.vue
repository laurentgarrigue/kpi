<script setup lang="ts">
import { PasswordRenewalError } from '~/composables/useAuth'

definePageMeta({
  layout: 'default'
})

const { t } = useI18n()
const { renewPassword } = useAuth()
const renewalToken = usePasswordRenewalToken()

const password = ref('')
const confirmPassword = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const expired = ref(false)

const { rules, allRulesValid } = usePasswordRules(password)
const passwordsMatch = computed(() => password.value === confirmPassword.value && confirmPassword.value.length > 0)
const canSubmit = computed(() => allRulesValid.value && passwordsMatch.value && !loading.value)

const errorKeys: Record<string, string> = {
  WEAK_PASSWORD: 'login.renewal.error_weak',
  PASSWORD_REUSED: 'login.renewal.error_reused'
}

// Reached without a token (reload, direct link): the renewal starts from the login page.
onMounted(() => {
  if (!renewalToken.value) {
    navigateTo('/login')
  }
})

async function handleSubmit() {
  if (!canSubmit.value || !renewalToken.value) return

  error.value = null
  loading.value = true

  try {
    const result = await renewPassword(renewalToken.value, password.value)
    renewalToken.value = null
    await navigateTo(result.hasMandates ? '/select-mandate' : '/')
  } catch (e) {
    const code = e instanceof PasswordRenewalError ? e.code : 'INVALID_TOKEN'
    expired.value = !(code in errorKeys)
    error.value = t(errorKeys[code] ?? 'login.renewal.error_expired')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-primary-600 dark:text-primary-300">KPI Admin</h1>
      </div>

      <div class="bg-white dark:bg-header-900 rounded-lg shadow-lg p-8">
        <h2 class="text-xl font-semibold text-header-900 dark:text-header-50 mb-2">
          {{ t('login.renewal.title') }}
        </h2>
        <p class="text-sm text-header-700 dark:text-header-200 mb-6">
          {{ t('login.renewal.intro') }}
        </p>

        <div
          v-if="error"
          class="mb-4 p-3 bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 rounded-lg text-danger-700 dark:text-danger-400 text-sm"
        >
          {{ error }}
        </div>

        <NuxtLink
          v-if="expired"
          to="/login"
          class="block text-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium"
        >
          {{ t('login.renewal.back_to_login') }}
        </NuxtLink>

        <form v-else class="space-y-4" @submit.prevent="handleSubmit">
          <div>
            <label for="new-password" class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">
              {{ t('users.reset_password.new_password') }}
            </label>
            <UInput id="new-password" v-model="password" type="password" autocomplete="new-password" required autofocus />
          </div>

          <div class="text-xs text-header-900 dark:text-header-50 space-y-0.5">
            <div class="font-medium mb-1">{{ t('users.reset_password.rules') }}</div>
            <div v-for="rule in rules" :key="rule.key" class="flex items-center gap-1.5">
              <UIcon
                :name="rule.valid ? 'i-heroicons-check-circle' : 'i-heroicons-x-circle'"
                :class="rule.valid ? 'text-success-500' : 'text-header-600 dark:text-header-300'"
                class="w-4 h-4"
              />
              <span :class="rule.valid ? 'text-success-700 dark:text-success-300' : 'text-header-600 dark:text-header-300'">{{ rule.label }}</span>
            </div>
          </div>

          <div>
            <label for="confirm-password" class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">
              {{ t('users.reset_password.confirm_password') }}
            </label>
            <UInput id="confirm-password" v-model="confirmPassword" type="password" autocomplete="new-password" required />
            <p v-if="confirmPassword && !passwordsMatch" class="mt-1 text-xs text-danger-500">
              {{ t('users.reset_password.error_mismatch') }}
            </p>
          </div>

          <UButton type="submit" color="primary" block :loading="loading" :disabled="!canSubmit">
            {{ t('login.renewal.submit') }}
          </UButton>
        </form>
      </div>
    </div>
  </div>
</template>
