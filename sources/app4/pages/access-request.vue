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

const form = ref({
  email: '',
  licence: '',
  nom: '',
  prenom: '',
  club: '',
  responsabilites: '',
  message: ''
})

const loading = ref(false)
const error = ref<string | null>(null)
const success = ref(false)

const canSubmit = computed(() =>
  form.value.email.trim() &&
  form.value.nom.trim() &&
  form.value.prenom.trim() &&
  form.value.responsabilites.trim() &&
  form.value.message.trim() &&
  !loading.value
)

async function handleSubmit() {
  if (!canSubmit.value) return

  error.value = null
  loading.value = true

  try {
    const response = await fetch(`${config.public.api2BaseUrl}/auth/access-request`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(form.value)
    })

    if (!response.ok) {
      error.value = t('access_request.error_generic')
      return
    }

    success.value = true
  } catch {
    error.value = t('access_request.error_generic')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center px-4 py-8">
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
    <div class="w-full max-w-lg">
      <!-- Logo -->
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-primary-600">KPI Admin</h1>
        <p class="mt-2 text-header-900">{{ t('access_request.subtitle') }}</p>
      </div>

      <div class="bg-white rounded-lg shadow-lg p-8">
        <h2 class="text-xl font-semibold text-header-900 mb-6">
          {{ t('access_request.title') }}
        </h2>

        <!-- Success -->
        <div v-if="success" class="space-y-4">
          <div class="p-4 bg-success-50 border border-success-200 rounded-lg text-success-700 text-sm">
            <p class="font-semibold mb-1">{{ t('access_request.success_title') }}</p>
            <p>{{ t('access_request.success_message') }}</p>
          </div>
          <NuxtLink
            to="/login"
            class="block text-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium"
          >
            {{ t('access_request.back_to_login') }}
          </NuxtLink>
        </div>

        <!-- Form -->
        <form v-else class="space-y-4" @submit.prevent="handleSubmit">
          <p class="text-xs text-header-600">{{ t('access_request.required_fields') }}</p>

          <!-- Error -->
          <div
            v-if="error"
            class="p-3 bg-danger-50 border border-danger-200 rounded-lg text-danger-700 text-sm"
          >
            {{ error }}
          </div>

          <!-- Email * -->
          <div>
            <label class="block text-sm font-medium text-header-900 mb-1">
              {{ t('access_request.email') }} *
            </label>
            <UInput
              v-model="form.email"
              type="email"
              variant="outline"
              :placeholder="t('access_request.email_placeholder')"
              required
              autofocus
            />
          </div>

          <!-- Nom * + Prénom * -->
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-sm font-medium text-header-900 mb-1">
                {{ t('access_request.prenom') }} *
              </label>
              <UInput
                v-model="form.prenom"
                type="text"
                variant="outline"
                :placeholder="t('access_request.prenom_placeholder')"
                required
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-header-900 mb-1">
                {{ t('access_request.nom') }} *
              </label>
              <UInput
                v-model="form.nom"
                type="text"
                variant="outline"
                :placeholder="t('access_request.nom_placeholder')"
                required
              />
            </div>
          </div>

          <!-- Licence (optionnel) -->
          <div>
            <label class="block text-sm font-medium text-header-900 mb-1">
              {{ t('access_request.licence') }}
            </label>
            <UInput
              v-model="form.licence"
              type="text"
              variant="outline"
              :placeholder="t('access_request.licence_placeholder')"
            />
          </div>

          <!-- Club (optionnel) -->
          <div>
            <label class="block text-sm font-medium text-header-900 mb-1">
              {{ t('access_request.club') }}
            </label>
            <UInput
              v-model="form.club"
              type="text"
              variant="outline"
              :placeholder="t('access_request.club_placeholder')"
            />
          </div>

          <!-- Responsabilités * -->
          <div>
            <label class="block text-sm font-medium text-header-900 mb-1">
              {{ t('access_request.responsabilites') }} *
            </label>
            <UInput
              v-model="form.responsabilites"
              type="text"
              variant="outline"
              :placeholder="t('access_request.responsabilites_placeholder')"
              required
            />
          </div>

          <!-- Message * -->
          <div>
            <label class="block text-sm font-medium text-header-900 mb-1">
              {{ t('access_request.message') }} *
            </label>
            <UTextarea
              v-model="form.message"
              variant="outline"
              :placeholder="t('access_request.message_placeholder')"
              :rows="4"
              required
            />
          </div>

          <!-- Actions -->
          <div class="flex flex-col gap-2 pt-2">
            <UButton
              type="submit"
              color="primary"
              block
              :loading="loading"
              :disabled="!canSubmit"
            >
              {{ t('access_request.submit') }}
            </UButton>
            <NuxtLink
              to="/login"
              class="text-center text-sm text-header-600 hover:text-header-900"
            >
              {{ t('access_request.back_to_login') }}
            </NuxtLink>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>
