<script setup lang="ts">
import type { ScoringEventCode, ScoringPlayer, TeamSide } from '~/types/scoring'

/**
 * Keyboard-only entry of a fact (spec §7.1 — opened by the `N` shortcut), in two steps:
 *  1. the jersey number — "7", "a7" or "7b"; `Entrée` picks when one player matches, a
 *     number present on both sides asks for the team letter;
 *  2. the fact, by its letter (B = goal, V/J/R = green/yellow/red card, D = black card).
 * The page then stages the fact and focuses its time: `Entrée` there records it, as with
 * the mouse. `Échap` closes at any step without recording anything.
 *
 * Why a second step instead of focusing the fact keys of the page: `Entrée` and `Espace`
 * are console shortcuts (shot clock, game clock) — on a focused button they would fire the
 * shortcut, not the button.
 *
 * Props down / events up: emits `select`; the page owns the selection and the staging.
 */
const props = defineProps<{
  open: boolean
  playersA: ScoringPlayer[]
  playersB: ScoringPlayer[]
  teamA: string
  teamB: string
  /** Fact keys offered in step 2, in display order. */
  facts: { code: ScoringEventCode, label: string }[]
}>()

const emit = defineEmits<{
  select: [team: TeamSide, player: ScoringPlayer, code: ScoringEventCode]
  close: []
}>()

const { t } = useI18n()

const query = ref('')
const picked = ref<ScoringPlayer | null>(null)
const input = ref<{ $el?: HTMLElement } | null>(null)
const factStep = ref<HTMLElement | null>(null)

watch(() => props.open, async (open) => {
  if (!open) return
  query.value = ''
  picked.value = null
  await nextTick()
  input.value?.$el?.querySelector('input')?.focus()
})

const matches = computed(() => findPlayersByNumber(query.value, props.playersA, props.playersB))
const digits = computed(() => query.value.replace(/\D/g, ''))

const pick = async (player: ScoringPlayer) => {
  picked.value = player
  await nextTick()
  factStep.value?.focus()
}

const onEnter = () => {
  if (matches.value.length === 1) void pick(matches.value[0]!)
}

const chooseFact = (code: ScoringEventCode) => {
  if (picked.value) emit('select', picked.value.team, picked.value, code)
}

const onFactKey = (event: KeyboardEvent) => {
  if (event.key === 'Escape') {
    event.preventDefault()
    emit('close')
    return
  }
  const fact = props.facts.find(f => f.code === event.key.toUpperCase())
  if (fact) {
    event.preventDefault()
    chooseFact(fact.code)
  }
}

const teamName = (team: TeamSide) => (team === 'A' ? props.teamA : props.teamB)
</script>

<template>
  <Teleport to="body">
    <div v-if="open" class="fixed inset-0 z-[9999] flex items-start justify-center p-4 pt-[15vh]" @click.self="emit('close')">
      <div class="absolute inset-0 bg-black/40" @click="emit('close')" />
      <div
        class="relative w-full max-w-sm rounded-lg bg-white dark:bg-header-900 shadow-xl p-4 space-y-3"
        role="dialog"
        :aria-label="t('scoring.picker.title')"
      >
        <!-- Step 1: who -->
        <template v-if="!picked">
          <label for="scoring-number-picker" class="block text-sm font-semibold">{{ t('scoring.picker.title') }}</label>
          <UInput
            id="scoring-number-picker"
            ref="input"
            v-model="query"
            size="xl"
            class="w-full scoring-digits"
            autocomplete="off"
            :placeholder="t('scoring.picker.placeholder')"
            @keydown.enter.prevent="onEnter"
            @keydown.esc.prevent="emit('close')"
          />

          <ul v-if="matches.length" class="space-y-1">
            <li v-for="p in matches" :key="`${p.team}${p.matric}`">
              <button
                type="button"
                class="w-full flex items-center gap-3 rounded-md border border-header-200 dark:border-header-700 px-3 py-2 text-left hover:bg-header-50 dark:hover:bg-header-800 focus-visible:outline-2 focus-visible:outline-primary-500"
                @click="pick(p)"
              >
                <UBadge size="sm" :color="p.team === 'A' ? 'primary' : 'neutral'">{{ p.team }}</UBadge>
                <span class="scoring-digits w-8 text-center">{{ p.numero }}</span>
                <span class="flex-1 min-w-0 truncate">{{ p.nom.toUpperCase() }} {{ p.prenom }}</span>
                <span class="text-xs text-header-500 dark:text-header-400 truncate max-w-24">{{ teamName(p.team) }}</span>
              </button>
            </li>
          </ul>

          <p class="text-xs text-header-600 dark:text-header-300">
            <template v-if="matches.length > 1">{{ t('scoring.picker.ambiguous', { number: digits }) }}</template>
            <template v-else-if="digits && !matches.length">{{ t('scoring.picker.none') }}</template>
            <template v-else>{{ t('scoring.picker.hint') }}</template>
          </p>
        </template>

        <!-- Step 2: what — one letter -->
        <div
          v-else
          ref="factStep"
          tabindex="-1"
          class="space-y-3 outline-none"
          @keydown="onFactKey"
        >
          <p class="flex items-center gap-2 text-sm">
            <UBadge size="sm" :color="picked.team === 'A' ? 'primary' : 'neutral'">{{ picked.team }}</UBadge>
            <span class="scoring-digits">{{ picked.numero }}</span>
            <strong class="truncate">{{ picked.nom.toUpperCase() }} {{ picked.prenom }}</strong>
          </p>
          <div class="grid grid-cols-1 gap-1">
            <UButton
              v-for="f in facts" :key="f.code"
              block
              variant="outline"
              color="neutral"
              class="justify-start"
              @click="chooseFact(f.code)"
            >
              <template #leading><ScoringEventIcon :code="f.code" /></template>
              <span class="flex-1 text-left">{{ f.label }}</span>
              <kbd class="rounded border border-header-300 dark:border-header-600 px-1.5 text-xs">{{ f.code }}</kbd>
            </UButton>
          </div>
          <p class="text-xs text-header-600 dark:text-header-300">{{ t('scoring.picker.fact_hint') }}</p>
        </div>
      </div>
    </div>
  </Teleport>
</template>
