<script setup lang="ts">
import type { CmsArticleSummary, CmsHomeBlock, CmsMedia } from '~/types/cms'
import { moveItem, validationErrors } from '~/utils/cms'

// Home blocks of the public site (PAGE_CMS.md § 5, PAGE_CONTENT.md § 4): enable, order and configure alert,
// featured articles, latest news and partners.
definePageMeta({ layout: 'admin', middleware: ['auth', 'editorial'], editorialAdmin: true })

const { t } = useI18n()
const toast = useToast()
const cms = useCmsApi()
const blocks = ref<CmsHomeBlock[]>([])
const articles = ref<CmsArticleSummary[]>([])
const errors = ref<Record<string, string>>({})
const saving = ref(false)
const pickerFor = ref<number | null>(null)
const MAX_FEATURED = 3
const levelItems = computed(() => (['info', 'important'] as const).map(value => ({ label: t(`cms.home.level.${value}`), value })))
const articleItems = computed(() => articles.value.map(article => ({ label: article.title, value: article.id })))

function setPartnerLogo(block: CmsHomeBlock, media: CmsMedia) {
  const partner = block.config.partners?.[pickerFor.value ?? -1]
  if (partner) partner.media_id = media.id
  pickerFor.value = null
}

async function save() {
  errors.value = {}
  saving.value = true
  try {
    blocks.value = await cms.saveHomeBlocks(blocks.value)
    toast.add({ title: t('common.success'), description: t('cms.saved'), color: 'success', duration: 3000 })
  } catch (failure) {
    errors.value = validationErrors(failure)
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  const [loaded, published] = await Promise.all([cms.homeBlocks(), cms.articles({ status: 'published', page: 1 })])
  blocks.value = loaded
  articles.value = published.items
})
</script>

<template>
  <div class="space-y-4">
    <h1 class="text-2xl font-bold text-header-900 dark:text-header-50">{{ t('cms.home.title') }}</h1>
    <p class="text-sm text-header-600 dark:text-header-300">{{ t('cms.home.help') }}</p>
    <ol class="space-y-3">
      <li v-for="(block, index) in blocks" :key="block.id" class="space-y-3 rounded-lg bg-white dark:bg-header-900 p-4 shadow" :data-block="block.kind">
        <div class="flex items-center gap-2">
          <UButton size="xs" variant="ghost" icon="heroicons:chevron-up" :disabled="index === 0" :aria-label="t('cms.move_up')" @click="blocks = moveItem(blocks, index, -1)" />
          <UButton size="xs" variant="ghost" icon="heroicons:chevron-down" :disabled="index === blocks.length - 1" :aria-label="t('cms.move_down')" @click="blocks = moveItem(blocks, index, 1)" />
          <h2 class="flex-1 font-semibold">{{ t(`cms.home.kind.${block.kind}`) }}</h2>
          <USwitch v-model="block.enabled" :label="t('cms.home.enabled')" />
        </div>

        <template v-if="block.kind === 'alert'">
          <div class="grid gap-3 sm:grid-cols-3">
            <UFormField :label="t('cms.home.alert_level')"><USelect v-model="block.config.level" :items="levelItems" class="w-full" /></UFormField>
            <UFormField :label="t('cms.home.from')" :error="errors[`blocks.${index}.from`] ? t('cms.errors.invalid') : undefined"><UInput :model-value="block.config.from ?? ''" type="date" class="w-full" @update:model-value="block.config.from = String($event) || null" /></UFormField>
            <UFormField :label="t('cms.home.to')" :error="errors[`blocks.${index}.to`] ? t('cms.errors.invalid') : undefined"><UInput :model-value="block.config.to ?? ''" type="date" class="w-full" @update:model-value="block.config.to = String($event) || null" /></UFormField>
          </div>
          <UFormField :label="t('cms.home.alert_text_fr')"><UTextarea v-model="block.config.text_fr" class="w-full" :rows="2" maxlength="500" /></UFormField>
          <UFormField :label="t('cms.home.alert_text_en')"><UTextarea v-model="block.config.text_en" class="w-full" :rows="2" maxlength="500" /></UFormField>
        </template>

        <UFormField v-else-if="block.kind === 'featured'" :label="t('cms.home.featured_articles', { max: MAX_FEATURED })" :error="errors[`blocks.${index}.articles`] ? t(`cms.errors.${errors[`blocks.${index}.articles`]}`) : undefined">
          <USelectMenu v-model="block.config.articles" :items="articleItems" value-key="value" multiple class="w-full" :placeholder="t('cms.home.pick_articles')" />
        </UFormField>

        <UFormField v-else-if="block.kind === 'latest_news'" :label="t('cms.home.latest_count')">
          <UInputNumber v-model="block.config.count" :min="1" :max="12" class="w-32" />
        </UFormField>

        <template v-else-if="block.kind === 'partners'">
          <div v-for="(partner, partnerIndex) in block.config.partners ?? []" :key="partnerIndex" class="flex flex-wrap items-end gap-2">
            <UFormField :label="t('cms.home.partner_name')" :error="errors[`blocks.${index}.partners.${partnerIndex}.name`] ? t('cms.errors.required') : undefined"><UInput v-model="partner.name" class="w-48" /></UFormField>
            <UFormField :label="t('cms.home.partner_url')" :error="errors[`blocks.${index}.partners.${partnerIndex}.url`] ? t('cms.errors.invalid') : undefined"><UInput v-model="partner.url" class="w-64" placeholder="https://…" /></UFormField>
            <UButton size="sm" variant="outline" icon="heroicons:photo" @click="pickerFor = partnerIndex">{{ partner.media_id ? t('cms.home.logo_change') : t('cms.home.logo_pick') }}</UButton>
            <UButton size="sm" variant="ghost" color="error" icon="heroicons:trash" :aria-label="t('common.delete')" @click="block.config.partners!.splice(partnerIndex, 1)" />
          </div>
          <UButton size="sm" variant="outline" icon="heroicons:plus" @click="(block.config.partners ??= []).push({ name: '', url: '', media_id: null })">{{ t('cms.home.add_partner') }}</UButton>
          <CmsMediaPicker :open="pickerFor !== null" type="image" @close="pickerFor = null" @select="setPartnerLogo(block, $event)" />
        </template>
      </li>
    </ol>
    <UButton :loading="saving" @click="save">{{ t('common.save') }}</UButton>
  </div>
</template>
