<template>
  <nav class="bg-gray-900 text-white shadow-lg">
    <div class="container mx-auto px-4">
      <div class="flex items-center justify-between h-16">
        <!-- Logo/Brand -->
        <div class="flex-shrink-0">
          <NuxtLink to="/" class="text-xl font-bold text-white hover:text-green-400 transition">
            KPI WSM
          </NuxtLink>
        </div>

        <!-- Desktop Navigation -->
        <div class="hidden md:flex items-center space-x-4">
          <NuxtLink
            to="/"
            class="px-3 py-2 rounded hover:bg-gray-700 transition"
            :class="{ 'bg-gray-700': $route.path === '/' }"
          >
            {{ t('nav.Home') }}
          </NuxtLink>

          <NuxtLink
            v-if="isAuthenticated"
            to="/manager"
            class="px-3 py-2 rounded hover:bg-gray-700 transition"
            :class="{ 'bg-gray-700': $route.path === '/manager' }"
          >
            {{ t('nav.Manager') }}
          </NuxtLink>

          <NuxtLink
            v-if="isAuthenticated"
            to="/stats"
            class="px-3 py-2 rounded hover:bg-gray-700 transition"
            :class="{ 'bg-gray-700': $route.path === '/stats' }"
          >
            {{ t('nav.Stats') }}
          </NuxtLink>

          <NuxtLink
            v-if="isAuthenticated"
            to="/faker"
            class="px-3 py-2 rounded hover:bg-gray-700 transition"
            :class="{ 'bg-gray-700': $route.path === '/faker' }"
          >
            {{ t('nav.Faker') }}
          </NuxtLink>

          <AppLocaleSwitcher />

          <button
            v-if="isAuthenticated"
            @click="handleLogout"
            class="px-3 py-2 rounded bg-red-600 hover:bg-red-700 transition"
          >
            {{ t('nav.Logout') }}
          </button>

          <NuxtLink v-else to="/login" class="px-3 py-2 rounded bg-green-600 hover:bg-green-700 transition">
            {{ t('nav.Login') }}
          </NuxtLink>
        </div>

        <!-- Mobile Menu Button -->
        <div class="md:hidden">
          <button @click="mobileMenuOpen = !mobileMenuOpen" class="p-2 rounded hover:bg-gray-700 transition">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path
                v-if="!mobileMenuOpen"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M4 6h16M4 12h16M4 18h16"
              />
              <path
                v-else
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M6 18L18 6M6 6l12 12"
              />
            </svg>
          </button>
        </div>
      </div>

      <!-- Mobile Menu -->
      <div v-if="mobileMenuOpen" class="md:hidden py-4 border-t border-gray-700">
        <div class="flex flex-col space-y-2">
          <NuxtLink
            to="/"
            class="px-3 py-2 rounded hover:bg-gray-700 transition"
            @click="mobileMenuOpen = false"
          >
            {{ t('nav.Home') }}
          </NuxtLink>

          <NuxtLink
            v-if="isAuthenticated"
            to="/manager"
            class="px-3 py-2 rounded hover:bg-gray-700 transition"
            @click="mobileMenuOpen = false"
          >
            {{ t('nav.Manager') }}
          </NuxtLink>

          <NuxtLink
            v-if="isAuthenticated"
            to="/stats"
            class="px-3 py-2 rounded hover:bg-gray-700 transition"
            @click="mobileMenuOpen = false"
          >
            {{ t('nav.Stats') }}
          </NuxtLink>

          <NuxtLink
            v-if="isAuthenticated"
            to="/faker"
            class="px-3 py-2 rounded hover:bg-gray-700 transition"
            @click="mobileMenuOpen = false"
          >
            {{ t('nav.Faker') }}
          </NuxtLink>

          <div class="px-3 py-2">
            <AppLocaleSwitcher />
          </div>

          <button
            v-if="isAuthenticated"
            @click="handleLogout"
            class="mx-3 px-3 py-2 rounded bg-red-600 hover:bg-red-700 transition text-left"
          >
            {{ t('nav.Logout') }}
          </button>

          <NuxtLink
            v-else
            to="/login"
            class="mx-3 px-3 py-2 rounded bg-green-600 hover:bg-green-700 transition text-center"
            @click="mobileMenuOpen = false"
          >
            {{ t('nav.Login') }}
          </NuxtLink>
        </div>
      </div>
    </div>
  </nav>
</template>

<script setup>
const { t } = useI18n()
const { isAuthenticated, logout } = useAuth()
const mobileMenuOpen = ref(false)

async function handleLogout() {
  mobileMenuOpen.value = false
  await logout()
}
</script>
