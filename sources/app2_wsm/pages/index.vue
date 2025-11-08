<template>
  <div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
      <h1 class="text-4xl font-bold text-gray-900 mb-6">{{ t('nav.Home') }}</h1>

      <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-2xl font-semibold mb-4">KPI WSM - WebSocket Manager</h2>
        <p class="text-gray-600 mb-4">
          Gestionnaire de connexions WebSocket pour les données de matchs en temps réel.
        </p>

        <div v-if="!isAuthenticated" class="bg-blue-50 border border-blue-200 rounded p-4 mb-4">
          <p class="text-blue-800">
            Veuillez vous <NuxtLink to="/login" class="font-semibold underline">connecter</NuxtLink> pour
            accéder aux fonctionnalités.
          </p>
        </div>

        <div v-else class="space-y-4">
          <div class="bg-green-50 border border-green-200 rounded p-4">
            <p class="text-green-800">
              <span class="font-semibold">{{ t('Login.Welcome') }},</span>
              {{ user?.name }}
            </p>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <NuxtLink
              to="/manager"
              class="bg-blue-600 text-white rounded-lg p-6 hover:bg-blue-700 transition text-center"
            >
              <h3 class="text-xl font-semibold mb-2">{{ t('nav.Manager') }}</h3>
              <p class="text-sm opacity-90">Gérer les connexions WebSocket</p>
            </NuxtLink>

            <NuxtLink
              to="/stats"
              class="bg-green-600 text-white rounded-lg p-6 hover:bg-green-700 transition text-center"
            >
              <h3 class="text-xl font-semibold mb-2">{{ t('nav.Stats') }}</h3>
              <p class="text-sm opacity-90">Collecter les statistiques</p>
            </NuxtLink>

            <NuxtLink
              to="/faker"
              class="bg-purple-600 text-white rounded-lg p-6 hover:bg-purple-700 transition text-center"
            >
              <h3 class="text-xl font-semibold mb-2">{{ t('nav.Faker') }}</h3>
              <p class="text-sm opacity-90">Générer des données de test</p>
            </NuxtLink>
          </div>
        </div>
      </div>

      <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-semibold mb-3">Fonctionnalités</h3>
        <ul class="space-y-2 text-gray-600">
          <li class="flex items-start">
            <svg class="h-6 w-6 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>Gestion de multiples connexions WebSocket simultanées</span>
          </li>
          <li class="flex items-start">
            <svg class="h-6 w-6 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>Support des protocoles WebSocket brut et STOMP</span>
          </li>
          <li class="flex items-start">
            <svg class="h-6 w-6 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>Collecte de statistiques de matchs en temps réel</span>
          </li>
          <li class="flex items-start">
            <svg class="h-6 w-6 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>Générateur de données de test pour les scénarios de match</span>
          </li>
          <li class="flex items-start">
            <svg class="h-6 w-6 text-green-500 mr-2 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <span>Synchronisation optionnelle avec la base de données</span>
          </li>
        </ul>
      </div>
    </div>
  </div>
</template>

<script setup>
const { t } = useI18n()
const { isAuthenticated, user } = useAuth()
</script>
