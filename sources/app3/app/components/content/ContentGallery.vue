<script setup lang="ts">
// Image viewer of an article (PAGE_NEWS.md § 2.2.4, NEWS-07): native modal dialog (focus kept inside, rest of the
// page inert), arrows to browse, Escape to close; focus returns to the image that opened it.
const props = defineProps<{ images: { src: string, alt: string }[] }>()
const index = defineModel<number | null>({ required: true })

const dialog = useTemplateRef<HTMLDialogElement>('dialog')
const current = computed(() => (index.value === null ? null : props.images[index.value] ?? null))
let opener: HTMLElement | null = null

watch(index, async (value, previous) => {
  await nextTick()
  if (value !== null && previous === null) {
    opener = document.activeElement instanceof HTMLElement ? document.activeElement : null
    if (dialog.value && !dialog.value.open) {
      dialog.value.showModal()
    }
  } else if (value === null && dialog.value?.open) {
    dialog.value.close()
  }
})

function close() {
  index.value = null
  opener?.focus()
}

function move(step: number) {
  if (index.value !== null && props.images.length > 0) {
    index.value = (index.value + step + props.images.length) % props.images.length
  }
}

function onKeydown(event: KeyboardEvent) {
  const steps: Record<string, number> = { ArrowRight: 1, ArrowLeft: -1 }
  if (event.key === 'Escape') {
    event.preventDefault()
    close()
  } else if (event.key in steps) {
    event.preventDefault()
    move(steps[event.key]!)
  }
}
</script>

<template>
  <dialog
    ref="dialog"
    :aria-label="$t('news.gallery')"
    class="m-auto max-h-none max-w-none bg-transparent p-0 backdrop:bg-black/85"
    data-testid="gallery"
    @keydown="onKeydown"
    @cancel.prevent="close"
  >
    <div v-if="current" class="flex h-dvh w-dvw flex-col items-center justify-center gap-3 p-4 text-white">
      <img :src="current.src" :alt="current.alt" class="max-h-[80dvh] max-w-full object-contain" data-testid="gallery-image">
      <p class="text-sm" aria-live="polite">{{ $t('news.galleryPosition', { index: (index ?? 0) + 1, count: images.length }) }}</p>
      <div class="flex gap-2">
        <UButton v-if="images.length > 1" color="neutral" variant="subtle" icon="i-heroicons-arrow-left" :aria-label="$t('news.galleryPrevious')" data-testid="gallery-previous" @click="move(-1)" />
        <UButton color="neutral" variant="subtle" icon="i-heroicons-x-mark" :aria-label="$t('news.galleryClose')" autofocus data-testid="gallery-close" @click="close" />
        <UButton v-if="images.length > 1" color="neutral" variant="subtle" icon="i-heroicons-arrow-right" :aria-label="$t('news.galleryNext')" data-testid="gallery-next" @click="move(1)" />
      </div>
    </div>
  </dialog>
</template>
