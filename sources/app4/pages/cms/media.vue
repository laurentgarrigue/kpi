<script setup lang="ts">
import type { CmsAlbum, CmsMedia } from '~/types/cms'
import { formatBytes, mediaSrc, validationErrors } from '~/utils/cms'

// Media library (PAGE_CMS.md § 4, CMS-04): grid, drag and drop upload, details with alt text, replace, albums.
definePageMeta({ layout: 'admin', middleware: ['auth', 'editorial'] })

const { t, locale } = useI18n()
const toast = useToast()
const cms = useCmsApi()
const authStore = useAuthStore()
const { legacyBaseUrl } = useRuntimeConfig().public

const items = ref<CmsMedia[]>([])
const albums = ref<CmsAlbum[]>([])
const ALL = 'all'
const type = ref(ALL)
const album = ref(ALL)
const page = ref(1)
const pages = ref(1)
const loading = ref(false)
const uploading = ref(false)
const dragging = ref(false)
const selected = ref<CmsMedia | null>(null)
const errors = ref<Record<string, string>>({})
const albumTitle = ref('')
const albumOpen = ref(false)
const replaceInput = useTemplateRef<HTMLInputElement>('replaceInput')

const typeItems = computed(() => [
  { label: t('cms.media.all_types'), value: ALL },
  { label: t('cms.media.images'), value: 'image' },
  { label: t('cms.media.documents'), value: 'document' },
])
const albumItems = computed(() => [{ label: t('cms.media.all_albums'), value: ALL }, ...albums.value.map(item => ({ label: `${item.title_fr} (${item.media_count})`, value: String(item.id) }))])
const albumChoices = computed(() => [{ label: t('cms.media.no_album'), value: 'none' }, ...albums.value.map(item => ({ label: item.title_fr, value: String(item.id) }))])

async function load() {
  loading.value = true
  try {
    const result = await cms.media({ page: page.value, type: type.value === ALL ? '' : type.value, album: album.value === ALL ? '' : album.value })
    items.value = result.items
    pages.value = result.pages
  } finally {
    loading.value = false
  }
}

async function loadAlbums() {
  albums.value = await cms.albums()
}

async function uploadFiles(files: FileList | File[]) {
  uploading.value = true
  try {
    for (const file of Array.from(files)) {
      try {
        const media = await cms.uploadMedia(file, album.value !== ALL ? { album_id: album.value } : {})
        selected.value = media
      } catch (failure) {
        const code = validationErrors(failure).file
        toast.add({ title: file.name, description: code ? t(`cms.errors.file_${code}`) : t('common.error'), color: 'error', duration: 5000 })
      }
    }
    await load()
  } finally {
    uploading.value = false
  }
}

function onDrop(event: DragEvent) {
  dragging.value = false
  if (event.dataTransfer?.files.length) uploadFiles(event.dataTransfer.files)
}

async function saveDetails() {
  if (!selected.value) return
  errors.value = {}
  try {
    selected.value = await cms.updateMedia(selected.value.id, {
      alt_fr: selected.value.alt_fr, alt_en: selected.value.alt_en, credit: selected.value.credit, album_id: selected.value.album_id,
    })
    toast.add({ title: t('common.success'), description: t('cms.saved'), color: 'success', duration: 2000 })
    await Promise.all([load(), loadAlbums()])
  } catch (failure) {
    errors.value = validationErrors(failure)
  }
}

async function replace(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file || !selected.value) return
  try {
    selected.value = await cms.replaceMedia(selected.value.id, file)
    toast.add({ title: t('common.success'), description: t('cms.media.replaced'), color: 'success', duration: 3000 })
    await load()
  } catch (failure) {
    const code = validationErrors(failure).file
    toast.add({ title: t('common.error'), description: code ? t(`cms.errors.file_${code}`) : '', color: 'error', duration: 5000 })
  }
}

async function remove() {
  if (!selected.value) return
  try {
    await cms.deleteMedia(selected.value.id)
    selected.value = null
    await load()
  } catch {
    toast.add({ title: t('common.error'), description: t('cms.media.in_use'), color: 'error', duration: 5000 })
  }
}

async function createAlbum() {
  if (!albumTitle.value.trim()) return
  await cms.saveAlbum({ title_fr: albumTitle.value })
  albumTitle.value = ''
  albumOpen.value = false
  await loadAlbums()
}

const selectedAlbum = computed({
  get: () => (selected.value?.album_id ? String(selected.value.album_id) : 'none'),
  set: (value: string) => { if (selected.value) selected.value.album_id = value === 'none' ? null : Number(value) },
})

async function copyUrl(url: string) {
  await navigator.clipboard.writeText(url.split('?')[0] ?? url)
  toast.add({ title: t('cms.media.url_copied'), color: 'success', duration: 2000 })
}

watch([type, album], () => { page.value = 1; load() })
watch(page, load)
onMounted(() => Promise.all([load(), loadAlbums()]))
</script>

<template>
  <div>
    <h1 class="mb-6 text-2xl font-bold text-header-900 dark:text-header-50">{{ t('cms.media.title') }}</h1>
    <div class="mb-4 flex flex-wrap items-center gap-3">
      <USelect v-model="type" :items="typeItems" class="w-44" :aria-label="t('cms.media.type')" />
      <USelect v-model="album" :items="albumItems" class="w-56" :aria-label="t('cms.media.album')" />
      <UButton variant="outline" icon="heroicons:folder-plus" @click="albumOpen = true">{{ t('cms.media.new_album') }}</UButton>
    </div>

    <label
      class="mb-4 flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed p-6 text-sm"
      :class="dragging ? 'border-primary-500 bg-primary-50 dark:bg-primary-950' : 'border-header-300 dark:border-header-700'"
      data-testid="media-dropzone"
      @dragover.prevent="dragging = true"
      @dragleave="dragging = false"
      @drop.prevent="onDrop"
    >
      <UIcon :name="uploading ? 'heroicons:arrow-path' : 'heroicons:arrow-up-tray'" class="h-8 w-8" :class="{ 'animate-spin': uploading }" />
      <span>{{ t('cms.media.drop') }}</span>
      <span class="text-xs text-header-600 dark:text-header-300">{{ t('cms.media.limits') }}</span>
      <input type="file" multiple accept="image/jpeg,image/png,image/webp,application/pdf" class="sr-only" @change="uploadFiles(($event.target as HTMLInputElement).files ?? [])">
    </label>

    <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
      <div>
        <p v-if="loading && items.length === 0" class="py-6 text-center">{{ t('common.loading') }}</p>
        <p v-else-if="items.length === 0" class="py-6 text-center">{{ t('cms.media.empty') }}</p>
        <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-6">
          <li v-for="media in items" :key="media.id">
            <button type="button" class="w-full rounded border p-1 text-left" :class="selected?.id === media.id ? 'border-primary-500 ring-2 ring-primary-500' : 'border-header-200 dark:border-header-700'" @click="selected = { ...media }">
              <img v-if="media.mime.startsWith('image/')" :src="mediaSrc(media.thumbnail, legacyBaseUrl)" :alt="media.alt_fr" class="h-24 w-full object-cover" loading="lazy">
              <span v-else class="flex h-24 items-center justify-center"><UIcon name="heroicons:document" class="h-10 w-10" /></span>
              <span class="mt-1 block truncate text-xs">{{ media.name }}</span>
            </button>
          </li>
        </ul>
        <CmsPager v-if="pages > 1" v-model:page="page" :pages="pages" class="mt-4" />
      </div>

      <aside v-if="selected" class="space-y-3 rounded-lg bg-white dark:bg-header-900 p-4 shadow" data-testid="media-details">
        <img v-if="selected.mime.startsWith('image/')" :src="mediaSrc(selected.url, legacyBaseUrl)" :alt="selected.alt_fr" class="max-h-48 w-full rounded object-contain">
        <p class="break-all text-sm font-medium">{{ selected.name }}</p>
        <p class="text-xs text-header-600 dark:text-header-300">
          {{ selected.mime }} · {{ formatBytes(selected.size, locale === 'en' ? 'en-GB' : 'fr-FR') }}<template v-if="selected.width"> · {{ selected.width }}×{{ selected.height }}</template>
        </p>
        <UButton size="xs" variant="outline" icon="heroicons:clipboard" @click="copyUrl(selected.url)">{{ t('cms.media.copy_url') }}</UButton>
        <UFormField :label="t('cms.media.alt_fr')" :required="selected.mime.startsWith('image/')" :error="errors.alt_fr ? t(`cms.errors.${errors.alt_fr}`) : undefined">
          <UInput v-model="selected.alt_fr" class="w-full" />
        </UFormField>
        <UFormField :label="t('cms.media.alt_en')">
          <UInput v-model="selected.alt_en" class="w-full" />
        </UFormField>
        <UFormField :label="t('cms.media.credit')">
          <UInput v-model="selected.credit" class="w-full" />
        </UFormField>
        <UFormField :label="t('cms.media.album')">
          <USelect v-model="selectedAlbum" :items="albumChoices" class="w-full" />
        </UFormField>
        <div class="flex flex-wrap gap-2">
          <UButton size="sm" @click="saveDetails">{{ t('common.save') }}</UButton>
          <UButton size="sm" variant="outline" icon="heroicons:arrow-path" @click="replaceInput?.click()">{{ t('cms.media.replace') }}</UButton>
          <input ref="replaceInput" type="file" class="sr-only" :accept="selected.mime" @change="replace">
          <UButton v-if="authStore.canAdministerContent" size="sm" color="error" variant="ghost" icon="heroicons:trash" @click="remove">{{ t('common.delete') }}</UButton>
        </div>
        <p class="text-xs text-header-600 dark:text-header-300">{{ t('cms.media.replace_help') }}</p>
      </aside>
    </div>

    <AdminModal :open="albumOpen" :title="t('cms.media.new_album')" @close="albumOpen = false">
      <form class="space-y-3" @submit.prevent="createAlbum">
        <UFormField :label="t('cms.fields.title')"><UInput v-model="albumTitle" class="w-full" autofocus /></UFormField>
        <UButton type="submit">{{ t('common.save') }}</UButton>
      </form>
    </AdminModal>
  </div>
</template>
