<template>
  <AdminModal :open="open" :title="title" max-width="3xl" @close="emit('close')">
    <p v-if="loading" class="text-base">{{ t('common.loading') }}</p>
    <p v-else-if="games.length === 0" class="text-base text-header-500">{{ t('medals.check_none') }}</p>
    <template v-else>
      <p class="mb-3 text-sm text-header-500">
        {{ total > games.length ? t('medals.check_total_capped', { n: total, shown: games.length }) : t('medals.check_total', { n: total }, total) }}
        · {{ t('medals.check_hint') }}
      </p>
      <ul class="max-h-[60vh] divide-y divide-header-100 overflow-y-auto text-base dark:divide-header-800" data-testid="medal-games">
        <li v-for="game in games" :key="game.id" class="flex flex-wrap items-baseline gap-x-4 gap-y-1 py-2.5">
          <span class="w-24 shrink-0 text-sm tabular-nums text-header-500">{{ formatDate(game.date) }}</span>
          <span class="w-28 shrink-0 text-sm">{{ game.competition }}<template v-if="game.number !== null"> · n°{{ game.number }}</template></span>
          <span class="min-w-48 flex-1">
            {{ game.team_a ?? '?' }} <b class="tabular-nums">{{ game.score_a }} – {{ game.score_b }}</b> {{ game.team_b ?? '?' }}
            <span v-if="game.awards?.length" class="block text-sm text-header-500">{{ game.awards.map(auditAwardText).join(' ; ') }}</span>
            <span v-else-if="game.timeline_issue" class="block text-sm text-header-500">{{ t(`medals.issues.${game.timeline_issue}`) }}</span>
          </span>
          <a :href="gameSheetUrl(runtimeConfig.public.app2BaseUrl, game.id)" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-700 hover:underline dark:text-primary-300">
            {{ t('medals.check_open_sheet') }} <UIcon name="i-heroicons-arrow-top-right-on-square" class="size-4" />
          </a>
        </li>
      </ul>
    </template>
  </AdminModal>
</template>

<script setup lang="ts">
// Games behind a figure of the medals page (FEATURE_MATCH_MEDALS.md § 9.3, MED-80): opened from the catalogue
// (games of an element) or the seasons (games set aside); each one opens its sheet in app2 to check what was typed.
import { auditAwardText, gameSheetUrl, type AuditGame } from '~/utils/medals'

defineProps<{ open: boolean, title: string, loading: boolean, total: number, games: AuditGame[] }>()
const emit = defineEmits<{ close: [] }>()
const { t, locale } = useI18n()
const runtimeConfig = useRuntimeConfig()

const formatDate = (date: string | null) => (date ? new Date(`${date}T00:00:00`).toLocaleDateString(locale.value, { dateStyle: 'short' }) : '—')
</script>
