<script setup lang="ts">
import type { GroupSection } from '#kpi-layer/utils/results/types'

// Group selection: a GET form to /history (which redirects), applied on change with JavaScript (HIS-01).
const props = defineProps<{ sections: GroupSection[], group: string }>()
const { t } = useI18n()
const localePath = useLocalePath()

const selected = ref(props.group)
watch(() => props.group, value => (selected.value = value))

function apply(value: string) {
  if (value !== props.group) {
    navigateTo(localePath(`/history/${value}`))
  }
}
</script>

<template>
  <form method="get" :action="localePath('/history')" class="flex flex-wrap items-center gap-x-4 gap-y-2" data-testid="history-selector">
    <div class="flex w-full flex-wrap items-center gap-1.5 text-sm text-muted sm:w-auto">
      {{ $t('history.group') }}
      <SiteGroupSearchSelect v-model="selected" name="group" :sections="sections" :section-label="section => t(`results.section.${section}`)" @update:model-value="apply" />
    </div>
    <UButton type="submit" variant="outline">{{ $t('common.show') }}</UButton>
  </form>
</template>
