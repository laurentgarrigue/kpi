<script setup lang="ts">
/**
 * Warning icon shown next to a player who is not compliant with the competition's
 * eligibility rules ("joueur en règle"). Tooltip lists the failed criteria.
 */
interface Props {
  errors?: string[]
  enforcement?: 'block' | 'warn' | null
  minPagaie?: string | null
  // Errors reported without blocking for the season (2027 national rule: paddle colour)
  advisoryErrors?: string[]
  // Player holds a playing status (-, C): the rules matter, highlight the icon
  playing?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  errors: () => [],
  enforcement: null,
  minPagaie: null,
  advisoryErrors: () => [],
  playing: true
})

const { t } = useI18n()
const { errorLabels } = useEligibilityMessages()

const title = computed(() =>
  [t('presence.eligibility_not_compliant'), ...errorLabels(props.errors, props.minPagaie)].join('\n')
)

const colorClass = computed(() => {
  if (!props.playing) return 'text-header-600 dark:text-header-300'
  const blocking = props.errors.some(code => !props.advisoryErrors.includes(code))
  return props.enforcement === 'block' && blocking
    ? 'text-danger-600 dark:text-danger-300'
    : 'text-amber-700 dark:text-amber-300'
})
</script>

<template>
  <UIcon
    v-if="enforcement && errors.length"
    name="i-heroicons-exclamation-triangle-solid"
    class="w-6 h-6 inline-block align-middle ml-1.5 shrink-0 cursor-help"
    :class="colorClass"
    :title="title"
  />
</template>
