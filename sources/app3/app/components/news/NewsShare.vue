<script setup lang="ts">
import { NEW_TAB_ATTRS } from '~/utils/links'
import { shareLinks } from '~/utils/news'

// Share an article (PAGE_NEWS.md § 2.2.6, NEWS-05): the system share sheet (Web Share API) when the browser has
// one, otherwise plain links to the networks — no third-party script. Without JavaScript, `<details>` shows the links.
const props = defineProps<{ url: string, title: string }>()
const links = computed(() => shareLinks(props.url, props.title))
const canCopy = ref(false)
const copied = ref(false)

onMounted(() => {
  canCopy.value = typeof navigator.clipboard?.writeText === 'function'
})

async function onToggle(event: MouseEvent) {
  if (typeof navigator.share !== 'function') {
    return
  }
  event.preventDefault()
  try {
    await navigator.share({ url: props.url, title: props.title })
  } catch {
    // Closed by the visitor: nothing to do.
  }
}

async function copy() {
  await navigator.clipboard.writeText(props.url)
  copied.value = true
}
</script>

<template>
  <details class="group relative" data-testid="news-share">
    <summary class="flex cursor-pointer list-none items-center gap-1 rounded border border-line px-3 py-1 hover:bg-kpi-blue-50" @click="onToggle">
      <UIcon name="i-heroicons-share" class="size-4" aria-hidden="true" />
      {{ $t('news.share') }}
    </summary>
    <ul class="absolute z-20 mt-1 min-w-48 rounded border border-line bg-surface py-1 shadow-lg">
      <li v-for="link in links" :key="link.id">
        <a :href="link.href" v-bind="link.id === 'email' ? {} : NEW_TAB_ATTRS" class="block px-3 py-1 hover:bg-kpi-blue-50" :data-share="link.id">
          {{ $t(`news.shareOn.${link.id}`) }}
          <span v-if="link.id !== 'email'" class="sr-only">{{ $t('a11y.newTab') }}</span>
        </a>
      </li>
      <li v-if="canCopy">
        <button type="button" class="block w-full px-3 py-1 text-left hover:bg-kpi-blue-50" data-testid="share-copy" @click="copy">
          {{ copied ? $t('news.copied') : $t('news.copyLink') }}
        </button>
      </li>
    </ul>
  </details>
</template>
