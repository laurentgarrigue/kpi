<script setup lang="ts">
import type { CmsArticle, CmsCategory, CmsLinkKind } from '~/types/cms'
import { mediaSrc, previewUrl, slugify, toDateTimeLocal, fromDateTimeLocal, validationErrors } from '~/utils/cms'

// Article editor (PAGE_CMS.md § 2, ADM-CMS-02/04): FR / EN tabs, TipTap body, categories, signature, links to the
// results pages, cover image, SEO, scheduling, preview on the public site, local draft.
definePageMeta({ layout: 'admin', middleware: ['auth', 'editorial'] })

const route = useRoute()
const router = useRouter()
const { t, locale } = useI18n()
const toast = useToast()
const cms = useCmsApi()
const authStore = useAuthStore()
const { publicSiteUrl, legacyBaseUrl } = useRuntimeConfig().public

const id = computed<number | 'new'>(() => (route.params.id === 'new' ? 'new' : Number(route.params.id)))
const LINK_KINDS: CmsLinkKind[] = ['competition', 'event', 'club']
const emptyArticle = (): CmsArticle => ({
  slug: '', title_fr: '', title_en: '', lead_fr: '', lead_en: '', body_fr: '', body_en: '', cover_media_id: null,
  cover: null, status: 'draft', published_at: null, signature: 'La CNA Kayak-Polo', categories: [], links: [],
  seo_title_fr: '', seo_title_en: '', seo_description_fr: '', seo_description_en: '',
})

const article = ref<CmsArticle>(emptyArticle())
const categories = ref<CmsCategory[]>([])
const language = ref<'fr' | 'en'>('fr')
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const loading = ref(true)
const coverPickerOpen = ref(false)
const deleteOpen = ref(false)
const publishedAt = computed({
  get: () => toDateTimeLocal(article.value.published_at),
  set: (value: string) => { article.value.published_at = fromDateTimeLocal(value) },
})
const draft = useLocalDraft('article', () => id.value, () => article.value)
const restorable = ref<{ savedAt: string, value: CmsArticle } | null>(null)
const languageTabs = computed(() => [{ label: 'Français', value: 'fr' }, { label: 'English', value: 'en' }])
const statusItems = computed(() => (['draft', 'published', 'archived'] as const).map(value => ({ label: t(`cms.status.${value}`), value })))
const linkKindItems = computed(() => LINK_KINDS.map(value => ({ label: t(`cms.articles.link_kind.${value}`), value })))
const error = (field: string) => (errors.value[field] ? t(`cms.errors.${errors.value[field]}`) : undefined)

async function load() {
  loading.value = true
  try {
    categories.value = await cms.categories()
    article.value = id.value === 'new' ? emptyArticle() : await cms.article(id.value)
    restorable.value = draft.read() as typeof restorable.value
  } finally {
    loading.value = false
  }
}

function restoreDraft() {
  if (restorable.value) {
    article.value = restorable.value.value
  }
  restorable.value = null
}

function toggleCategory(categoryId: number, checked: boolean) {
  const selected = new Set(article.value.categories)
  if (checked) selected.add(categoryId)
  else selected.delete(categoryId)
  article.value.categories = [...selected]
}

async function save(status?: CmsArticle['status']) {
  errors.value = {}
  saving.value = true
  try {
    const saved = await cms.saveArticle({ ...article.value, ...(status ? { status } : {}), slug: article.value.slug || slugify(article.value.title_fr) })
    article.value = saved
    draft.clear()
    toast.add({ title: t('common.success'), description: t('cms.saved'), color: 'success', duration: 3000 })
    if (id.value === 'new') {
      await router.replace(`/cms/articles/${saved.id}`)
    }
  } catch (failure) {
    errors.value = validationErrors(failure)
  } finally {
    saving.value = false
  }
}

async function preview() {
  if (!article.value.id) return
  const isPublic = article.value.display_status === 'published'
  const token = isPublic ? undefined : (await cms.articlePreviewToken(article.value.id)).token
  window.open(previewUrl(publicSiteUrl, `/news/${article.value.slug}`, token), '_blank', 'noopener')
}

async function remove() {
  if (!article.value.id) return
  await cms.deleteArticle(article.value.id)
  draft.clear()
  await router.push('/cms/articles')
}

onMounted(load)
</script>

<template>
  <div v-if="!loading" class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <NuxtLink to="/cms/articles" class="text-sm text-primary-700 dark:text-primary-300 hover:underline">← {{ t('cms.articles.title') }}</NuxtLink>
        <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">{{ article.title_fr || t('cms.articles.new') }}</h1>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <CmsStatusBadge v-if="article.display_status" :status="article.display_status" />
        <UButton v-if="article.id" variant="outline" icon="heroicons:eye" @click="preview">{{ t('cms.preview') }}</UButton>
        <UButton :loading="saving" variant="outline" @click="save()">{{ t('common.save') }}</UButton>
        <UButton v-if="article.status !== 'published'" :loading="saving" icon="heroicons:paper-airplane" data-testid="publish" @click="save('published')">{{ t('cms.publish') }}</UButton>
        <UButton v-else :loading="saving" color="warning" variant="soft" @click="save('draft')">{{ t('cms.unpublish') }}</UButton>
      </div>
    </div>

    <UAlert
v-if="restorable" color="info" variant="subtle" :title="t('cms.draft_found', { date: restorable.savedAt.slice(0, 16).replace('T', ' ') })"
      :actions="[{ label: t('cms.draft_restore'), onClick: restoreDraft }, { label: t('cms.draft_ignore'), variant: 'ghost', onClick: () => { draft.clear(); restorable = null } }]" />

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
      <section class="space-y-4">
        <UTabs v-model="language" :items="languageTabs" :content="false" />
        <UFormField :label="t('cms.fields.title')" :required="language === 'fr'" :error="error(`title_${language}`)">
          <UInput v-model="article[`title_${language}`]" class="w-full" size="lg" maxlength="255" />
        </UFormField>
        <UFormField v-if="language === 'fr'" :label="t('cms.fields.slug')" :help="t('cms.fields.slug_help', { slug: article.slug || slugify(article.title_fr) })" :error="error('slug')">
          <UInput v-model="article.slug" class="w-full" :placeholder="slugify(article.title_fr)" />
        </UFormField>
        <UFormField :label="t('cms.fields.lead')">
          <UTextarea v-model="article[`lead_${language}`]" class="w-full" :rows="3" />
        </UFormField>
        <UFormField :label="t('cms.fields.body')">
          <CmsRichEditor :key="language" v-model="article[`body_${language}`]" />
        </UFormField>
        <details class="rounded border border-header-200 dark:border-header-700 p-3">
          <summary class="cursor-pointer font-medium">{{ t('cms.seo.title_section') }}</summary>
          <CmsSeoFields v-model="article" :language="language" :fallback-title="article[`title_${language}`]" :errors="errors" class="mt-3" />
        </details>
      </section>

      <aside class="space-y-4">
        <UFormField :label="t('cms.status_label')" :error="error('status')">
          <USelect v-model="article.status" :items="statusItems" class="w-full" />
        </UFormField>
        <UFormField :label="t('cms.fields.published_at')" :help="t('cms.fields.published_at_help')" :error="error('published_at')">
          <UInput v-model="publishedAt" type="datetime-local" class="w-full" />
        </UFormField>
        <fieldset class="space-y-1" data-testid="categories">
          <legend class="text-sm font-medium">{{ t('cms.categories') }} <span class="text-danger-600">*</span></legend>
          <UCheckbox
v-for="category in categories" :key="category.id" :model-value="article.categories.includes(category.id)"
            :label="locale === 'en' ? category.label_en : category.label_fr" @update:model-value="toggleCategory(category.id, !!$event)" />
          <p v-if="errors.categories" class="text-sm text-danger-600">{{ t(`cms.errors.categories_${errors.categories}`) }}</p>
        </fieldset>
        <UFormField :label="t('cms.fields.signature')" :help="t('cms.fields.signature_help')" :error="error('signature')">
          <UInput v-model="article.signature" class="w-full" maxlength="150" />
        </UFormField>
        <div class="space-y-2">
          <p class="text-sm font-medium">{{ t('cms.fields.cover') }}</p>
          <img v-if="article.cover" :src="mediaSrc(article.cover.thumbnail, legacyBaseUrl)" :alt="article.cover.alt_fr" class="h-32 w-full rounded object-cover">
          <div class="flex gap-2">
            <UButton size="sm" variant="outline" icon="heroicons:photo" @click="coverPickerOpen = true">{{ t('cms.fields.cover_pick') }}</UButton>
            <UButton v-if="article.cover_media_id" size="sm" variant="ghost" color="neutral" @click="article.cover_media_id = null; article.cover = null">{{ t('cms.remove') }}</UButton>
          </div>
        </div>
        <fieldset class="space-y-2">
          <legend class="text-sm font-medium">{{ t('cms.articles.links') }}</legend>
          <p class="text-xs text-header-600 dark:text-header-300">{{ t('cms.articles.links_help') }}</p>
          <div v-for="(link, index) in article.links" :key="index" class="flex gap-1">
            <USelect v-model="link.kind" :items="linkKindItems" class="w-32" />
            <UInput v-model="link.ref" class="flex-1" :placeholder="t(`cms.articles.link_placeholder.${link.kind}`)" />
            <UButton icon="heroicons:x-mark" variant="ghost" color="neutral" :aria-label="t('cms.remove')" @click="article.links.splice(index, 1)" />
          </div>
          <p v-if="errors.links" class="text-sm text-danger-600">{{ t('cms.errors.links_invalid') }}</p>
          <UButton size="sm" variant="outline" icon="heroicons:plus" @click="article.links.push({ kind: 'competition', ref: '' })">{{ t('cms.articles.add_link') }}</UButton>
        </fieldset>
        <UButton v-if="article.id && authStore.canAdministerContent" color="error" variant="ghost" icon="heroicons:trash" @click="deleteOpen = true">{{ t('common.delete') }}</UButton>
      </aside>
    </div>

    <CmsMediaPicker :open="coverPickerOpen" type="image" @close="coverPickerOpen = false" @select="(media) => { article.cover_media_id = media.id; article.cover = media; coverPickerOpen = false }" />
    <AdminConfirmModal :open="deleteOpen" :title="t('common.delete')" :message="t('cms.articles.delete_confirm', { title: article.title_fr })" @close="deleteOpen = false" @confirm="remove" />
  </div>
</template>
