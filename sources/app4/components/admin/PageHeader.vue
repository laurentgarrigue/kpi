<script setup lang="ts">
const { t } = useI18n()
const router = useRouter()
const workContext = useWorkContextStore()
const slots = useSlots()

interface Props {
  title: string
  showFilters?: boolean
  showAllOption?: boolean
  competitionFilteredCodes?: string[] | null
  hasNotices?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  showFilters: true,
  showAllOption: false,
  competitionFilteredCodes: null,
  hasNotices: false,
})

const emit = defineEmits<{
  'event-group-change': []
  'competition-change': []
}>()

// Notices dismissal state
const noticesDismissed = ref(false)

// Reset dismissed state when slot content might change (competition change)
watch(() => workContext.pageCompetitionCode, () => {
  noticesDismissed.value = false
})
watch(() => workContext.pageCompetitionCodeAll, () => {
  noticesDismissed.value = false
})
</script>

<template>
  <div class="mb-2">
    <!-- Row 1: Title + Work Context Summary -->
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
      <div class="flex items-center gap-3">
        <button
          :title="t('common.back')"
          class="inline-flex items-center p-1.5 text-header-900 dark:text-header-200 hover:text-header-900 hover:bg-header-200 dark:hover:bg-header-800 rounded-lg transition-colors"
          @click="router.back()"
        >
          <UIcon name="heroicons:arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">{{ title }}</h1>
      </div>
      <AdminWorkContextSummary compact />
    </div>

    <!-- Row 2: Filters -->
    <div v-if="showFilters" class="flex flex-wrap gap-3 items-end">
      <!-- Event / Group filter -->
      <div class="min-w-48 max-w-96">
        <label class="block text-xs font-medium text-header-900 dark:text-header-200 mb-1">{{ t('eventGroupSelect.label') }}</label>
        <AdminEventGroupSelect @change="emit('event-group-change')" />
      </div>

      <!-- Competition filter -->
      <div class="min-w-48 max-w-96">
        <label class="block text-xs font-medium text-header-900 dark:text-header-200 mb-1">{{ t(workContext.competitionFilterLabelKey) }}</label>
        <AdminCompetitionSingleSelect
          :show-all-option="showAllOption"
          :filtered-codes="competitionFilteredCodes"
          @change="emit('competition-change')"
        />
      </div>

      <!-- Extra filters slot -->
      <slot name="filters" />

      <!-- Badges slot (inside filters row) -->
      <slot name="badges" />
    </div>

    <!-- Badges slot (standalone when filters hidden) -->
    <div v-else-if="slots.badges" class="flex flex-wrap gap-3 items-center">
      <slot name="badges" />
    </div>

    <!-- Notices (dismissable) -->
    <div v-if="props.hasNotices && !noticesDismissed" class="mt-2 relative">
      <slot name="notices" />
      <button
        class="absolute top-1 right-1 p-1 text-header-600 dark:text-header-600 hover:text-header-900 rounded transition-colors"
        @click="noticesDismissed = true"
      >
        <UIcon name="heroicons:x-mark" class="w-4 h-4" />
      </button>
    </div>
  </div>
</template>
