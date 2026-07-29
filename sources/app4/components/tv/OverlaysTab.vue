<script setup lang="ts">
/**
 * "Incrustations" tab of the TV page: display tokens and chaining settings of an event
 * (PAGE_INCRUSTATION.md §7/§11bis, plan lot 4).
 *
 * Two jobs, both operational rather than technical:
 *  - **mint and revoke display tokens**, and hand out the ready-to-paste OBS URL. A token
 *    lives in a video mixer's configuration that can end up anywhere, so revocation must
 *    be one click (plan §4.4);
 *  - **set the chaining delays**, at event level or for a single pitch. An empty field
 *    means "inherit" (pitch → event → server default), never zero — the placeholder shows
 *    the inherited value so nobody has to guess.
 */
const props = defineProps<{ eventId: number | null }>()

const { t } = useI18n()
const api = useApi()
const toast = useToast()
const config = useRuntimeConfig()

interface DisplayToken {
  id: number
  token: string
  pitch: string | null
  label: string | null
  expires_at: string | null
  revoked_at: string | null
  last_used_at: string | null
}
interface DisplaySettingsRow {
  pitch: string | null
  halftime_score_delay: number | null
  final_score_delay: number | null
  final_score_duration: number | null
  next_game_delay: number | null
  next_game_duration: number | null
  background: string | null
  style_id: string | null
}
interface DisplaysResponse {
  tokens: DisplayToken[]
  settings: DisplaySettingsRow[]
  defaults: Record<string, string | number>
}

const loading = ref(false)
const tokens = ref<DisplayToken[]>([])
const settingsRows = ref<DisplaySettingsRow[]>([])
const defaults = ref<Record<string, string | number>>({})

// New token form
const newToken = reactive({ pitch: '', label: '', days: 7 })
const lastCreated = ref<{ token: string, pitch: string | null } | null>(null)

// Settings form — scope: '' = whole event, otherwise a pitch number
const settingsScope = ref('')
const form = reactive<Record<string, string>>({
  halftimeScoreDelay: '',
  finalScoreDelay: '',
  finalScoreDuration: '',
  nextGameDelay: '',
  nextGameDuration: '',
  background: '',
  styleId: ''
})

const SETTING_FIELDS = [
  'halftimeScoreDelay', 'finalScoreDelay', 'finalScoreDuration',
  'nextGameDelay', 'nextGameDuration', 'background', 'styleId'
] as const

const COLUMN_OF: Record<string, keyof DisplaySettingsRow> = {
  halftimeScoreDelay: 'halftime_score_delay',
  finalScoreDelay: 'final_score_delay',
  finalScoreDuration: 'final_score_duration',
  nextGameDelay: 'next_game_delay',
  nextGameDuration: 'next_game_duration',
  background: 'background',
  styleId: 'style_id'
}

const load = async () => {
  if (!props.eventId) return
  loading.value = true
  try {
    const res = await api.get<DisplaysResponse>(`/admin/scoring/displays/${props.eventId}`)
    tokens.value = res.tokens
    settingsRows.value = res.settings
    defaults.value = res.defaults
    fillForm()
  } catch { /* handled by useApi */ } finally {
    loading.value = false
  }
}

/** Load the stored row of the selected scope into the form (empty = inherit). */
const fillForm = () => {
  const row = settingsRows.value.find(r => (r.pitch ?? '') === settingsScope.value)
  for (const field of SETTING_FIELDS) {
    const value = row ? row[COLUMN_OF[field]] : null
    form[field] = value === null || value === undefined ? '' : String(value)
  }
}
watch(settingsScope, fillForm)
watch(() => props.eventId, load, { immediate: true })

/** Placeholder = the value actually inherited if the field stays empty. */
const inheritedOf = (field: string): string => {
  if (settingsScope.value !== '') {
    const eventRow = settingsRows.value.find(r => r.pitch === null)
    const value = eventRow?.[COLUMN_OF[field]]
    if (value !== null && value !== undefined) return String(value)
  }
  return String(defaults.value[field] ?? '')
}

const createToken = async () => {
  if (!props.eventId) return
  try {
    const res = await api.post<{ token: string, pitch: string | null }>(
      `/admin/scoring/displays/${props.eventId}/tokens`,
      { pitch: newToken.pitch, label: newToken.label, days: newToken.days }
    )
    lastCreated.value = res
    newToken.label = ''
    await load()
  } catch { /* handled by useApi */ }
}

const revokeToken = async (id: number) => {
  try {
    await api.del(`/admin/scoring/displays/tokens/${id}`)
    await load()
  } catch { /* handled by useApi */ }
}

const saveSettings = async () => {
  if (!props.eventId) return
  try {
    await api.put(`/admin/scoring/displays/${props.eventId}/settings`, {
      pitch: settingsScope.value,
      ...form
    })
    toast.add({ title: t('tv.overlays.settings_saved'), color: 'success' })
    await load()
  } catch { /* handled by useApi */ }
}

/** Ready-to-paste OBS URL for a token (blocks kept broad; the operator can trim them). */
const overlayUrl = (token: DisplayToken, pitch?: string): string => {
  const base = String(config.public.baseUrl ?? '/admin2')
  const p = pitch ?? token.pitch ?? '1'
  const origin = typeof window !== 'undefined' ? window.location.origin : ''
  return `${origin}${base}/live/overlay?event=${props.eventId}&pitch=${p}`
    + `&token=${token.token}&blocks=score,clock,shotclock,penalty,fact,next`
}

const copy = async (text: string) => {
  try {
    await navigator.clipboard.writeText(text)
    toast.add({ title: t('tv.overlays.copied'), color: 'success' })
  } catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
}

const isActive = (token: DisplayToken): boolean =>
  token.revoked_at === null && (token.expires_at === null || new Date(token.expires_at) > new Date())
</script>

<template>
  <div v-if="!eventId" class="text-header-600 py-8 text-center">
    {{ t('tv.overlays.select_event') }}
  </div>

  <div v-else class="space-y-8">
    <!-- ─── Display tokens ─── -->
    <section>
      <h3 class="font-semibold mb-1">{{ t('tv.overlays.tokens_title') }}</h3>
      <p class="text-sm text-header-600 mb-3">{{ t('tv.overlays.tokens_hint') }}</p>

      <div class="flex flex-wrap items-end gap-2 mb-4">
        <div>
          <label class="block text-xs text-header-600 mb-1">{{ t('tv.overlays.pitch') }}</label>
          <UInput v-model="newToken.pitch" size="sm" class="w-24" :placeholder="t('tv.overlays.all_pitches')" />
        </div>
        <div>
          <label class="block text-xs text-header-600 mb-1">{{ t('tv.overlays.label') }}</label>
          <UInput v-model="newToken.label" size="sm" class="w-56" :placeholder="t('tv.overlays.label_placeholder')" />
        </div>
        <div>
          <label class="block text-xs text-header-600 mb-1">{{ t('tv.overlays.days') }}</label>
          <UInput v-model.number="newToken.days" type="number" size="sm" class="w-20" />
        </div>
        <UButton size="sm" icon="i-heroicons-plus" @click="createToken">{{ t('tv.overlays.create_token') }}</UButton>
      </div>

      <UAlert
        v-if="lastCreated"
        color="success"
        variant="soft"
        class="mb-4"
        :title="t('tv.overlays.token_created')"
        :description="t('tv.overlays.token_created_hint')"
      />

      <div v-if="loading" class="text-sm text-header-600">…</div>
      <table v-else-if="tokens.length" class="w-full text-sm">
        <thead class="text-xs text-header-600">
          <tr>
            <th class="text-left">{{ t('tv.overlays.label') }}</th>
            <th class="text-left w-24">{{ t('tv.overlays.pitch') }}</th>
            <th class="text-left w-40">{{ t('tv.overlays.expires') }}</th>
            <th class="text-left w-40">{{ t('tv.overlays.last_used') }}</th>
            <th class="w-56" />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="tok in tokens"
            :key="tok.id"
            class="border-t border-header-100"
            :class="isActive(tok) ? '' : 'opacity-50'"
          >
            <td class="py-1">{{ tok.label || '—' }}</td>
            <td class="py-1">{{ tok.pitch || t('tv.overlays.all_pitches') }}</td>
            <td class="py-1">
              <span v-if="tok.revoked_at" class="text-error-600">{{ t('tv.overlays.revoked') }}</span>
              <span v-else>{{ tok.expires_at || '—' }}</span>
            </td>
            <td class="py-1">{{ tok.last_used_at || '—' }}</td>
            <td class="py-1 text-right space-x-1">
              <UButton
                v-if="isActive(tok)"
                size="xs"
                variant="outline"
                icon="i-heroicons-clipboard"
                @click="copy(overlayUrl(tok))"
              >{{ t('tv.overlays.copy_url') }}</UButton>
              <UButton
                v-if="isActive(tok)"
                size="xs"
                color="error"
                variant="ghost"
                icon="i-heroicons-x-mark"
                @click="revokeToken(tok.id)"
              >{{ t('tv.overlays.revoke') }}</UButton>
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="text-sm text-header-400">{{ t('tv.overlays.no_token') }}</div>
    </section>

    <!-- ─── Chaining settings ─── -->
    <section>
      <h3 class="font-semibold mb-1">{{ t('tv.overlays.settings_title') }}</h3>
      <p class="text-sm text-header-600 mb-3">{{ t('tv.overlays.settings_hint') }}</p>

      <div class="flex items-end gap-2 mb-3">
        <div>
          <label class="block text-xs text-header-600 mb-1">{{ t('tv.overlays.scope') }}</label>
          <UInput v-model="settingsScope" size="sm" class="w-40" :placeholder="t('tv.overlays.scope_event')" />
        </div>
        <UButton size="sm" @click="saveSettings">{{ t('tv.overlays.save') }}</UButton>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <div v-for="field in SETTING_FIELDS" :key="field">
          <label class="block text-xs text-header-600 mb-1">{{ t('tv.overlays.field.' + field) }}</label>
          <UInput v-model="form[field]" size="sm" :placeholder="inheritedOf(field)" />
        </div>
      </div>
      <p class="text-xs text-header-400 mt-2">{{ t('tv.overlays.inherit_hint') }}</p>
    </section>
  </div>
</template>
