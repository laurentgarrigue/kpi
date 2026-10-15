<script setup lang="ts">
import type { CmsPage, CmsPageSummary } from '~/types/cms'
import { isReservedPageSlug, previewUrl, slugify, validationErrors } from '~/utils/cms'

// Editorial page editor (PAGE_CMS.md § 3, CMS-06): parent page, slug checked (reserved names), FR / EN, SEO, preview.
definePageMeta({ layout: 'admin', middleware: ['auth', 'editorial'] })

const route = useRoute()
const router = useRouter()
const { t } = useI18n()
const toast = useToast()
const cms = useCmsApi()
const authStore = useAuthStore()
const { publicSiteUrl } = useRuntimeConfig().public

const id = computed<number | 'new'>(() => (route.params.id === 'new' ? 'new' : Number(route.params.id)))
const emptyPage = (): CmsPage => ({
  slug: '', parent_id: null, title_fr: '', title_en: '', body_fr: '', body_en: '', status: 'draft',
  seo_title_fr: '', seo_title_en: '', seo_description_fr: '', seo_description_en: '',
})
const page = ref<CmsPage>(emptyPage())
const others = ref<CmsPageSummary[]>([])
const language = ref<'fr' | 'en'>('fr')
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const loading = ref(true)
const deleteOpen = ref(false)
const draft = useLocalDraft('page', () => id.value, () => page.value)
const restorable = ref<{ savedAt: string, value: CmsPage } | null>(null)
const languageTabs = [{ label: 'Français', value: 'fr' }, { label: 'English', value: 'en' }]
const statusItems = computed(() => (['draft', 'published', 'archived'] as const).map(value => ({ label: t(`cms.status.${value}`), value })))
const parentItems = computed(() => [
  { label: t('cms.pages.no_parent'), value: 'none' },
  ...others.value.filter(other => other.id !== page.value.id).map(other => ({ label: `${'— '.repeat(other.depth)}${other.title}`, value: String(other.id) })),
])
const parent = computed({
  get: () => (page.value.parent_id === null ? 'none' : String(page.value.parent_id)),
  set: (value: string) => { page.value.parent_id = value === 'none' ? null : Number(value) },
})
const slug = computed(() => page.value.slug || slugify(page.value.title_fr))
const slugWarning = computed(() => (isReservedPageSlug(slug.value) ? t('cms.errors.reserved') : undefined))
const error = (field: string) => (errors.value[field] ? t(`cms.errors.${errors.value[field]}`) : undefined)

async function load() {
  loading.value = true
  try {
    others.value = await cms.pages()
    page.value = id.value === 'new' ? emptyPage() : await cms.page(id.value)
    restorable.value = draft.read() as typeof restorable.value
  } finally {
    loading.value = false
  }
}

async function save(status?: CmsPage['status']) {
  errors.value = {}
  saving.value = true
  try {
    const saved = await cms.savePage({ ...page.value, ...(status ? { status } : {}), slug: slug.value })
    page.value = saved
    draft.clear()
    toast.add({ title: t('common.success'), description: t('cms.saved'), color: 'success', duration: 3000 })
    if (id.value === 'new') {
      await router.replace(`/cms/pages/${saved.id}`)
    }
  } catch (failure) {
    errors.value = validationErrors(failure)
  } finally {
    saving.value = false
  }
}

async function preview() {
  if (!page.value.id || !page.value.path) return
  const token = page.value.status === 'published' ? undefined : (await cms.pagePreviewToken(page.value.id)).token
  window.open(previewUrl(publicSiteUrl, page.value.path, token), '_blank', 'noopener')
}

async function remove() {
  if (!page.value.id) return
  await cms.deletePage(page.value.id)
  draft.clear()
  await router.push('/cms/pages')
}

onMounted(load)
</script>

<template>
  <div v-if="!loading" class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <NuxtLink to="/cms/pages" class="text-sm text-primary-700 dark:text-primary-300 hover:underline">← {{ t('cms.pages.title') }}</NuxtLink>
        <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">{{ page.title_fr || t('cms.pages.new') }}</h1>
        <p v-if="page.path" class="font-mono text-xs text-header-600 dark:text-header-300">{{ page.path }}</p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <CmsStatusBadge :status="page.status" />
        <UButton v-if="page.id" variant="outline" icon="heroicons:eye" @click="preview">{{ t('cms.preview') }}</UButton>
        <UButton :loading="saving" variant="outline" @click="save()">{{ t('common.save') }}</UButton>
        <UButton v-if="page.status !== 'published'" :loading="saving" icon="heroicons:paper-airplane" @click="save('published')">{{ t('cms.publish') }}</UButton>
        <UButton v-else :loading="saving" color="warning" variant="soft" @click="save('draft')">{{ t('cms.unpublish') }}</UButton>
      </div>
    </div>

    <UAlert
v-if="restorable" color="info" variant="subtle" :title="t('cms.draft_found', { date: restorable.savedAt.slice(0, 16).replace('T', ' ') })"
      :actions="[{ label: t('cms.draft_restore'), onClick: () => { if (restorable) page = restorable.value; restorable = null } }, { label: t('cms.draft_ignore'), variant: 'ghost', onClick: () => { draft.clear(); restorable = null } }]" />

    <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
      <section class="space-y-4">
        <UTabs v-model="language" :items="languageTabs" :content="false" />
        <UFormField :label="t('cms.fields.title')" :required="language === 'fr'" :error="error(`title_${language}`)">
          <UInput v-model="page[`title_${language}`]" class="w-full" size="lg" maxlength="255" />
        </UFormField>
        <UFormField :label="t('cms.fields.body')">
          <CmsRichEditor :key="language" v-model="page[`body_${language}`]" />
        </UFormField>
        <details class="rounded border border-header-200 dark:border-header-700 p-3">
          <summary class="cursor-pointer font-medium">{{ t('cms.seo.title_section') }}</summary>
          <CmsSeoFields v-model="page" :language="language" :fallback-title="page[`title_${language}`]" :errors="errors" class="mt-3" />
        </details>
      </section>
      <aside class="space-y-4">
        <UFormField :label="t('cms.status_label')">
          <USelect v-model="page.status" :items="statusItems" class="w-full" />
        </UFormField>
        <UFormField :label="t('cms.fields.slug')" :help="t('cms.pages.slug_help')" :error="error('slug') ?? slugWarning">
          <UInput v-model="page.slug" class="w-full" :placeholder="slugify(page.title_fr)" />
        </UFormField>
        <UFormField :label="t('cms.pages.parent')" :error="error('parent_id')">
          <USelect v-model="parent" :items="parentItems" class="w-full" />
        </UFormField>
        <UButton v-if="page.id && authStore.canAdministerContent" color="error" variant="ghost" icon="heroicons:trash" @click="deleteOpen = true">{{ t('common.delete') }}</UButton>
      </aside>
    </div>
    <AdminConfirmModal :open="deleteOpen" :title="t('common.delete')" :message="t('cms.pages.delete_confirm', { title: page.title_fr })" @close="deleteOpen = false" @confirm="remove" />
  </div>
</template>
