<script setup lang="ts">
import type { GroupSection } from '#kpi-layer/utils/results/types'
import { groupLabel } from '~/utils/competitions'

// Index of the groups of a season by section, as links, the current one marked (CPL-12): large screens only, the
// selection form covers phones and browsing without JavaScript.
defineProps<{ season: string, sections: GroupSection[], group: string | null }>()
const { locale } = useI18n()
const localePath = useLocalePath()
</script>

<template>
  <nav :aria-label="$t('results.group')" data-testid="group-index">
    <template v-for="section in sections" :key="section.section">
      <h2 class="mt-4 mb-1 font-sans text-[0.8125rem] font-bold text-muted">{{ $t(`results.section.${section.section}`) }}</h2>
      <ul>
        <li v-for="item in section.groups" :key="item.code">
          <NuxtLink
            :to="{ path: localePath(`/competitions/${season}`), query: { group: item.code } }"
            :aria-current="item.code === group ? 'page' : undefined"
            class="block rounded-md px-2.5 py-1.5"
            :class="item.code === group ? 'bg-navy font-semibold text-white' : 'hover:bg-wash'"
          >
            {{ groupLabel(item, locale) }}
          </NuxtLink>
        </li>
      </ul>
    </template>
  </nav>
</template>
