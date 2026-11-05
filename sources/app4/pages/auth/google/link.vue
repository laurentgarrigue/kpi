<script setup lang="ts">
/**
 * Confirmation du lien d'un compte Google à un compte KPI existant (AUTH_HARDENING § 4).
 * L'utilisateur arrive ici depuis api2 /auth/google/callback après avoir prouvé son identité
 * Google ; il doit saisir le mot de passe actuel du compte KPI pour qu'on écrive la ligne
 * dans kp_user_oauth. Sans ça, un homonyme Google pourrait voler un compte par confusion de mail.
 */
definePageMeta({
  layout: 'default',
  middleware: 'auth'
})

const { t, locale, setLocale } = useI18n()
const config = useRuntimeConfig()
const route = useRoute()

const languages = [
  { code: 'fr', label: 'FR', flag: '🇫🇷' },
  { code: 'en', label: 'EN', flag: '🇬🇧' }
]

const userCode = computed<string>(() => String(route.query.code ?? ''))
const password = ref('')
const loading = ref(false)
const error = ref<string | null>(null)

async function handleSubmit() {
  if (!password.value || loading.value) return
  error.value = null
  loading.value = true
  try {
    const resp = await fetch(`${config.public.api2BaseUrl}/auth/google/link`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code: userCode.value, password: password.value })
    })
    if (resp.status === 302 || resp.ok) {
      // Le backend répond en redirect vers /?oauth=ok, qui pose les cookies et finit le login.
      window.location.href = '/?oauth=ok'
      return
    }
    const data = await resp.json().catch(() => ({ message: '' }))
    if (resp.status === 401) {
      error.value = t('login.oauth_link.bad_password')
    } else if (resp.status === 400) {
      error.value = t('login.oauth_link.expired')
    } else {
      error.value = data?.message || t('login.error_generic')
    }
  } catch {
    error.value = t('login.error_generic')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4">
    <div class="fixed top-4 right-4 flex gap-2">
      <button
        v-for="lang in languages"
        :key="lang.code"
        :class="[
          'text-2xl transition-all duration-200 cursor-pointer',
          locale === lang.code ? 'opacity-100 scale-110 drop-shadow-lg' : 'opacity-50 hover:opacity-75 hover:scale-105'
        ]"
        :title="lang.label"
        @click="setLocale(lang.code as 'en' | 'fr')"
      >
        {{ lang.flag }}
      </button>
    </div>

    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-primary-600 dark:text-primary-300">KPI Admin</h1>
        <p class="mt-2 text-header-900 dark:text-header-50">{{ t('login.oauth_link.title') }}</p>
      </div>

      <div class="bg-white dark:bg-header-900 rounded-lg shadow-lg p-8">
        <p class="text-sm text-header-900 dark:text-header-50 mb-4">
          {{ t('login.oauth_link.explain', { code: userCode }) }}
        </p>

        <div
          v-if="error"
          class="mb-4 p-3 bg-danger-50 dark:bg-danger-950 border border-danger-200 dark:border-danger-800 rounded-lg text-danger-700 dark:text-danger-400 text-sm"
        >
          {{ error }}
        </div>

        <form class="space-y-4" @submit.prevent="handleSubmit">
          <div>
            <label for="password" class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">
              {{ t('login.password') }}
            </label>
            <UInput
              id="password"
              v-model="password"
              type="password"
              variant="outline"
              :placeholder="t('login.password_placeholder')"
              required
              autofocus
            />
          </div>

          <UButton type="submit" color="primary" block :loading="loading" :disabled="loading || !password">
            {{ t('login.oauth_link.submit') }}
          </UButton>
        </form>

        <div class="mt-4 text-center">
          <NuxtLink to="/login" class="text-sm text-header-600 dark:text-header-300 hover:text-header-900">
            {{ t('login.oauth_link.cancel') }}
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>
