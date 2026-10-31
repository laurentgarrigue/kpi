<script setup lang="ts">
import { attachedFormsApiPath, type FormSummary } from '~/utils/forms'
import { relatedNewsApiPath, type ArticleLink, type NewsList } from '~/utils/news'

// Editorial content linked to a competition, an event or a club: its 3 latest news (PAGE_NEWS.md § 2.2.5, NEWS-09)
// and, for an event or a competition, its open registration forms (FEATURE_FORMS.md § 2, FRM-10). Hidden when empty
// or when api2 fails: the results page stays whole.
const props = defineProps<{ kind: ArticleLink['kind'], reference: string }>()
const { locale } = useI18n()
const localePath = useLocalePath()
const api2 = useApi2()

const { data } = await useAsyncData(
  () => `linked:${props.kind}:${props.reference}:${locale.value}`,
  async () => {
    const [news, forms] = await Promise.all([
      api2<NewsList>(relatedNewsApiPath(props.kind, props.reference, locale.value)).then(list => list.items).catch(() => []),
      props.kind === 'club'
        ? Promise.resolve([])
        : api2<FormSummary[]>(attachedFormsApiPath(props.kind, props.reference, locale.value)).catch(() => []),
    ])
    return { news, forms: forms.filter(form => form.state !== 'closed') }
  },
)
</script>

<template>
  <div v-if="data && (data.forms.length > 0 || data.news.length > 0)" class="space-y-8">
    <section v-if="data.forms.length > 0" aria-labelledby="linked-forms" class="rounded border border-kpi-blue-600 p-4" data-testid="linked-forms">
      <h2 id="linked-forms" class="mb-2 text-[1.875rem] leading-none text-kpi-blue-700">{{ $t('forms.attached') }}</h2>
      <ul class="space-y-2">
        <li v-for="form in data.forms" :key="form.slug" class="flex flex-wrap items-center gap-3">
          <span class="font-semibold">{{ form.title }}</span>
          <UButton :to="localePath(`/forms/${form.slug}`)" size="sm">{{ $t('forms.register') }}</UButton>
        </li>
      </ul>
    </section>
    <section v-if="data.news.length > 0" aria-labelledby="linked-news" class="space-y-4" data-testid="linked-news">
      <h2 id="linked-news" class="text-[1.875rem] leading-none text-kpi-blue-700">{{ $t('news.linkedNews') }}</h2>
      <ul class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <li v-for="article in data.news" :key="article.slug"><NewsRow :article="article" compact /></li>
      </ul>
    </section>
  </div>
</template>
