<script setup lang="ts">
definePageMeta({
  layout: 'default',
  middleware: 'auth'
})

const { t, locale, setLocale } = useI18n()
const config = useRuntimeConfig()

const languages = [
  { code: 'fr', label: 'FR', flag: '🇫🇷' },
  { code: 'en', label: 'EN', flag: '🇬🇧' }
]

const identifier = ref('')
const loading = ref(false)
const submitted = ref(false)
const error = ref<string | null>(null)

async function handleSubmit() {
  if (!identifier.value.trim() || loading.value) return

  error.value = null
  loading.value = true

  try {
    const response = await fetch(`${config.public.api2BaseUrl}/auth/forgot-password`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ identifier: identifier.value.trim(), locale: locale.value })
    })

    if (!response.ok) {
      error.value = t('login.error_generic')
      return
    }

    submitted.value = true
  } catch {
    error.value = t('login.error_generic')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4">
    <!-- Language switcher -->
    <div class="fixed top-4 right-4 flex gap-2">
      <button
        v-for="lang in languages"
        :key="lang.code"
        :class="[
          'text-2xl transition-all duration-200 cursor-pointer',
          locale === lang.code
            ? 'opacity-100 scale-110 drop-shadow-lg'
            : 'opacity-50 hover:opacity-75 hover:scale-105'
        ]"
        :title="lang.label"
        @click="setLocale(lang.code as 'en' | 'fr')"
      >
        {{ lang.flag }}
      </button>
    </div>
    <div class="w-full max-w-md">
      <!-- Logo -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-primary-600">KPI Admin</h1>
      </div>

      <div class="bg-white rounded-lg shadow-lg p-8">
        <h2 class="text-xl font-semibold text-header-900 mb-2">
          {{ t('forgot_password.title') }}
        </h2>
        <p class="text-sm text-header-600 mb-6">{{ t('forgot_password.subtitle') }}</p>

        <!-- Success -->
        <div v-if="submitted" class="space-y-4">
          <div class="p-3 bg-success-50 border border-success-200 rounded-lg text-success-700 text-sm">
            {{ t('forgot_password.success') }}
          </div>
          <NuxtLink
            to="/login"
            class="block text-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium"
          >
            {{ t('login.title') }}
          </NuxtLink>
        </div>

        <!-- Form -->
        <form v-else class="space-y-4" @submit.prevent="handleSubmit">
          <div
            v-if="error"
            class="p-3 bg-danger-50 border border-danger-200 rounded-lg text-danger-700 text-sm"
          >
            {{ error }}
          </div>

          <div>
            <label class="block text-sm font-medium text-header-700 mb-1">
              {{ t('forgot_password.identifier') }}
            </label>
            <UInput
              v-model="identifier"
              type="text"
              variant="outline"
              :placeholder="t('forgot_password.identifier_placeholder')"
              required
              autofocus
            />
          </div>

          <UButton
            type="submit"
            color="primary"
            block
            :loading="loading"
            :disabled="!identifier.trim() || loading"
          >
            {{ t('forgot_password.submit') }}
          </UButton>

          <NuxtLink
            to="/login"
            class="block text-center text-sm text-header-500 hover:text-header-700"
          >
            {{ t('access_request.back_to_login') }}
          </NuxtLink>
        </form>
      </div>
    </div>
  </div>
</template>
