<script setup lang="ts">
/**
 * Recovery codes shown once, right after an activation or a regeneration (AUTH_HARDENING.md § 5, AUTH-2FA-06).
 * The user must confirm they kept them before going on.
 */
import { recoveryCodesText } from '~/utils/secondFactor'

const props = defineProps<{
  codes: string[]
  account: string
}>()

const emit = defineEmits<{ acknowledged: [] }>()

const { t } = useI18n()
const kept = ref(false)
const copied = ref(false)

const text = computed(() => recoveryCodesText(props.codes, t('second_factor.recovery.file_heading', { account: props.account })))

async function copy() {
  try {
    await navigator.clipboard.writeText(text.value)
    copied.value = true
  } catch {
    copied.value = false
  }
}

function download() {
  const url = URL.createObjectURL(new Blob([text.value], { type: 'text/plain' }))
  const link = document.createElement('a')
  link.href = url
  link.download = `kpi-codes-recuperation-${props.account}.txt`
  link.click()
  URL.revokeObjectURL(url)
}
</script>

<template>
  <div class="space-y-4">
    <h3 class="text-lg font-semibold text-header-900 dark:text-header-50">{{ t('second_factor.recovery.title') }}</h3>
    <p class="text-sm text-header-700 dark:text-header-200">{{ t('second_factor.recovery.intro') }}</p>

    <ul class="grid grid-cols-2 gap-2 p-4 bg-header-50 dark:bg-header-950 rounded font-mono text-base text-header-900 dark:text-header-50" data-testid="recovery-codes">
      <li v-for="code in codes" :key="code">{{ code }}</li>
    </ul>

    <div class="flex flex-wrap gap-2">
      <UButton color="neutral" variant="outline" size="sm" icon="i-heroicons-clipboard-document" @click="copy">
        {{ copied ? t('second_factor.recovery.copied') : t('second_factor.recovery.copy') }}
      </UButton>
      <UButton color="neutral" variant="outline" size="sm" icon="i-heroicons-arrow-down-tray" @click="download">
        {{ t('second_factor.recovery.download') }}
      </UButton>
    </div>

    <UCheckbox v-model="kept" :label="t('second_factor.recovery.kept')" />

    <UButton color="primary" block :disabled="!kept" @click="emit('acknowledged')">
      {{ t('second_factor.recovery.continue') }}
    </UButton>
  </div>
</template>
