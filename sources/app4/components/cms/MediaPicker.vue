<script setup lang="ts">
import type { CmsMedia } from '~/types/cms'
import { mediaSrc, validationErrors } from '~/utils/cms'

// Media library in a modal (PAGE_CMS.md § 4): pick an existing file or send a new one (image alt text required).
const props = defineProps<{ open: boolean, type?: 'image' | 'document' }>()
const emit = defineEmits<{ close: [], select: [media: CmsMedia] }>()
const { t } = useI18n()
const cms = useCmsApi()
const { legacyBaseUrl } = useRuntimeConfig().public

const items = ref<CmsMedia[]>([])
const page = ref(1)
const pages = ref(1)
const loading = ref(false)
const file = ref<File | null>(null)
const alt = ref('')
const uploadErrors = ref<Record<string, string>>({})
const uploading = ref(false)

async function load() {
  loading.value = true
  try {
    const result = await cms.media({ page: page.value, ...(props.type ? { type: props.type } : {}) })
    items.value = result.items
    pages.value = result.pages
  } finally {
    loading.value = false
  }
}

async function upload() {
  if (!file.value) return
  uploadErrors.value = {}
  if (file.value.type.startsWith('image/') && !alt.value.trim()) {
    uploadErrors.value = { alt_fr: 'required' }
    return
  }
  uploading.value = true
  try {
    const media = await cms.uploadMedia(file.value, { alt_fr: alt.value })
    emit('select', media)
    file.value = null
    alt.value = ''
  } catch (error) {
    uploadErrors.value = validationErrors(error)
  } finally {
    uploading.value = false
  }
}

function onFile(event: Event) {
  file.value = (event.target as HTMLInputElement).files?.[0] ?? null
}

watch(() => props.open, (open) => { if (open) load() })
watch(page, load)
</script>

<template>
  <AdminModal :open="open" :title="t('cms.media.pick')" max-width="3xl" @close="emit('close')">
    <div class="space-y-4">
      <div class="flex flex-wrap items-end gap-3 rounded border border-header-200 dark:border-header-700 p-3">
        <UFormField :label="t('cms.media.file')">
          <input type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="text-sm" data-testid="media-file" @change="onFile">
        </UFormField>
        <UFormField :label="t('cms.media.alt')" :error="uploadErrors.alt_fr ? t(`cms.errors.${uploadErrors.alt_fr}`) : undefined" class="flex-1 min-w-48">
          <UInput v-model="alt" class="w-full" />
        </UFormField>
        <UButton :loading="uploading" :disabled="!file" icon="heroicons:arrow-up-tray" @click="upload">{{ t('cms.media.upload') }}</UButton>
        <p v-if="uploadErrors.file" class="w-full text-sm text-danger-600">{{ t(`cms.errors.file_${uploadErrors.file}`) }}</p>
      </div>
      <div v-if="loading" class="py-6 text-center text-header-600 dark:text-header-300">{{ t('common.loading') }}</div>
      <ul v-else class="grid grid-cols-3 gap-3 sm:grid-cols-5">
        <li v-for="media in items" :key="media.id">
          <button type="button" class="w-full rounded border border-header-200 dark:border-header-700 p-1 hover:ring-2 hover:ring-primary-500" :title="media.name" @click="emit('select', media)">
            <img v-if="media.mime.startsWith('image/')" :src="mediaSrc(media.thumbnail, legacyBaseUrl)" :alt="media.alt_fr" class="h-24 w-full object-cover">
            <span v-else class="flex h-24 flex-col items-center justify-center text-xs">
              <UIcon name="heroicons:document" class="h-8 w-8" />{{ media.name }}
            </span>
          </button>
        </li>
      </ul>
      <CmsPager v-if="pages > 1" v-model:page="page" :pages="pages" />
    </div>
  </AdminModal>
</template>
