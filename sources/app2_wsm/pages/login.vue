<template>
  <div class="container mx-auto px-4 py-8">
    <div class="max-w-md mx-auto">
      <div class="bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-6 text-center">
          {{ t('Login.Authentication') }}
        </h1>

        <form @submit.prevent="handleLogin" class="space-y-4">
          <div>
            <label for="login" class="block text-sm font-medium text-gray-700 mb-1">
              {{ t('Login.Login') }}
            </label>
            <input
              id="login"
              v-model="loginForm.username"
              type="text"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
              :placeholder="t('Login.LoginHelp')"
            />
          </div>

          <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
              {{ t('Login.Password') }}
            </label>
            <input
              id="password"
              v-model="loginForm.password"
              type="password"
              required
              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
            />
          </div>

          <div v-if="error" class="bg-red-50 border border-red-200 rounded p-3 text-red-800 text-sm">
            {{ error }}
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="w-full py-3 bg-green-600 text-white rounded-lg font-semibold hover:bg-green-700 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span v-if="loading">
              <svg class="animate-spin h-5 w-5 inline-block mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
              </svg>
              Connexion...
            </span>
            <span v-else>{{ t('Login.Submit') }}</span>
          </button>
        </form>

        <div class="mt-6 text-center">
          <NuxtLink to="/" class="text-sm text-gray-600 hover:text-gray-900">
            ← {{ t('nav.Home') }}
          </NuxtLink>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const { t } = useI18n()
const { login, isAuthenticated } = useAuth()
const router = useRouter()

const loginForm = reactive({
  username: '',
  password: '',
})

const loading = ref(false)
const error = ref(null)

// Redirect if already authenticated
onMounted(() => {
  if (isAuthenticated.value) {
    router.push('/')
  }
})

async function handleLogin() {
  if (!loginForm.username || !loginForm.password) {
    error.value = t('Login.EmptyMsg')
    return
  }

  loading.value = true
  error.value = null

  try {
    const result = await login(loginForm.username, loginForm.password)

    if (result.success) {
      // Redirect to home
      router.push('/')
    } else {
      if (result.error.includes('401') || result.error.includes('Unauthorized')) {
        error.value = t('Login.UnauthorizedMsg')
      } else {
        error.value = t('Login.ErrorMsg')
      }
    }
  } catch (err) {
    console.error('Login error:', err)
    error.value = t('Login.ErrorMsg')
  } finally {
    loading.value = false
  }
}
</script>
