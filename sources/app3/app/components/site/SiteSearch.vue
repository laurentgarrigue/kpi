<script setup lang="ts">
import { searchApiPath, searchSections, type SearchResults } from '~/utils/search'

// Search field of the header, on every page (FEATURE_SEARCH.md § 2.1, SRC-01, SRC-02).
const { t } = useI18n()
const query = ref('')
const { results } = useSuggestions<SearchResults>(query, searchApiPath)
const groups = computed(() => (results.value ? searchSections(results.value) : [])
  .map(section => ({ label: t(`search.category.${section.category}`), hits: section.hits })))
</script>

<template>
  <SearchCombobox
    id="site-search"
    v-model="query"
    action="/search"
    :label="$t('header.search')"
    :placeholder="$t('header.searchPlaceholder')"
    :groups="groups"
    see-all
    compact
  />
</template>
