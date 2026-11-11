<template>
  <ClientOnly>
    <div class="p-8 text-center text-gray-500">
      <p>{{ t('Event.Selecting') }}</p>
    </div>
  </ClientOnly>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { navigateTo } from '#app'
import { useContextSelection } from '~/composables/useContextSelection'

// Disable prerendering and SSR for this route since it only redirects
definePageMeta({
  prerender: false,
  ssr: false
})

const route = useRoute()
const { selectGroup } = useContextSelection()
const { t } = useI18n()

onMounted(async () => {
  const season = route.params.season
  const groupCode = route.params.id

  if (!season || !groupCode) {
    return navigateTo('/', { replace: true })
  }

  await selectGroup(season, groupCode)

  // Always redirect to home, which will now use the new context
  await navigateTo('/', { replace: true })
})
</script>
