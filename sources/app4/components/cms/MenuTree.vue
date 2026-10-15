<script setup lang="ts">
import type { CmsMenuItem } from '~/types/cms'
import { canHaveMenuChildren, moveItem } from '~/utils/cms'

// One level of the editorial menu and, recursively, its sub-entries, up to three levels (PAGE_CMS.md § 5,
// ADM-CMS-08). Each level has its own badge, indentation and edge colour, so that the tree reads at a glance.
const items = defineModel<CmsMenuItem[]>({ required: true })
const props = defineProps<{
  depth: number
  prefix: string
  pageItems: { label: string, value: string }[]
  targetItems: { label: string, value: string }[]
  errors: Record<string, string>
}>()
const { t } = useI18n()

const LEVEL_STYLES = [
  'border-l-4 border-primary-500 bg-white dark:bg-header-900 shadow',
  'border-l-4 border-sky-400 bg-header-50 dark:bg-header-800',
  'border-l-4 border-header-300 bg-header-100/60 dark:bg-header-700/60',
]
const emptyItem = (): CmsMenuItem => ({ label_fr: '', label_en: '', target_kind: 'page', target_ref: '', children: [] })
const move = (index: number, delta: -1 | 1) => {
  items.value = moveItem(items.value, index, delta)
}
const remove = (index: number) => {
  items.value = items.value.filter((_, position) => position !== index)
}
const addChild = (item: CmsMenuItem) => {
  item.children = [...(item.children ?? []), emptyItem()]
}
</script>

<template>
  <ol class="space-y-2" :class="{ 'ml-6 mt-2': depth > 1 }" :data-depth="depth">
    <li v-for="(item, index) in items" :key="index" class="rounded-lg p-3" :class="LEVEL_STYLES[depth - 1]" :data-level="depth">
      <div class="mb-1">
        <UBadge size="sm" variant="subtle" :color="depth === 1 ? 'primary' : 'neutral'">{{ t('cms.menu.level', { level: depth }) }}</UBadge>
      </div>
      <CmsMenuItemRow
        v-model="items[index]!" :page-items="pageItems" :target-items="targetItems" :errors="errors" :prefix="`${prefix}.${index}`"
        :first="index === 0" :last="index === items.length - 1"
        @up="move(index, -1)" @down="move(index, 1)" @remove="remove(index)"
      />
      <template v-if="canHaveMenuChildren(props.depth)">
        <CmsMenuTree
          v-if="item.children?.length"
          v-model="item.children" :depth="depth + 1" :prefix="`${prefix}.${index}.children`"
          :page-items="pageItems" :target-items="targetItems" :errors="errors"
        />
        <UButton size="xs" variant="ghost" icon="heroicons:plus" class="ml-6 mt-1" @click="addChild(item)">
          {{ t('cms.menu.add_child', { level: depth + 1 }) }}
        </UButton>
      </template>
      <p v-if="errors[`${prefix}.${index}.children`]" class="mt-1 text-sm text-error">{{ t(`cms.errors.${errors[`${prefix}.${index}.children`]}`) }}</p>
    </li>
  </ol>
</template>
