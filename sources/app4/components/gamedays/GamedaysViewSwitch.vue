<script setup lang="ts">
import type { GamedaysView } from '~/utils/gamedaysView'

// Liste / Schéma switch of the Phases page (PAGE_JOURNEES_PHASES.md § 5.3, GDY-SCH-01).
defineProps<{
  schemaAvailable: boolean
}>()

const view = defineModel<GamedaysView>({ required: true })
const { t } = useI18n()

const baseClass = 'flex items-center gap-1.5 px-3 py-2 text-sm font-medium border first:rounded-l-lg last:rounded-r-lg -ml-px first:ml-0 disabled:opacity-50 disabled:cursor-not-allowed'
const activeClass = 'relative z-10 text-primary-700 bg-primary-50 border-primary-400 dark:text-primary-200 dark:bg-primary-950'
const idleClass = 'text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border-header-300 dark:border-header-700 hover:bg-header-50 dark:hover:bg-header-800'
</script>

<template>
  <div class="inline-flex" role="group" data-testid="gamedays-view-switch">
    <button
      :class="[baseClass, view === 'list' ? activeClass : idleClass]"
      :aria-pressed="view === 'list'"
      :title="t('gamedays.view_list')"
      data-testid="gamedays-view-list"
      @click="view = 'list'"
    >
      <UIcon name="heroicons:list-bullet" class="w-5 h-5" />
      <span class="hidden sm:inline">{{ t('gamedays.view_list') }}</span>
    </button>
    <button
      :class="[baseClass, view === 'schema' ? activeClass : idleClass]"
      :aria-pressed="view === 'schema'"
      :disabled="!schemaAvailable && view !== 'schema'"
      :title="schemaAvailable ? t('gamedays.view_schema_title') : t('gamedays.view_schema_needs_competition')"
      data-testid="gamedays-view-schema"
      @click="view = 'schema'"
    >
      <UIcon name="heroicons:rectangle-group" class="w-5 h-5" />
      <span class="hidden sm:inline">{{ t('gamedays.view_schema') }}</span>
    </button>
  </div>
</template>
