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
const { selectEvent } = useContextSelection()
const { t } = useI18n()

onMounted(async () => {
  const eventId = parseInt(route.params.id)
  if (isNaN(eventId)) {
    return navigateTo('/', { replace: true })
  }

  await selectEvent(eventId)

  // Always redirect to home, which will now use the new context
  await navigateTo('/', { replace: true })
})
</script>
