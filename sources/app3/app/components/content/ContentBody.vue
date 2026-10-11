<script setup lang="ts">
import { galleryImages } from '~/utils/content'

// Body of an article or a page, prepared by `prepareBody` (utils/content.ts): typographic styles of the site, videos
// loaded on click, images opening the viewer (PAGE_NEWS.md § 2.2.3-4). Without JavaScript, the video and image
// links lead to the video and the image.
const props = defineProps<{ html: string }>()
const images = computed(() => galleryImages(props.html))
const opened = ref<number | null>(null)

function loadVideo(link: HTMLAnchorElement) {
  const iframe = document.createElement('iframe')
  iframe.src = `${link.dataset.videoSrc}?autoplay=1`
  iframe.title = link.dataset.videoTitle ?? ''
  iframe.allow = 'autoplay; fullscreen; picture-in-picture'
  iframe.allowFullscreen = true
  iframe.className = 'kpi-video-player'
  link.replaceWith(iframe)
  iframe.focus()
}

function onClick(event: MouseEvent) {
  const target = event.target instanceof Element ? event.target : null
  const video = target?.closest<HTMLAnchorElement>('a.kpi-video')
  if (video?.dataset.videoSrc) {
    event.preventDefault()
    loadVideo(video)
    return
  }
  const zoom = target?.closest<HTMLAnchorElement>('a.kpi-zoom')
  if (zoom?.dataset.galleryIndex !== undefined) {
    event.preventDefault()
    opened.value = Number(zoom.dataset.galleryIndex)
  }
}
</script>

<template>
  <div>
    <!-- eslint-disable-next-line vue/no-v-html -- HTML filtered by the api2 whitelist (FEATURE_CMS.md § 3.2, CMS-02) -->
    <div class="kpi-prose" data-testid="content-body" @click="onClick" v-html="html" />
    <ContentGallery v-if="images.length > 0" v-model="opened" :images="images" />
  </div>
</template>
