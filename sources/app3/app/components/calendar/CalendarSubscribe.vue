<script setup lang="ts">
import { webcalUrl } from '~/utils/page-links'

// « In your calendar » block (CMP-23): subscription (webcal link), copyable https link, .ics file and a folded
// tutorial for the main calendar applications (CAL-06, CAL-10).
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
  <section class="rounded-lg border border-line p-4.5" aria-labelledby="calendar-subscription-title" data-testid="calendar-subscription">
    <h2 id="calendar-subscription-title" class="mb-3 text-[1.75rem] leading-tight text-kpi-blue-700">{{ t('calendar.agenda') }}</h2>
    <p class="mb-3.5 text-sm text-muted">{{ t('calendar.agendaIntro') }}</p>
    <a :href="webcalUrl(url)" class="flex w-full items-center justify-center gap-2 rounded-md bg-navy px-3.5 py-2.5 font-semibold text-white hover:bg-kpi-blue-700 dark:hover:bg-kpi-blue-900" data-testid="ics-subscribe">
      <UIcon name="i-heroicons-calendar-days" class="size-4.5" aria-hidden="true" />{{ t('calendar.subscribe') }}
    </a>
    <button type="button" class="mt-2 flex w-full items-center justify-center rounded-md border border-line px-3.5 py-2.5 font-semibold hover:bg-wash" data-testid="ics-copy" @click="copyLink">
      {{ copied ? t('calendar.copied') : t('calendar.copy') }}
    </button>
    <label class="sr-only" for="ics-link">{{ t('calendar.linkLabel') }}</label>
    <input id="ics-link" :value="url" readonly class="mt-2 w-full rounded-md border border-line bg-surface px-2 py-1 text-xs text-muted" data-testid="ics-link" @focus="($event.target as HTMLInputElement).select()">
    <a :href="url" class="mt-3 inline-block text-sm underline" data-testid="ics-download">{{ t('calendar.download') }}</a>
    <details class="mt-3.5 border-t border-line pt-2.5 text-sm" data-testid="ics-tutorial">
      <summary class="cursor-pointer font-semibold">{{ t('calendar.tutorial.title') }}</summary>
      <p class="mt-2 text-xs text-muted">{{ t('calendar.icsHelp') }}</p>
      <div class="mt-2 grid gap-4">
        <section v-for="app in APPS" :key="app" :data-calendar-app="app">
          <h3 class="font-sans text-sm font-bold">{{ t(`calendar.tutorial.${app}.name`) }}</h3>
          <ol class="list-decimal space-y-1 pl-5 text-muted">
            <template v-for="step in STEPS" :key="step">
              <li v-if="te(`calendar.tutorial.${app}.step${step}`)">{{ t(`calendar.tutorial.${app}.step${step}`) }}</li>
            </template>
          </ol>
        </section>
      </div>
    </details>
  </section>
</template>
