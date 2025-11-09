<template>
  <div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 via-blue-900 to-gray-900 text-white">
    <div class="max-w-4xl mx-auto px-4 py-12 text-center">
      <h1 class="text-6xl font-bold mb-6 animate__animated animate__fadeInDown">
        KPI Live
      </h1>
      <p class="text-2xl text-gray-300 mb-12 animate__animated animate__fadeIn animate__delay-1s">
        {{ t('Home.Subtitle') }}
      </p>

      <div class="bg-gray-800 bg-opacity-50 rounded-xl p-8 backdrop-blur-sm animate__animated animate__fadeInUp animate__delay-2s">
        <h2 class="text-3xl font-semibold mb-6">{{ t('Home.HowToUse') }}</h2>

        <div class="text-left space-y-4 text-lg">
          <p class="flex items-start">
            <span class="text-blue-400 mr-2">•</span>
            <span>{{ t('Home.Usage1') }}</span>
          </p>
          <p class="flex items-start">
            <span class="text-blue-400 mr-2">•</span>
            <span>{{ t('Home.Usage2') }}</span>
          </p>
          <p class="flex items-start">
            <span class="text-blue-400 mr-2">•</span>
            <span>{{ t('Home.Usage3') }}</span>
          </p>
        </div>

        <div class="mt-8 p-6 bg-gray-700 bg-opacity-50 rounded-lg">
          <h3 class="text-xl font-semibold mb-3">{{ t('Home.ExampleUrl') }}</h3>
          <code class="text-sm text-green-400 break-all">
            /live/:event/:pitch/:options
          </code>
          <p class="text-sm text-gray-400 mt-2">{{ t('Home.Example') }}</p>
          <code class="text-xs text-green-400 break-all block mt-2">
            /live/123/1/score/inter/full
          </code>
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
          <div class="p-4 bg-gray-700 bg-opacity-30 rounded-lg">
            <h4 class="font-semibold mb-2 text-blue-400">{{ t('Home.DisplayModes') }}</h4>
            <ul class="space-y-1 text-gray-300">
              <li>• main</li>
              <li>• match</li>
              <li>• score</li>
            </ul>
          </div>
          <div class="p-4 bg-gray-700 bg-opacity-30 rounded-lg">
            <h4 class="font-semibold mb-2 text-blue-400">{{ t('Home.Zones') }}</h4>
            <ul class="space-y-1 text-gray-300">
              <li>• club ({{ t('Home.ClubMode') }})</li>
              <li>• inter ({{ t('Home.InterMode') }})</li>
            </ul>
          </div>
          <div class="p-4 bg-gray-700 bg-opacity-30 rounded-lg">
            <h4 class="font-semibold mb-2 text-blue-400">{{ t('Home.Modes') }}</h4>
            <ul class="space-y-1 text-gray-300">
              <li>• full ({{ t('Home.FullMode') }})</li>
              <li>• only ({{ t('Home.OnlyMode') }})</li>
              <li>• events ({{ t('Home.EventsMode') }})</li>
              <li>• static ({{ t('Home.StaticMode') }})</li>
            </ul>
          </div>
          <div class="p-4 bg-gray-700 bg-opacity-30 rounded-lg">
            <h4 class="font-semibold mb-2 text-blue-400">{{ t('Home.Styles') }}</h4>
            <ul class="space-y-1 text-gray-300">
              <li>• default</li>
              <li>• saintomer2022</li>
              <li>• welland2018</li>
            </ul>
          </div>
        </div>
      </div>

      <div class="mt-12 text-gray-400 text-sm">
        <p>{{ t('Home.Version') }}: 2.0.0</p>
        <p class="mt-2">
          <a href="https://kayak-polo.info" target="_blank" class="text-blue-400 hover:text-blue-300">
            kayak-polo.info
          </a>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
const { t } = useI18n()

definePageMeta({
  layout: 'default'
})

useSeoMeta({
  title: 'KPI Live - Home',
  description: 'KPI Live - Real-time display for kayak polo competitions'
})
</script>
