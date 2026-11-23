<script setup lang="ts">
import type { ChecklistCompetition } from '~/types'
import type { ReportScope } from '~/utils/reports'

/**
 * Choix du périmètre d'un nouveau rapport depuis la page Rapports (FEATURE_DESIGNATIONS.md D12, D14) : compétition,
 * événement ou journée de la saison. Un rapport sur un match se crée depuis la page des matchs.
 */
const props = defineProps<{ open: boolean, season: string }>()
const emit = defineEmits<{ close: [], chosen: [scope: { scopeType: ReportScope, scopeId: string, label: string }] }>()
const { t } = useI18n()
const api = useApi()

const scopeType = ref<Exclude<ReportScope, 'MATCH'>>('EVENEMENT')
const competitions = ref<ChecklistCompetition[]>([])
const events = ref<{ id: number, libelle: string, dateDebut: string | null }[]>([])
const gamedays = ref<{ id: number, phase: string | null, nom: string | null, dateDebut: string | null }[]>([])
const competition = ref('')
const choice = ref('')
const selectClass = 'w-full rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm'

const load = async () => {
  if (!props.open || !props.season) return
  const [filters, eventList] = await Promise.all([
    api.get<{ groups: { section: number, sectionLabel: string, competitions: { code: string, libelle: string }[] }[] }>('/admin/filters/competitions', { seasons: props.season }),
    api.get<{ items: { id: number, libelle: string, dateDebut: string | null }[] }>('/admin/gamedays/events'),
  ])
  competitions.value = (filters.groups ?? []).flatMap(group => group.competitions.map(c => ({ ...c, section: group.section, sectionLabel: group.sectionLabel })))
  events.value = eventList.items
}

watch(competition, async (code) => {
  gamedays.value = []
  if (scopeType.value === 'JOURNEE' && code) {
    gamedays.value = (await api.get<{ items: typeof gamedays.value }>('/admin/gamedays', { season: props.season, competitions: code, limit: '0' })).items
  }
})
watch(scopeType, () => {
  choice.value = ''
  competition.value = ''
})
watch(() => props.open, load, { immediate: true })

const label = computed(() => {
  if (scopeType.value === 'EVENEMENT') return events.value.find(e => String(e.id) === choice.value)?.libelle ?? choice.value
  if (scopeType.value === 'COMPETITION') return competitions.value.find(c => c.code === choice.value)?.libelle ?? choice.value
  const gameday = gamedays.value.find(g => String(g.id) === choice.value)
  return gameday ? `${competition.value} · ${gameday.phase || gameday.nom || gameday.id}` : choice.value
})

const submit = () => {
  const scopeId = scopeType.value === 'COMPETITION' ? competition.value : choice.value
  if (scopeId) emit('chosen', { scopeType: scopeType.value, scopeId, label: scopeType.value === 'COMPETITION' ? `${competition.value} · ${competitions.value.find(c => c.code === competition.value)?.libelle ?? ''}` : label.value })
}
</script>

<template>
  <AdminModal :open="open" :title="t('reports.new.choose_title')" @close="emit('close')">
    <form class="space-y-4" @submit.prevent="submit">
      <fieldset>
        <legend class="block text-sm font-medium mb-1">{{ t('reports.new.scope') }}</legend>
        <div class="flex flex-wrap gap-4 text-sm">
          <label v-for="type in (['EVENEMENT', 'JOURNEE', 'COMPETITION'] as const)" :key="type" class="inline-flex items-center gap-1.5">
            <input v-model="scopeType" type="radio" :value="type">
            {{ t(`reports.scope.${type}`) }}
          </label>
        </div>
      </fieldset>
      <div v-if="scopeType === 'EVENEMENT'">
        <label for="report-event" class="block text-sm font-medium mb-1">{{ t('reports.scope.EVENEMENT') }}</label>
        <select id="report-event" v-model="choice" :class="selectClass" required>
          <option value="">—</option>
          <option v-for="event in events" :key="event.id" :value="String(event.id)">{{ event.libelle }}<template v-if="event.dateDebut"> ({{ event.dateDebut }})</template></option>
        </select>
      </div>
      <div v-else>
        <label for="report-competition" class="block text-sm font-medium mb-1">{{ t('reports.scope.COMPETITION') }}</label>
        <select id="report-competition" v-model="competition" :class="selectClass" required>
          <option value="">—</option>
          <option v-for="c in competitions" :key="c.code" :value="c.code">{{ c.code }} - {{ c.libelle }}</option>
        </select>
      </div>
      <div v-if="scopeType === 'JOURNEE' && competition">
        <label for="report-gameday" class="block text-sm font-medium mb-1">{{ t('reports.scope.JOURNEE') }}</label>
        <select id="report-gameday" v-model="choice" :class="selectClass" required>
          <option value="">—</option>
          <option v-for="g in gamedays" :key="g.id" :value="String(g.id)">{{ g.dateDebut }} · {{ g.phase || g.nom || g.id }}</option>
        </select>
      </div>
      <p class="text-xs text-header-500">{{ t('reports.new.match_hint') }}</p>
      <div class="flex justify-end gap-2">
        <button type="button" class="px-3 py-1.5 rounded border border-header-300 dark:border-header-700 text-sm" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm disabled:opacity-50" :disabled="scopeType === 'COMPETITION' ? !competition : !choice">{{ t('common.next') }}</button>
      </div>
    </form>
  </AdminModal>
</template>
