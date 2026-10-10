<script setup lang="ts">
import { webcalUrl } from '~/utils/page-links'

// Subscription to a competition calendar: webcal link, copyable https link and a tutorial for the main calendar
// applications (CAL-06, CAL-10).
const props = defineProps<{ url: string }>()
const COPIED_DURATION_MS = 3000
const APPS = ['google', 'outlook', 'apple'] as const
const STEPS = [1, 2, 3, 4] as const
const { t, te } = useI18n()
const copied = ref(false)

async function copyLink() {
  try {
    await navigator.clipboard.writeText(props.url)
    copied.value = true
    setTimeout(() => { copied.value = false }, COPIED_DURATION_MS)
  }
  catch {
    // Clipboard refused: the link stays selectable in the field above.
  }
}
</script>

<template>
  <div class="mb-4 space-y-3 rounded border border-line p-4" data-testid="calendar-subscription">
    <div class="flex flex-wrap items-center gap-3">
      <a :href="webcalUrl(url)" class="inline-flex items-center gap-1 rounded bg-kpi-blue-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-kpi-blue-700" data-testid="ics-subscribe">
        <UIcon name="i-heroicons-calendar-days" class="size-5" aria-hidden="true" />{{ t('calendar.subscribe') }}
      </a>
      <a :href="url" class="text-sm underline" data-testid="ics-download">{{ t('calendar.download') }}</a>
    </div>
    <div class="flex flex-wrap items-center gap-2">
      <label class="sr-only" for="ics-link">{{ t('calendar.linkLabel') }}</label>
      <input id="ics-link" :value="url" readonly class="min-w-0 flex-1 rounded border border-line px-2 py-1 text-sm" data-testid="ics-link" @focus="($event.target as HTMLInputElement).select()">
      <button type="button" class="rounded border border-kpi-blue-600 px-3 py-1 text-sm font-semibold text-kpi-blue-600 hover:bg-kpi-blue-50" data-testid="ics-copy" @click="copyLink">
        {{ copied ? t('calendar.copied') : t('calendar.copy') }}
      </button>
    </div>
    <p class="text-xs text-ink/70">{{ t('calendar.icsHelp') }}</p>
    <details data-testid="ics-tutorial">
      <summary class="cursor-pointer text-sm font-semibold text-kpi-blue-600">{{ t('calendar.tutorial.title') }}</summary>
      <div class="mt-2 grid gap-4 md:grid-cols-3">
        <section v-for="app in APPS" :key="app" :data-calendar-app="app">
          <h3 class="text-lg">{{ t(`calendar.tutorial.${app}.name`) }}</h3>
          <ol class="list-decimal space-y-1 pl-5 text-sm">
            <template v-for="step in STEPS" :key="step">
              <li v-if="te(`calendar.tutorial.${app}.step${step}`)">{{ t(`calendar.tutorial.${app}.step${step}`) }}</li>
            </template>
          </ol>
        </section>
      </div>
    </details>
  </div>
</template>
