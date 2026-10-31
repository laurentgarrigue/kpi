<script setup lang="ts">
import { ageCategoryLabel, hasUnverifiableAgeCategory } from '~/utils/ageCategory'

/**
 * Age category of a player (DOC/specs/CATEGORIE_AGE.md): « ? » with the tooltip « Date de naissance inconnue » when
 * api2 sends none. In a competition with age bounds, api2 adds a non-blocking warning (never an eligibility error):
 * the « ? » is then highlighted in amber, never red.
 */
interface Props {
  code: string | null | undefined
  sexe?: string | null
  warnings?: string[]
}

const props = withDefaults(defineProps<Props>(), {
  sexe: null,
  warnings: () => []
})

const { t } = useI18n()

const unverifiable = computed(() => hasUnverifiableAgeCategory(props.warnings))
const title = computed(() => {
  if (unverifiable.value) return t('presence.error_Naissance_inconnue')
  return props.code ? undefined : t('common.birth_date_unknown')
})
</script>

<template>
  <span
    :class="[
      { 'cursor-help': title },
      unverifiable ? 'rounded-full px-2 font-bold bg-amber-500 text-white dark:text-header-950' : ''
    ]"
    :title="title"
  >{{ ageCategoryLabel(code, sexe) }}</span>
</template>
