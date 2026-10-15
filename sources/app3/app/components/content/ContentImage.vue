<script setup lang="ts">
import { mediaSrcset, mediaUrl, mediaVariant, type MediaView } from '~/utils/news'

// Image of the media library: WebP variants served by the legacy host (FEATURE_CMS.md § 5.3, PAGE_NEWS.md § 2.2.3).
const props = withDefaults(defineProps<{ media: MediaView, width: number, sizes?: string, lazy?: boolean }>(), { sizes: undefined, lazy: true })
const { legacyBaseUrl } = useRuntimeConfig().public
const src = computed(() => mediaUrl(mediaVariant(props.media, props.width), legacyBaseUrl))
const srcset = computed(() => mediaSrcset(props.media, legacyBaseUrl) || undefined)
</script>

<template>
  <img
    :src="src"
    :srcset="srcset"
    :sizes="srcset ? sizes : undefined"
    :alt="media.alt"
    :width="media.width ?? undefined"
    :height="media.height ?? undefined"
    :loading="lazy ? 'lazy' : undefined"
    decoding="async"
  >
</template>
