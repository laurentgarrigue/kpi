<script setup lang="ts">
import type { ChecklistCompetition } from '~/types'
import type { ReportScope } from '~/utils/reports'

/**
 * Choix de l'élément auquel rattacher un nouveau rapport, depuis la page Rapports (FEATURE_DESIGNATIONS.md D12, D14) :
 * compétition, événement, journée ou match de la saison, chacun avec une recherche. C'est le seul point d'entrée de
 * création : les autres pages de l'admin ne portent pas de bouton « Rapport ».
 */
interface Option { value: string, label: string }

const props = defineProps<{ open: boolean, season: string }>()
const emit = defineEmits<{ close: [], chosen: [scope: { scopeType: ReportScope, scopeId: string, label: string }] }>()
const { t } = useI18n()
const api = useApi()

const SCOPES: ReportScope[] = ['EVENEMENT', 'JOURNEE', 'MATCH', 'COMPETITION']
const scopeType = ref<ReportScope>('EVENEMENT')
const competitions = ref<ChecklistCompetition[]>([])
const events = ref<Option[]>([])
const gamedays = ref<Option[]>([])
const matches = ref<Option[]>([])
const competition = ref('')
const gameday = ref('')
const choice = ref('')
const searches = reactive({ competition: '', event: '', gameday: '', match: '' })
const fieldClass = 'w-full rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm'

const filtered = (options: Option[], query: string) => {
  const q = query.trim().toLowerCase()
  return q ? options.filter(option => option.label.toLowerCase().includes(q)) : options
}
const competitionOptions = computed(() => filtered(competitions.value.map(c => ({ value: c.code, label: `${c.code} - ${c.libelle}` })), searches.competition))
const needsCompetition = computed(() => scopeType.value !== 'EVENEMENT')

const load = async () => {
  if (!props.open || !props.season) return
  const [filters, eventList] = await Promise.all([
    api.get<{ groups: { section: number, sectionLabel: string, competitions: { code: string, libelle: string }[] }[] }>('/admin/filters/competitions', { seasons: props.season }),
    api.get<{ items: { id: number, libelle: string, dateDebut: string | null }[] }>('/admin/gamedays/events'),
  ])
  competitions.value = (filters.groups ?? []).flatMap(group => group.competitions.map(c => ({ ...c, section: group.section, sectionLabel: group.sectionLabel })))
  events.value = eventList.items.map(event => ({ value: String(event.id), label: event.dateDebut ? `${event.libelle} (${event.dateDebut})` : event.libelle }))
}

watch(competition, async (code) => {
  gameday.value = ''
  gamedays.value = []
  if (code && (scopeType.value === 'JOURNEE' || scopeType.value === 'MATCH')) {
    const list = await api.get<{ items: { id: number, phase: string | null, nom: string | null, lieu: string | null, dateDebut: string | null }[] }>('/admin/gamedays', { season: props.season, competitions: code, limit: '0' })
    gamedays.value = list.items.map(g => ({ value: String(g.id), label: [g.dateDebut, g.phase || g.nom || String(g.id), g.lieu].filter(Boolean).join(' · ') }))
  }
})
watch(gameday, async (id) => {
  matches.value = []
  if (id && scopeType.value === 'MATCH') {
    const list = await api.get<{ items: { id: number, numeroOrdre: number | null, dateMatch: string | null, heureMatch: string | null, equipeA: string | null, equipeB: string | null }[] }>('/admin/games', { season: props.season, journee: id, limit: '0' })
    matches.value = list.items.map(m => ({ value: String(m.id), label: `n° ${m.numeroOrdre ?? m.id} · ${m.equipeA ?? '?'} - ${m.equipeB ?? '?'} · ${[m.dateMatch, m.heureMatch].filter(Boolean).join(' ')}` }))
  }
})
watch(scopeType, () => {
  choice.value = ''
  competition.value = ''
  Object.assign(searches, { competition: '', event: '', gameday: '', match: '' })
})
watch(() => props.open, load, { immediate: true })

/** Élément choisi : sa valeur et son libellé, selon le type de rattachement. */
const selected = computed<Option | null>(() => {
  const pick = (options: Option[], value: string) => options.find(option => option.value === value) ?? null
  switch (scopeType.value) {
    case 'EVENEMENT': return pick(events.value, choice.value)
    case 'COMPETITION': return pick(competitionOptions.value, competition.value) ?? (competition.value ? { value: competition.value, label: competition.value } : null)
    case 'JOURNEE': return gameday.value ? { value: gameday.value, label: `${competition.value} · ${pick(gamedays.value, gameday.value)?.label ?? gameday.value}` } : null
    default: return choice.value ? { value: choice.value, label: `${competition.value} · ${pick(matches.value, choice.value)?.label ?? choice.value}` } : null
  }
})

const submit = () => {
  if (selected.value) emit('chosen', { scopeType: scopeType.value, scopeId: selected.value.value, label: selected.value.label })
}
</script>

<template>
  <AdminModal :open="open" :title="t('reports.new.choose_title')" max-width="xl" @close="emit('close')">
    <form class="space-y-4" @submit.prevent="submit">
      <fieldset>
        <legend class="block text-sm font-medium mb-1">{{ t('reports.new.scope') }}</legend>
        <div class="flex flex-wrap gap-4 text-sm">
          <label v-for="type in SCOPES" :key="type" class="inline-flex items-center gap-1.5">
            <input v-model="scopeType" type="radio" :value="type">
            {{ t(`reports.scope.${type}`) }}
          </label>
        </div>
      </fieldset>

      <div v-if="scopeType === 'EVENEMENT'" class="space-y-1">
        <label for="report-event-search" class="block text-sm font-medium">{{ t('reports.scope.EVENEMENT') }}</label>
        <input id="report-event-search" v-model="searches.event" type="search" :placeholder="t('reports.new.search')" :class="fieldClass">
        <select v-model="choice" size="6" :aria-label="t('reports.scope.EVENEMENT')" :class="fieldClass">
          <option v-for="option in filtered(events, searches.event)" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>

      <div v-if="needsCompetition" class="space-y-1">
        <label for="report-competition-search" class="block text-sm font-medium">{{ t('reports.scope.COMPETITION') }}</label>
        <input id="report-competition-search" v-model="searches.competition" type="search" :placeholder="t('reports.new.search')" :class="fieldClass">
        <select v-model="competition" size="6" :aria-label="t('reports.scope.COMPETITION')" :class="fieldClass">
          <option v-for="option in competitionOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>

      <div v-if="(scopeType === 'JOURNEE' || scopeType === 'MATCH') && competition" class="space-y-1">
        <label for="report-gameday-search" class="block text-sm font-medium">{{ t('reports.scope.JOURNEE') }}</label>
        <input id="report-gameday-search" v-model="searches.gameday" type="search" :placeholder="t('reports.new.search')" :class="fieldClass">
        <select v-model="gameday" size="5" :aria-label="t('reports.scope.JOURNEE')" :class="fieldClass">
          <option v-for="option in filtered(gamedays, searches.gameday)" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>

      <div v-if="scopeType === 'MATCH' && gameday" class="space-y-1">
        <label for="report-match-search" class="block text-sm font-medium">{{ t('reports.scope.MATCH') }}</label>
        <input id="report-match-search" v-model="searches.match" type="search" :placeholder="t('reports.new.search_match')" :class="fieldClass">
        <select v-model="choice" size="6" :aria-label="t('reports.scope.MATCH')" :class="fieldClass">
          <option v-for="option in filtered(matches, searches.match)" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
      </div>

      <p v-if="selected" class="text-sm">{{ t('reports.new.selected', { label: selected.label }) }}</p>
      <div class="flex justify-end gap-2">
        <button type="button" class="px-3 py-1.5 rounded border border-header-300 dark:border-header-700 text-sm" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm disabled:opacity-50" :disabled="!selected">{{ t('common.next') }}</button>
      </div>
    </form>
  </AdminModal>
</template>
