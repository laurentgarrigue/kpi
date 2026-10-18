<script setup lang="ts">
// Search block, between the navigation and the current page, opened by the header magnifier
// (FEATURE_SEARCH.md § 2, SRC-06). Closed by its own button, the magnifier again, or a navigation.
const { open, close } = useSearchBlock()
const route = useRoute()
const block = useTemplateRef<HTMLElement>('block')

watch(() => route.fullPath, close)
watch(open, async (isOpen) => {
  if (isOpen) {
    await nextTick()
    block.value?.querySelector('input')?.focus()
  }
})

function closeAndRefocus() {
  close()
  document.getElementById(SEARCH_BLOCK_TOGGLE_ID)?.focus()
}
</script>

<template>
  <div v-if="open" :id="SEARCH_BLOCK_ID" ref="block" class="border-b border-line bg-surface" data-testid="search-block">
    <div class="mx-auto flex max-w-7xl items-start gap-2 px-4 py-3 lg:px-8">
      <div class="min-w-0 flex-1 md:max-w-xl">
        <SiteSearch />
      </div>
      <button
        type="button"
        class="rounded p-2 text-kpi-blue-600 hover:bg-kpi-blue-50"
        :aria-label="$t('header.closeSearch')"
        :title="$t('header.closeSearch')"
        data-testid="search-block-close"
        @click="closeAndRefocus"
      >
        <UIcon name="i-heroicons-x-mark" class="block size-6" aria-hidden="true" />
      </button>
    </div>
  </div>
</template>
